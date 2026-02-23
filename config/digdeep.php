<?php

return [

    'enabled' => env('DIGDEEP_ENABLED', true),

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

    'storage_path' => storage_path('digdeep/digdeep.sqlite'),

    'max_profiles' => 200,

    'ignored_paths' => [
        'digdeep',
        '_debugbar',
        'telescope',
        'horizon',
        'livewire',
        'favicon.ico',
    ],

];
