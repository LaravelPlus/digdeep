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

    // Share of requests to profile, 0.0–1.0. Lower it on busy apps to cut DB writes.
    'sample_rate' => env('DIGDEEP_SAMPLE_RATE', 1.0),

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
    | Maximum JSON payload size for a single profile. Oversized profiles get
    | their bodies shortened and query bindings dropped, then the largest
    | sections are emptied until the profile fits. `_truncated` and
    | `_dropped` in the stored data say what was removed.
    |
    */

    'max_profile_size_kb' => env('DIGDEEP_MAX_PROFILE_SIZE', 512),

    // Per-collector entry cap and max string length (SQL, log context, payload summaries).
    // Totals such as query_count are still counted from the full data.
    'limits' => [
        'max_entries' => env('DIGDEEP_MAX_ENTRIES', 500),
        'max_string_length' => env('DIGDEEP_MAX_STRING_LENGTH', 10000),
    ],

    // Prune to max_profiles on [chance, out of] writes. Or schedule `digdeep:prune` and set [0, 1].
    'prune_lottery' => [1, 10],

    /*
    |--------------------------------------------------------------------------
    | Redaction
    |--------------------------------------------------------------------------
    |
    | Applied before anything is stored: request/response bodies, session,
    | log context, outgoing HTTP client calls and SQL bindings. A key is
    | masked when its name contains any entry in 'keys' (case-insensitive).
    | Bindings are masked when bound to such a column (`password = ?`,
    | insert column lists) or when the value matches a 'binding_patterns'
    | regex (bcrypt/argon2 hashes by default).
    |
    */

    'redact' => [
        'keys' => ['password', 'passwd', 'token', 'secret', 'card', 'cvv', 'cvc', 'api_key', 'apikey', 'authorization', 'private_key'],
        'headers' => ['authorization', 'proxy-authorization', 'cookie', 'set-cookie', 'x-xsrf-token', 'x-csrf-token', 'x-api-key', 'x-auth-token'],
        'bindings' => env('DIGDEEP_REDACT_BINDINGS', true),
        'binding_patterns' => ['/^\$2[aby]\$\d\d\$/', '/^\$argon2/'],
    ],

    // Set to false to stop storing response bodies (JSON bodies are key-redacted either way).
    'capture_response_bodies' => env('DIGDEEP_CAPTURE_RESPONSE_BODIES', true),

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
