<?php

namespace Stevebauman\Translation\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Locale
 * @package Stevebauman\Translation\Models
 */
class Locale extends Model
{
    protected $table = 'locales';

    protected $fillable = array(
        'code',
        'lang_code',
        'name',
        'display_name',
    );

    /**
     * The hasMany translations relationship
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function translations()
    {
        return $this->hasMany('Stevebauman\Translation\Models\LocaleTranslation', 'locale_id', 'id');
    }
}