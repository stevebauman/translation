<?php

namespace Stevebauman\Translation\Contracts;

interface Client
{
    /**
     * Set source language.
     *
     * @param string $source Language code
     *
     * @return mixed
     */
    public function setSource($source = null);

    /**
     * Set target language.
     *
     * @param string $target Language code
     *
     * @return mixed
     */
    public function setTarget($target);

    /**
     * Translate the text.
     *
     * @param string $text
     *
     * @return mixed
     */
    public function translate($text);
}
