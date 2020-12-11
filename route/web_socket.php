<?php
/*
一般控制
open
message
close
*/
use ImStart\Routes\Route;


Route::get('index', function (){
    return 'this is ws index () tests';
});
Route::wsController('/index', 'DemoController');
