<?php

namespace Stevebauman\Translation\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Translation
 * @package Stevebauman\Translation\Models
 */
class Translation extends Model {

    protected $table = 'translations';

    protected $fillable = array(
        'locale_id',
        'translation_id',
        'translation',
    );

    /**
     * The belongsTo locale relationship
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function locale()
    {
        return $this->belongsTo('Stevebauman\Translation\Models\Locale', 'id', 'locale_id');
    }

    /**
     * The belongsTo parent relationship
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo('Stevebauman\Translation\Models\Translation', 'translation_id');
    }

}