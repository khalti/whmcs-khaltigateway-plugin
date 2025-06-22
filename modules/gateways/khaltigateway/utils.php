<?php

/**
 * Khalti.com Payment Gateway WHMCS Module
 * 
 * @see https://docs.khalti.com/
 * 
 * @copyright Copyright (c) Khalti Private Limited
 * @author : @acpmasquerade for Khalti.com
 */

/**
 * Print_r inside a preformatted tag <pre>
 */
function ndie($data, $style = "")
{
    echo "<pre style='{$style}'>";
    print_r($data);
    echo "</pre>";
}

/**
 * Print_r inside a preformatted tag <pre> and Die
 */
function mdie($data)
{
    ndie($data);
    die();
}

/**
 * JSON Encode and Die
 */
function jdie()
{
    die(json_encode(array("idx" => null)));
}

/**
 * A simple template-like function to include PHP file with injected variables
 * @param  string $filename   File to include
 * @param  array  $_inc_vars  Variables to inject into the file
 */
function file_include_contents($filename, $_inc_vars = array())
{
    if (is_file($filename)) {
        ob_start();
        include $filename;
        return ob_get_clean();
    }
    return false;
}
