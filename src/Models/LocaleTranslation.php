<?php

namespace Stevebauman\Translation\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Translation.
 */
class LocaleTranslation extends Model
{
    protected $table = 'locale_translations';

    protected $fillable = array(
        'locale_id',
        'translation_id',
        'translation',
    );

    /**
     * The belongsTo locale relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function locale()
    {
        return $this->belongsTo('Stevebauman\Translation\Models\Locale', 'locale_id', 'id');
    }

    /**
     * The belongsTo parent relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo('Stevebauman\Translation\Models\LocaleTranslation', 'translation_id');
    }

    /**
     * Returns true/false if the current translation
     * record is the parent translation.
     *
     * @return bool
     */
    public function isParent()
    {
        if (!$this->translation_id) {
            return true;
        }

        return false;
    }

    /**
     * Returns the translations of the current
     * translation record.
     *
     * @return mixed
     */
    public function getTranslations()
    {
        if ($this->isParent()) {
            return $this->where('translation_id', $this->id)->get();
        }

        return false;
    }
}
