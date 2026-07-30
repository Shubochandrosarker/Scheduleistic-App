<?php

/*
|--------------------------------------------------------------------------
| Media Library
|--------------------------------------------------------------------------
|
| Storage goes through Laravel's filesystem abstraction, so the same code
| runs against the local disk in development and an S3-compatible bucket in
| production. Nothing in the app builds a filesystem path by hand.
|
*/

return [

    /*
    | Which configured disk assets live on. Set MEDIA_DISK=s3 in production.
    */
    'disk' => env('MEDIA_DISK', env('FILESYSTEM_DISK', 'local')),

    /*
    | Hard ceiling on a single upload, in megabytes. This is a safety valve
    | independent of the plan storage quota — it stops one request from
    | consuming the whole allowance (and the request timeout).
    */
    'max_upload_mb' => (int) env('MEDIA_MAX_UPLOAD_MB', 512),

    /*
    | Derivatives generated asynchronously after upload. The original is never
    | modified; each entry produces a new object alongside it.
    |
    | `fit` is 'cover' (crop to fill) or 'contain' (letterbox).
    */
    'variants' => [
        'thumb'   => ['width' => 320,  'height' => 320,  'fit' => 'cover',   'quality' => 80],
        'preview' => ['width' => 1080, 'height' => 1080, 'fit' => 'contain', 'quality' => 82],
        'web'     => ['width' => 1600, 'height' => 1600, 'fit' => 'contain', 'quality' => 85],
    ],

    /*
    | Antivirus hook. When a command is configured it is run against the
    | uploaded file before the asset is marked ready; a non-zero exit marks
    | the asset failed and quarantines it. Left empty, scanning is skipped and
    | the asset is marked ready — the hook exists so operators can wire in
    | ClamAV without patching application code.
    |
    | Example: MEDIA_AV_COMMAND="clamdscan --no-summary --stdout"
    */
    'antivirus_command' => env('MEDIA_AV_COMMAND'),

    /*
    | Optional third-party import sources shown in the library's "Import from"
    | menu. A source is only offered when its credentials are configured, so
    | the UI never advertises an integration that cannot complete.
    */
    'sources' => [
        'canva'    => ['label' => 'Canva',        'configured' => (bool) env('CANVA_CLIENT_ID')],
        'drive'    => ['label' => 'Google Drive', 'configured' => (bool) env('GOOGLE_DRIVE_CLIENT_ID')],
        'dropbox'  => ['label' => 'Dropbox',      'configured' => (bool) env('DROPBOX_APP_KEY')],
        'unsplash' => ['label' => 'Unsplash',     'configured' => (bool) env('UNSPLASH_ACCESS_KEY')],
        'giphy'    => ['label' => 'GIPHY',        'configured' => (bool) env('GIPHY_API_KEY')],
    ],

];
