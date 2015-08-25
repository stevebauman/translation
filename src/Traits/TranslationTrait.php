<?php

namespace Stevebauman\Translation\Traits;

trait TranslationTrait
{
    /**
     * The belongsTo locale relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    abstract public function locale();

    /**
     * The belongsTo parent relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    abstract public function parent();

    /**
     * Returns true/false if the current translation
     * record is the parent translation.
     *
     * @return bool
     */
    public function isParent()
    {
        if (!$this->getAttribute($this->getForeignKey())) {
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
            return $this->query()->where($this->getForeignKey(), $this->getKey())->get();
        }

        return false;
    }
}
