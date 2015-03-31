<?php

/**
 * The Helpers.php file for Stevebauman/Translation
 */

if(config('translation::shorthand_enabled') || config('translation.shorthand_enabled'))
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