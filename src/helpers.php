<?php

/**
 * The Helpers.php file for Stevebauman/Translation
 */

if(Config::get('translation::shorthand_enabled') || Config::get('translation.shorthand_enabled'))
{
    if( ! function_exists('_t'))
    {
        /**
         * Shorthand function for translating text
         *
         * @param string $text
         * @param array $replacements
         * @return string
         */
        function _t($text, $replacements = array())
        {
            return Translation::translate($text, $replacements);
        }
    }
}