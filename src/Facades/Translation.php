<?php

namespace Stevebauman\Translation\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class Translation.
 */
class Translation extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'translation';
    }
}
