<?php

declare(strict_types=1);

namespace HuzaifaArain\LaravelPulseMcp\Tests;

use HuzaifaArain\LaravelPulseMcp\LaravelPulseMcpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelPulseMcpServiceProvider::class,
        ];
    }
}
