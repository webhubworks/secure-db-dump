<?php

namespace Webhub\SecureDbDump\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Webhub\SecureDbDump\SecureDbDump
 */
class SecureDbDump extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Webhub\SecureDbDump\SecureDbDump::class;
    }
}
