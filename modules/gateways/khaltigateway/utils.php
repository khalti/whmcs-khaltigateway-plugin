<?php 

/**
 * Print_r inside a preformatted tag <pre>
 */
function ndie($data, $style=""){
    echo "<pre style='{$style}'>";
    print_r($data);
    echo "</pre>";
}

/**
 * Print_r inside a preformatted tag <pre> and Die
 */
function mdie($data){
    ndie($data);
    die();
}

/**
 * JSON Encode and Die
 */
function jdie(){
    die(json_encode(array("idx"=>null)));
}

/**
 * A simple template like function to include php file with incjected variables
 * @param  string $file     File to include
 * @param  array  $vars     Variables to inject into the file
 */
function file_include_contents($filename, $_inc_vars=array()){
    if (is_file($filename)) {
        ob_start();
        include $filename;
        return ob_get_clean();
    }
    return false;
}