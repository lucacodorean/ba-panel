<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\BaPanelServiceProvider;

it('registers the service provider', function (): void {
    expect(app()->getProvider(BaPanelServiceProvider::class))
        ->toBeInstanceOf(BaPanelServiceProvider::class);
});
