<?php

namespace App\Support;

use InvalidArgumentException;

final class WaypostMcpApiPath
{
    /**
     * Path segment under `/api` only; query params belong in the tool's `query` argument.
     *
     * @throws InvalidArgumentException
     */
    public static function assertSafeRelativeApiPath(string $path): string
    {
        $normalized = str_starts_with($path, '/') ? $path : '/'.$path;

        if (str_contains($normalized, '..')) {
            throw new InvalidArgumentException("path must not contain '..'");
        }

        if (str_contains($normalized, '?') || str_contains($normalized, '#')) {
            throw new InvalidArgumentException('do not put ? or # in path; use the query object for query parameters');
        }

        if (strlen($normalized) > 1024) {
            throw new InvalidArgumentException('path too long');
        }

        if (! preg_match('/^\/[a-zA-Z0-9\/_.-]+$/', $normalized)) {
            throw new InvalidArgumentException(
                'path must be relative to /api using letters, digits, /, _, ., and - (e.g. /projects/1/tasks)',
            );
        }

        return $normalized;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function assertPathMatchesTokenScope(?int $scopedProjectId, string $path): void
    {
        if ($scopedProjectId === null) {
            return;
        }

        if (preg_match_all('#/projects/(\d+)#', $path, $matches)) {
            foreach ($matches[1] as $found) {
                if ((int) $found !== $scopedProjectId) {
                    throw new InvalidArgumentException(
                        "This token is limited to project {$scopedProjectId} only.",
                    );
                }
            }
        }
    }
}
