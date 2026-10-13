<?php

namespace Tmc1807\LaravelQrisStaticToDinamis\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Tmc1807\LaravelQrisStaticToDinamis\QrisServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            QrisServiceProvider::class,
        ];
    }
}
