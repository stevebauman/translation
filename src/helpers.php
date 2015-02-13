<?php

/**
 * The Helpers.php file for Stevebauman/Translation
 */

if(config('translation::shorthand_enabled') || config('translation.shorthand_enabled'))
{
    /**
     * Shorthand function for translating text
     *
     * @param $text
     * @param array $data
     * @return mixed
     */
    function _t($text, $data = array())
    {
        return Translation::translate($text, $data);
    }
}