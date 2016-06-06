<?php

namespace Stevebauman\Translation\Contracts;

use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

interface Translation
{
    /**
     * Constructor.
     *
     * @param Application $app
     */
    public function __construct(Application $app);

    /**
     * Returns the translation for the current locale.
     *
     * @param string $text
     * @param array  $replacements
     * @param string $toLocale
     *
     * @throws InvalidArgumentException
     *
     * @return string
     */
    public function translate($text = '', $replacements = [], $toLocale = '');

    /**
     * Retrieves the current app's default locale.
     *
     * @depreciated
     *
     * @return string
     */
    public function getAppLocale();

    /**
     * Returns a route prefix to automatically set a locale depending on the segment.
     *
     * @return null|string
     */
    public function getRoutePrefix();

    /**
     * Retrieves the current locale from the session. If a locale isn't
     * set then the default app locale is set as the current locale.
     *
     * @return string
     */
    public function getLocale();

    /**
     * Sets the default locale property.
     *
     * @param string $code
     */
    public function setLocale($code = '');

    /**
     * Returns the translation by the specified
     * text and the applications default locale.
     *
     * @param string $text
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function getDefaultTranslation($text);
}
