<?php

namespace Tests\Unit;

use App\Support\WaypostMcpApiPath;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class WaypostMcpApiPathTest extends TestCase
{
    public function test_normalizes_leading_slash(): void
    {
        $this->assertSame('/projects/1', WaypostMcpApiPath::assertSafeRelativeApiPath('projects/1'));
    }

    public function test_rejects_parent_segments(): void
    {
        $this->expectException(InvalidArgumentException::class);
        WaypostMcpApiPath::assertSafeRelativeApiPath('/projects/../1');
    }

    public function test_rejects_query_in_path(): void
    {
        $this->expectException(InvalidArgumentException::class);
        WaypostMcpApiPath::assertSafeRelativeApiPath('/projects?x=1');
    }

    public function test_scope_allows_matching_project(): void
    {
        WaypostMcpApiPath::assertPathMatchesTokenScope(5, '/projects/5/tasks');
        $this->addToAssertionCount(1);
    }

    public function test_scope_rejects_other_project(): void
    {
        $this->expectException(InvalidArgumentException::class);
        WaypostMcpApiPath::assertPathMatchesTokenScope(5, '/projects/6');
    }
}
