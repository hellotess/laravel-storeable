<?php

namespace Hellotess\Storable;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class StorableServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-storable');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Storable::class, fn () => new Storable());
        $this->app->bind('storable', Storable::class);
    }
}
