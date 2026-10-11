<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Offline field enumeration
    |--------------------------------------------------------------------------
    |
    | offline_session_hours: how long a field device may work without
    | contacting the server before it locks. Device tokens expire after
    | token_lifetime_days and must be renewed online.
    |
    */

    'offline' => [
        'offline_session_hours' => (int) env('LCCMS_OFFLINE_SESSION_HOURS', 72),
        'token_lifetime_days' => (int) env('LCCMS_DEVICE_TOKEN_DAYS', 30),
        'max_batch_operations' => (int) env('LCCMS_SYNC_MAX_BATCH', 100),
        'max_payload_kilobytes' => (int) env('LCCMS_SYNC_MAX_PAYLOAD_KB', 512),
        'max_devices_per_user' => (int) env('LCCMS_MAX_DEVICES_PER_USER', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    */

    'uploads' => [
        'complaint_max_images' => 5,
        'image_max_kilobytes' => (int) env('LCCMS_IMAGE_MAX_KB', 5120),
        'image_max_pixels' => (int) env('LCCMS_IMAGE_MAX_PIXELS', 40_000_000),
        'image_max_dimension' => (int) env('LCCMS_IMAGE_MAX_DIMENSION', 2400),
    ],

    /*
    |--------------------------------------------------------------------------
    | Strong authentication
    |--------------------------------------------------------------------------
    |
    | When enabled, two-factor authentication and passkeys are offered to every
    | user and are mandatory for super and national administrators. Disabled
    | by default until both work on the production domain (passkeys need
    | HTTPS and a matching relying-party domain).
    */

    'security' => [
        'strong_authentication' => (bool) env('LCCMS_STRONG_AUTHENTICATION', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Report exports
    |--------------------------------------------------------------------------
    |
    | Export files are private and deleted after the retention period. Each
    | user may have a limited number of exports running at once.
    */

    'exports' => [
        'retention_days' => (int) env('LCCMS_EXPORT_RETENTION_DAYS', 7),
        'max_pending_per_user' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Maps
    |--------------------------------------------------------------------------
    |
    | Tile provider for staff and public maps. Use a provider whose terms
    | permit your traffic; OpenStreetMap's public tiles are for light use only.
    */

    'maps' => [
        'tile_url' => env('LCCMS_MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => env('LCCMS_MAP_ATTRIBUTION', '&copy; OpenStreetMap contributors'),
        'max_zoom' => (int) env('LCCMS_MAP_MAX_ZOOM', 19),
        'max_points' => 5000,
    ],

];
