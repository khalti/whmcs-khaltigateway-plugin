<?php 

function ndie($data){
    echo "<pre>";
    print_r($data);
    echo "</pre>";
}

function mdie($data){
    ndie($data);
    die();
}

function jdie(){
    die(json_encode(array("idx"=>null)));
}
