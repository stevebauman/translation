<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

if (Config::get('translation.shorthand_enabled')) {
    if (!function_exists('_t')) {
        /**
         * Shorthand function for translating text.
         *
         * @param string $text
         * @param array  $replacements
         * @param string $toLocale
         *
         * @return string
         */
        function _t($text, $replacements = [], $toLocale = '')
        {
            return App::make('translation')->translate($text, $replacements, $toLocale);
        }
    }
}
