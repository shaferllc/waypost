<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | MCP Streamable HTTP clients that run inside a browser context (or some
    | Electron webviews) send OPTIONS preflight and need Access-Control-Expose-Headers
    | for MCP-Session-Id on JSON responses. Without mcp/* here, HandleCors skips
    | these routes entirely (default paths are only api/*).
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'mcp/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['MCP-Session-Id'],

    'max_age' => 0,

    'supports_credentials' => false,

];
