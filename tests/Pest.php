<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Tests\Support\Symfony\SymfonyPanel;
use Lucacodorean\BaPanel\Tests\TestCase;

// The Laravel tests boot the package in an Orchestra Testbench application.
pest()->extend(TestCase::class)->in('Unit', 'Feature');

// The Symfony tests boot their own kernel. They do not use the Laravel test case.
pest()->in('Symfony')->afterEach(function (): void {
    SymfonyPanel::reset();
});
