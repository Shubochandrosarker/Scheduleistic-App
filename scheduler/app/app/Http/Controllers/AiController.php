<?php

namespace App\Http\Controllers;

use App\Services\AiAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function __construct(private readonly AiAssistant $ai) {}

    /** Generate brand-voice captions for the requested networks. */
    public function generate(Request $request): JsonResponse
    {
        // Must belong to an organization (cost is attributable to a tenant).
        abort_unless($request->user()->currentTeam, 403);

        $validated = $request->validate([
            'topic'       => ['required', 'string', 'max:500'],
            'providers'   => ['required', 'array', 'min:1'],
            'providers.*' => ['string'],
        ]);

        return response()->json([
            'captions' => $this->ai->captions($validated['topic'], $validated['providers']),
        ]);
    }
}
