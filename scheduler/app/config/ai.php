<?php

/*
|--------------------------------------------------------------------------
| AI Caption Assistant
|--------------------------------------------------------------------------
|
| Pluggable LLM provider (OpenRouter / OpenAI / Anthropic-compatible chat
| completions). The per-network prompts encode the Wordpressistic brand voice
| (ported from automation/prompts/brand-voice-prompts.md).
|
*/

return [

    'fake'     => env('AI_FAKE', false),
    'endpoint' => env('AI_ENDPOINT', 'https://openrouter.ai/api/v1/chat/completions'),
    'key'      => env('AI_API_KEY'),
    'model'    => env('AI_MODEL', 'openai/gpt-4o-mini'),

    // Shared brand-voice guardrails applied to every generation.
    'system' => 'You write social media copy in the Wordpressistic brand voice: confident, '
        .'strategic, outcome-focused. No hype words ("game-changer", "leverage", "synergy"). '
        .'No AI tells ("In conclusion", "Let\'s dive in", "It\'s not just X, it\'s Y"). '
        .'Lead with concrete examples over theory. Return only the post text.',

    // Per-network shaping.
    'prompts' => [
        'linkedin'         => 'Write a LinkedIn post (max ~1300 chars), professional and insightful, about: :topic',
        'linkedin_company' => 'Write a LinkedIn company-page post, professional, about: :topic',
        'facebook'         => 'Write a friendly, engaging Facebook post about: :topic',
        'instagram'        => 'Write an Instagram caption with 3-5 relevant hashtags about: :topic',
        'threads'          => 'Write a short, punchy Threads post about: :topic',
        'pinterest'        => 'Write a concise, keyword-rich Pinterest pin description about: :topic',
        'tiktok'           => 'Write a short hooky TikTok caption with 2-3 hashtags about: :topic',
        'youtube'          => 'Write a YouTube video title and short description about: :topic',
        'mastodon'         => 'Write a concise Mastodon post about: :topic',
        'bluesky'          => 'Write a concise Bluesky post (max 300 chars) about: :topic',
        'medium'           => 'Write a short Medium intro paragraph about: :topic',
        'wordpress'        => 'Write a blog excerpt about: :topic',
        'default'          => 'Write a social media post about: :topic',
    ],

];
