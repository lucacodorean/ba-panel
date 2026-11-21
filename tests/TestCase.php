<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Tests;

use Lucacodorean\BaPanel\BaPanelServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [BaPanelServiceProvider::class];
    }
}
