<?php

namespace Stevebauman\Translation\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class Translation
 * @package Stevebauman\Translation\Facades
 */
class Translation extends Facade
{
    protected static function getFacadeAccessor() { return 'translation'; }
}