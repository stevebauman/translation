<?php

namespace Stevebauman\Translation\Facades;

use Illuminate\Support\Facades\Facade;

class Translation extends Facade {

    protected static function getFacadeAccessor() { return 'translation'; }

}