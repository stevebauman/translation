<?php

namespace Stevebauman\Translation\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stevebauman\Translation\Facades\Translation;

class LocaleMiddleware
{
    /**
     * Sets the locale cookie on every request depending
     * on the locale supplied in the route prefix.
     *
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $request->cookies->set('locale', Translation::getRoutePrefix());

        return $next($request);
    }
}
