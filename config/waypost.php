<?php

return [

    /*
    |--------------------------------------------------------------------------
    | waypost.json — default X-Waypost-Source hint
    |--------------------------------------------------------------------------
    |
    | Included in the downloadable manifest so clients send X-Waypost-Source on
    | API calls. Must be a slug in App\Support\WaypostSource::allowedSources().
    |
    */

    'manifest_x_waypost_source' => env('WAYPOST_MANIFEST_X_WAYPOST_SOURCE', 'ai'),

    /*
    |--------------------------------------------------------------------------
    | Extra client / agent labels
    |--------------------------------------------------------------------------
    |
    | Comma-separated slugs (a-z, digits, underscore, hyphen) merged with the
    | built-in list for X-Waypost-Source, agent-events "agent", and changelog.
    |
    */

    'extra_client_sources' => array_values(array_filter(array_map(
        static fn (string $s): string => strtolower(trim($s)),
        explode(',', (string) env('WAYPOST_EXTRA_CLIENT_SOURCES', '')),
    ), static fn (string $s): bool => $s !== '')),

    /*
    |--------------------------------------------------------------------------
    | Public base URL (browser + MCP / waypost.json)
    |--------------------------------------------------------------------------
    |
    | Defaults to APP_URL. Set this when the URL editors and the MCP server must
    | call differs from APP_URL (uncommon). Example: local HTTPS with Valet/Herd
    | — set APP_URL=https://waypost.test (and optionally mirror it here).
    |
    */

    'public_url' => env('WAYPOST_PUBLIC_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | MCP HTTP request logging
    |--------------------------------------------------------------------------
    |
    | When true, each /mcp/waypost response is logged (no body or secrets) and
    | includes X-Waypost-Mcp-Request-Id for correlation with storage/logs.
    |
    */

    'mcp_log_requests' => filter_var(env('WAYPOST_MCP_LOG_REQUESTS', false), FILTER_VALIDATE_BOOL),

];
