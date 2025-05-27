<?php

namespace Webhub\SecureDbDump;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Webhub\SecureDbDump\Commands\SecureDbDumpCommand;

class SecureDbDumpServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('secure-db-dump')
            ->hasConfigFile()
            ->hasCommand(SecureDbDumpCommand::class);
    }
}
