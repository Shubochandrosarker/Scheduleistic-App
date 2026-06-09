<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\Workspace;
use App\Services\UsageService;
use App\Social\Data\ConnectedAccount;
use App\Social\Providers\AbstractOAuthProvider;
use App\Social\Providers\AbstractTokenProvider;
use App\Social\ProviderManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ChannelController extends Controller
{
    public function __construct(
        private readonly ProviderManager $providers,
        private readonly UsageService $usage,
    ) {}

    public function index(Request $request, Workspace $workspace): Response
    {
        $this->authorizeWorkspace($request, $workspace);

        return Inertia::render('Channels/Index', [
            'workspace' => $workspace,
            'channels'  => $workspace->channels()->latest()->get(),
            'providers' => collect($this->providers->keys())->map(function ($key) {
                $driver = $this->providers->driver($key);
                $isToken = $driver instanceof AbstractTokenProvider;

                return [
                    'key'        => $key,
                    'label'      => $driver->label(),
                    'type'       => $isToken ? 'token' : 'oauth',
                    'fields'     => $isToken ? $driver->fields() : [],
                    // OAuth providers without credentials can't be connected on this instance.
                    'configured' => $driver instanceof AbstractOAuthProvider ? $driver->isConfigured() : true,
                ];
            })->values(),
        ]);
    }

    /** Begin the OAuth connect flow for a provider. */
    public function connect(Request $request, Workspace $workspace, string $provider): RedirectResponse
    {
        $this->authorizeWorkspace($request, $workspace);

        if (! $this->usage->allows($workspace->team, 'channels')) {
            return back()->withErrors(['plan' => 'Your plan\'s channel limit has been reached. Upgrade to connect more accounts.']);
        }

        $driver = $this->providers->driver($provider);

        // Token-based providers are connected via the credential form, not OAuth.
        if ($driver instanceof AbstractTokenProvider) {
            return back()->withErrors(['provider' => 'This network is connected with credentials below, not a redirect.']);
        }

        if ($driver instanceof AbstractOAuthProvider && ! $driver->isConfigured()) {
            abort(503, "The {$provider} app is not configured on this instance. Add its OAuth credentials to your .env.");
        }

        $state = Str::random(40);
        $request->session()->put('oauth.state', $state);
        $request->session()->put('oauth.workspace', $workspace->id);
        $request->session()->put('oauth.provider', $provider);

        $url = $this->providers->driver($provider)->authorizationUrl(
            $state,
            route('channels.callback', $provider),
        );

        return redirect()->away($url);
    }

    /** Handle the provider OAuth callback and create the channel. */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        abort_unless(
            $request->query('state') && $request->query('state') === $request->session()->pull('oauth.state'),
            403,
            'Invalid OAuth state.',
        );

        $workspace = Workspace::findOrFail($request->session()->pull('oauth.workspace'));
        $this->authorizeWorkspace($request, $workspace);

        $account = $this->providers->driver($provider)->exchangeCode(
            (string) $request->query('code'),
            route('channels.callback', $provider),
        );

        $this->storeAccount($workspace, $provider, $account);

        return redirect()
            ->route('workspaces.channels.index', $workspace)
            ->with('status', 'channel-connected');
    }

    /** Connect a token / app-password based provider via the credential form. */
    public function storeToken(Request $request, Workspace $workspace, string $provider): RedirectResponse
    {
        $this->authorizeWorkspace($request, $workspace);

        $driver = $this->providers->driver($provider);
        abort_unless($driver instanceof AbstractTokenProvider, 404);

        if (! $this->usage->allows($workspace->team, 'channels')) {
            return back()->withErrors(['plan' => 'Your plan\'s channel limit has been reached. Upgrade to connect more accounts.']);
        }

        // Validate the provider-declared fields are all present.
        $rules = [];
        foreach ($driver->fields() as $field) {
            $rules[$field['key']] = ['required', 'string', 'max:500'];
        }
        $validated = $request->validate($rules);

        $this->storeAccount($workspace, $provider, $driver->accountFromCredentials($validated));

        return redirect()
            ->route('workspaces.channels.index', $workspace)
            ->with('status', 'channel-connected');
    }

    /** Persist (or refresh) a channel from a connected account. */
    protected function storeAccount(Workspace $workspace, string $provider, ConnectedAccount $account): void
    {
        Channel::updateOrCreate(
            [
                'workspace_id'        => $workspace->id,
                'provider'            => $provider,
                'provider_account_id' => $account->providerAccountId,
            ],
            [
                'name'             => $account->name,
                'avatar'           => $account->avatar,
                'access_token'     => $account->accessToken,
                'refresh_token'    => $account->refreshToken,
                'token_expires_at' => $account->expiresAt,
                'scopes'           => $account->scopes,
                'meta'             => $account->meta,
                'status'           => 'connected',
            ],
        );
    }

    public function destroy(Request $request, Workspace $workspace, Channel $channel): RedirectResponse
    {
        $this->authorizeWorkspace($request, $workspace);
        abort_unless($channel->workspace_id === $workspace->id, 403);

        $channel->delete();

        return back()->with('status', 'channel-disconnected');
    }

    protected function authorizeWorkspace(Request $request, Workspace $workspace): void
    {
        abort_unless($workspace->team_id === $request->user()->currentTeam->id, 403);
    }
}
