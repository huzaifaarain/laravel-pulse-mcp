<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests\App;

use HuzaifaArain\LaravelPulseMcp\Tests\TestCase;

final class ExampleFunctionalTest extends TestCase
{
    public function test_it_merges_the_package_config(): void
    {
        $this->assertFalse(config('pulse-mcp.enabled'));
        $this->assertSame('mcp/pulse', config('pulse-mcp.path'));
    }
}
