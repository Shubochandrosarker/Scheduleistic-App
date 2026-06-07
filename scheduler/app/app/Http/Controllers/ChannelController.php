<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\Workspace;
use App\Services\UsageService;
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
            'providers' => collect($this->providers->keys())->map(fn ($key) => [
                'key'   => $key,
                'label' => $this->providers->driver($key)->label(),
            ])->values(),
        ]);
    }

    /** Begin the OAuth connect flow for a provider. */
    public function connect(Request $request, Workspace $workspace, string $provider): RedirectResponse
    {
        $this->authorizeWorkspace($request, $workspace);

        if (! $this->usage->allows($workspace->team, 'channels')) {
            return back()->withErrors(['plan' => 'Your plan\'s channel limit has been reached. Upgrade to connect more accounts.']);
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

        Channel::updateOrCreate(
            [
                'workspace_id'        => $workspace->id,
                'provider'            => $provider,
                'provider_account_id' => $account->providerAccountId,
            ],
            [
                'name'             => $account->name,
                'avatar'          => $account->avatar,
                'access_token'     => $account->accessToken,
                'refresh_token'    => $account->refreshToken,
                'token_expires_at' => $account->expiresAt,
                'scopes'           => $account->scopes,
                'meta'             => $account->meta,
                'status'           => 'connected',
            ],
        );

        return redirect()
            ->route('workspaces.channels.index', $workspace)
            ->with('status', 'channel-connected');
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
