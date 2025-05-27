<?php

namespace Webhub\SecureDbDump\Commands;

use Illuminate\Console\Command;

class SecureDbDumpCommand extends Command
{
    public $signature = 'secure-db-dump';

    public $description = 'My command';

    public function handle(): int
    {
        $config = config('secure-db-dump');

        dd($config);

        return self::SUCCESS;
    }
}
