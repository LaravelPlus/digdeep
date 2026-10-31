<?php

declare(strict_types=1);

return [

    // null (unset) = enabled only in local/testing environments. Set DIGDEEP_ENABLED=true to force on.
    'enabled' => env('DIGDEEP_ENABLED'),

    /*
    |--------------------------------------------------------------------------
    | Debugbar
    |--------------------------------------------------------------------------
    |
    | When true, a floating toolbar is injected into full HTML responses
    | showing real-time profiling data (queries, memory, events, etc.).
    | Similar to Laravel Debugbar but powered by DigDeep.
    |
    */

    'show_debugbar' => env('DIGDEEP_SHOW_DEBUGBAR', true),

    /*
    |--------------------------------------------------------------------------
    | Code Actions
    |--------------------------------------------------------------------------
    |
    | The "run artisan command" box and the AI "apply fix" button execute
    | commands and rewrite files under app/. Leave unset to allow them only
    | in the local environment; set DIGDEEP_CODE_ACTIONS=false to disable
    | them everywhere. Never enable on a shared or public server.
    |
    */

    'code_actions' => env('DIGDEEP_CODE_ACTIONS'),

    /*
    |--------------------------------------------------------------------------
    | Auto Profile
    |--------------------------------------------------------------------------
    |
    | When true, every web request is automatically profiled via middleware.
    | When false, only manually-triggered profiles are captured.
    |
    */

    'auto_profile' => env('DIGDEEP_AUTO_PROFILE', true),

    'max_profiles' => env('DIGDEEP_MAX_PROFILES', 200),

    /*
    |--------------------------------------------------------------------------
    | Threshold Alerts
    |--------------------------------------------------------------------------
    |
    | Profiles exceeding these thresholds are auto-tagged and fire
    | a ThresholdExceeded event for notification hooks.
    |
    */

    'thresholds' => [
        'duration_ms' => env('DIGDEEP_THRESHOLD_DURATION', 500),
        'query_count' => env('DIGDEEP_THRESHOLD_QUERIES', 20),
        'memory_peak_mb' => env('DIGDEEP_THRESHOLD_MEMORY', 64),
        'query_time_ms' => env('DIGDEEP_THRESHOLD_QUERY_TIME', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Max Profile Size (KB)
    |--------------------------------------------------------------------------
    |
    | Maximum JSON payload size for a single profile. Profiles exceeding
    | this limit have their request/response bodies and query bindings
    | truncated to stay within bounds.
    |
    */

    'max_profile_size_kb' => env('DIGDEEP_MAX_PROFILE_SIZE', 512),

    /*
    |--------------------------------------------------------------------------
    | AI Integration
    |--------------------------------------------------------------------------
    |
    | DigDeep can call an AI provider to suggest fixes for flagged queries.
    | Set DIGDEEP_AI_KEY to your API key and DIGDEEP_AI_PROVIDER to either
    | 'openai' or 'anthropic'. When set, these take priority over the app's
    | default laravel/ai configuration.
    |
    */

    'ai_provider' => env('DIGDEEP_AI_PROVIDER', null),

    'ai_key' => env('DIGDEEP_AI_KEY', null),

    /*
    |--------------------------------------------------------------------------
    | AI Model
    |--------------------------------------------------------------------------
    |
    | Override the model used for AI-assisted analysis. Leave null to use
    | the package default (claude-haiku-4-5-20251001 for Anthropic,
    | gpt-4o-mini for OpenAI).
    |
    */

    'ai_model' => env('DIGDEEP_AI_MODEL', null),

    'ignored_paths' => [
        'digdeep',
        '_debugbar',
        '_boost',
        'telescope',
        'horizon',
        'livewire',
        'favicon.ico',
    ],

];
