<?php
/**
 * Plugin Name: Wpistic Content Automation Bridge
 * Plugin URI:  https://wordpressistic.com
 * Description: Sends webhook to n8n when a post is published, with idempotency + retry. Also exposes admin REST endpoint for manual topic submission.
 * Version:     1.0.0
 * Author:      Wordpressistic
 * License:     GPL-2.0+
 * Text Domain: wpistic-automation
 *
 * @package Wpistic_Content_Automation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 *
 * Hooks: transition_post_status (idempotent fire on publish only).
 * Settings: stored under option 'wpistic_automation_settings'.
 */
final class Wpistic_Content_Automation {

	const OPTION_KEY = 'wpistic_automation_settings';
	const META_FIRED = '_wpistic_n8n_fired';

	/**
	 * Bootstrap.
	 */
	public static function init() {
		add_action( 'transition_post_status', [ __CLASS__, 'on_post_transition' ], 10, 3 );
		add_action( 'admin_menu',             [ __CLASS__, 'register_settings_page' ] );
		add_action( 'admin_init',             [ __CLASS__, 'register_settings' ] );
		add_action( 'rest_api_init',          [ __CLASS__, 'register_rest_routes' ] );
	}

	/**
	 * Fire webhook only on publish, only once per post (idempotent).
	 *
	 * @param string  $new New status.
	 * @param string  $old Old status.
	 * @param WP_Post $post Post object.
	 */
	public static function on_post_transition( $new, $old, $post ) {
		// Only on publish, not from publish→publish (avoid edits firing).
		if ( 'publish' !== $new || 'publish' === $old ) {
			return;
		}

		// Only blog posts (skip pages, custom types unless explicitly enabled).
		if ( 'post' !== $post->post_type ) {
			return;
		}

		// Idempotency: only fire once per post.
		if ( get_post_meta( $post->ID, self::META_FIRED, true ) ) {
			return;
		}

		$settings = get_option( self::OPTION_KEY, [] );
		$webhook_url = isset( $settings['webhook_url'] ) ? esc_url_raw( $settings['webhook_url'] ) : '';
		$secret      = isset( $settings['secret'] ) ? sanitize_text_field( $settings['secret'] ) : '';

		if ( empty( $webhook_url ) ) {
			return;
		}

		$payload = self::build_payload( $post );
		$result  = self::send_webhook( $webhook_url, $secret, $payload );

		if ( ! is_wp_error( $result ) && wp_remote_retrieve_response_code( $result ) < 400 ) {
			update_post_meta( $post->ID, self::META_FIRED, current_time( 'mysql', true ) );
		} else {
			// Log to error log for debugging; non-blocking.
			error_log( '[Wpistic Automation] Webhook failed for post ' . $post->ID . ': ' . wp_json_encode( $result ) );
		}
	}

	/**
	 * Construct the webhook payload.
	 *
	 * @param WP_Post $post Post object.
	 * @return array
	 */
	private static function build_payload( $post ) {
		$author_id = (int) $post->post_author;
		$author    = get_userdata( $author_id );
		$cats      = wp_get_post_categories( $post->ID, [ 'fields' => 'names' ] );
		$tags      = wp_get_post_tags( $post->ID, [ 'fields' => 'names' ] );
		$thumb_id  = (int) get_post_thumbnail_id( $post->ID );
		$thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'full' ) : '';

