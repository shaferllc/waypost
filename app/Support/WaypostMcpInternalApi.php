<?php

namespace App\Support;

use App\Http\Middleware\EnforceProjectScopedSanctumToken;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class WaypostMcpInternalApi
{
    /**
     * Dispatch a subrequest to this app's `/api` routes using the same Bearer token as the MCP request.
     *
     * @param  array<string, string|int|float|bool>  $query
     * @param  array<string, mixed>|null  $jsonBody  For POST/PATCH only
     */
    public static function dispatch(string $method, string $path, array $query = [], ?array $jsonBody = null): SymfonyResponse
    {
        $method = strtoupper($method);
        $path = WaypostMcpApiPath::assertSafeRelativeApiPath($path);

        $user = Auth::user();
        if ($user === null) {
            throw new \RuntimeException('Not authenticated.');
        }

        $scoped = EnforceProjectScopedSanctumToken::scopedProjectIdFromToken($user->currentAccessToken());
        WaypostMcpApiPath::assertPathMatchesTokenScope($scoped, $path);

        $uri = '/api'.$path;
        if ($query !== []) {
            $uri .= (str_contains($uri, '?') ? '&' : '?').http_build_query($query);
        }

        $server = [
            'REQUEST_URI' => $uri,
            'REQUEST_METHOD' => $method,
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WAYPOST_SOURCE' => 'mcp',
        ];

        $auth = request()->header('Authorization');
        if (is_string($auth) && $auth !== '') {
            $server['HTTP_AUTHORIZATION'] = $auth;
        }

        $content = null;
        if (in_array($method, ['POST', 'PATCH'], true) && $jsonBody !== null) {
            $server['CONTENT_TYPE'] = 'application/json';
            $content = json_encode($jsonBody, JSON_THROW_ON_ERROR);
        }

        $sub = Request::create($uri, $method, [], [], [], $server, $content);

        $kernel = app(Kernel::class);
        $response = $kernel->handle($sub);
        $kernel->terminate($sub, $response);

        return $response;
    }
}
