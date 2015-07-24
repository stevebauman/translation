<?php

namespace Stevebauman\Translation\Models;

use Illuminate\Database\Eloquent\Model;

class LocaleTranslation extends Model
{
    /**
     * The locale translations table.
     *
     * @var string
     */
    protected $table = 'locale_translations';

    /**
     * The fillable locale translation attributes.
     *
     * @var array
     */
    protected $fillable = [
        'locale_id',
        'translation_id',
        'translation',
    ];

    /**
     * The belongsTo locale relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function locale()
    {
        return $this->belongsTo(Locale::class, 'locale_id', 'id');
    }

    /**
     * The belongsTo parent relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(LocaleTranslation::class, 'translation_id');
    }

    /**
     * Returns true/false if the current translation
     * record is the parent translation.
     *
     * @return bool
     */
    public function isParent()
    {
        if (!$this->getAttribute('translation_id')) {
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
            return $this->query()->where('translation_id', $this->getKey())->get();
        }

        return false;
    }
}