		return [
			'trigger'        => 'wp_post_published',
			'run_id'         => wp_generate_uuid4(),
			'fired_at_utc'   => gmdate( 'c' ),
			'site_url'       => home_url(),
			'post' => [
				'id'             => (int) $post->ID,
				'title'          => get_the_title( $post ),
				'slug'           => $post->post_name,
				'url'            => get_permalink( $post ),
				'excerpt'        => wp_strip_all_tags( get_the_excerpt( $post ) ),
				'content_html'   => apply_filters( 'the_content', $post->post_content ),
				'content_plain'  => wp_strip_all_tags( $post->post_content ),
				'categories'     => $cats,
				'tags'           => $tags,
				'featured_image' => $thumb_url,
				'author' => [
					'id'           => $author_id,
					'display_name' => $author ? $author->display_name : '',
				],
				'published_at'   => get_post_time( 'c', true, $post ),
			],
		];
	}

	/**
	 * Send the webhook with HMAC signature for verification.
	 *
	 * @param string $url     Endpoint.
	 * @param string $secret  Shared secret.
	 * @param array  $payload Payload.
	 * @return array|WP_Error
	 */
	private static function send_webhook( $url, $secret, $payload ) {
		$body      = wp_json_encode( $payload );
		$signature = hash_hmac( 'sha256', $body, $secret );

		return wp_remote_post(
			$url,
			[
				'method'   => 'POST',
				'timeout'  => 15,
				'blocking' => false, // Fire-and-forget; n8n handles its own retries.
				'headers'  => [
					'Content-Type'         => 'application/json',
					'X-Wpistic-Signature'  => $signature,
					'X-Wpistic-Run-Id'     => $payload['run_id'],
				],
				'body'     => $body,
			]
		);
	}

	/**
	 * Register admin settings page.
	 */
	public static function register_settings_page() {
		add_options_page(
			__( 'Wpistic Automation', 'wpistic-automation' ),
			__( 'Wpistic Automation', 'wpistic-automation' ),
			'manage_options',
			'wpistic-automation',
			[ __CLASS__, 'render_settings_page' ]
		);
	}

	/**
	 * Register settings + fields.
	 */
	public static function register_settings() {
		register_setting(
			'wpistic_automation',
			self::OPTION_KEY,
			[
				'type'              => 'array',
				'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ],
			]
		);
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param array $input Raw input.
	 * @return array Cleaned.
	 */
	public static function sanitize_settings( $input ) {
		return [
			'webhook_url' => isset( $input['webhook_url'] ) ? esc_url_raw( $input['webhook_url'] ) : '',
			'secret'      => isset( $input['secret'] ) ? sanitize_text_field( $input['secret'] ) : '',
		];
	}

	/**
	 * Render the settings page.
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = get_option( self::OPTION_KEY, [] );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Wpistic Content Automation', 'wpistic-automation' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'wpistic_automation' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="wpistic_webhook_url"><?php esc_html_e( 'n8n Webhook URL', 'wpistic-automation' ); ?></label></th>
						<td>
							<input type="url" id="wpistic_webhook_url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[webhook_url]" value="<?php echo esc_attr( $settings['webhook_url'] ?? '' ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Production webhook URL from your n8n instance.', 'wpistic-automation' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="wpistic_secret"><?php esc_html_e( 'Shared Secret (HMAC)', 'wpistic-automation' ); ?></label></th>
						<td>
							<input type="text" id="wpistic_secret" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[secret]" value="<?php echo esc_attr( $settings['secret'] ?? '' ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Used to sign requests. n8n verifies this header before processing.', 'wpistic-automation' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register REST routes for manual topic submission.
	 */
	public static function register_rest_routes() {
		register_rest_route(
			'wpistic/v1',
			'/manual-trigger',
			[
				'methods'             => 'POST',
				'callback'            => [ __CLASS__, 'rest_manual_trigger' ],
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args' => [
					'topic'    => [ 'required' => true, 'type' => 'string' ],
					'angle'    => [ 'required' => false, 'type' => 'string' ],
					'audience' => [ 'required' => false, 'type' => 'string' ],
				],
			]
		);
	}

	/**
	 * Manual topic submission endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_manual_trigger( $request ) {
		$settings = get_option( self::OPTION_KEY, [] );
		if ( empty( $settings['webhook_url'] ) ) {
			return new WP_REST_Response( [ 'error' => 'Webhook URL not configured.' ], 500 );
		}

		$payload = [
			'trigger'      => 'manual_topic',
			'run_id'       => wp_generate_uuid4(),
			'fired_at_utc' => gmdate( 'c' ),
			'topic'        => sanitize_text_field( $request->get_param( 'topic' ) ),
			'angle'        => sanitize_text_field( $request->get_param( 'angle' ) ),
			'audience'     => sanitize_text_field( $request->get_param( 'audience' ) ),
			'submitted_by' => wp_get_current_user()->display_name,
		];

		self::send_webhook( $settings['webhook_url'], $settings['secret'] ?? '', $payload );

		return new WP_REST_Response(
			[
				'status' => 'queued',
				'run_id' => $payload['run_id'],
			],
			202
		);
	}
}

Wpistic_Content_Automation::init();
