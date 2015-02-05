<?php

/**
 * The Helpers.php file for Stevebauman/Translation
 */

if(config('translation::shorthand_enabled'))
{
    function _t($text, $data = array())
    {
        return Translation::translate($text, $data);
    }
}