<?php

namespace Stevebauman\Translation\Traits;

trait LocaleTrait
{
    /**
     * The hasMany translations relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    abstract public function translations();
}
