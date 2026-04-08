<?php

namespace App\Mcp\Support;

use App\Support\WaypostMcpInternalApi;
use InvalidArgumentException;
use Laravel\Mcp\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class WaypostMcpApiResponse
{
    /**
     * @param  array<string, string|int|float|bool>  $query
     */
    public static function fromDispatch(string $method, string $path, array $query = [], ?array $jsonBody = null): Response
    {
        try {
            $symfony = WaypostMcpInternalApi::dispatch($method, $path, $query, $jsonBody);
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return Response::error('Failed to call the Waypost API.');
        }

        return self::fromSymfony($symfony);
    }

    public static function fromSymfony(SymfonyResponse $symfony): Response
    {
        $raw = $symfony->getContent();
        $status = $symfony->getStatusCode();

        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_object($decoded))) {
            $raw = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if ($status >= 400) {
            return Response::error($raw !== '' ? $raw : 'HTTP '.$status);
        }

        return Response::text($raw !== '' ? $raw : 'HTTP '.$status);
    }
}
