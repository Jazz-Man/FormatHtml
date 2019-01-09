<?php

use JazzMan\FormatHtml\FormatHtml;

if (!function_exists('app_html_formatter')) {
    /**
     * @param      $input
     * @param bool $use_spaces
     * @param int  $indent_length
     *
     * @return string
     */
    function app_html_formatter($input, $use_spaces = true, $indent_length = 4)
    {
        $format = new FormatHtml();

        return $format->fix($input, $use_spaces, $indent_length);
    }
}
