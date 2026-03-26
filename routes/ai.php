<?php

use App\Http\Middleware\NormalizeMcpStreamableHttpAccept;
use App\Mcp\Servers\WaypostHttpServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/waypost', WaypostHttpServer::class)
    ->middleware([
        NormalizeMcpStreamableHttpAccept::class,
        'auth:sanctum',
        'throttle:120,1',
    ]);
