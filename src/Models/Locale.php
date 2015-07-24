<?php

namespace Stevebauman\Translation\Models;

use Illuminate\Database\Eloquent\Model;

class Locale extends Model
{
    /**
     * The locales table.
     *
     * @var string
     */
    protected $table = 'locales';

    /**
     * The fillable locale attributes.
     *
     * @var array
     */
    protected $fillable = [
        'code',
        'lang_code',
        'name',
        'display_name',
    ];

    /**
     * The hasMany translations relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function translations()
    {
        return $this->hasMany(LocaleTranslation::class, 'locale_id', 'id');
    }
}
