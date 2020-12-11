<?php
// http的路由
use ImStart\Routes\Route;
Route::get('index', function(){
  return 'this is route index () tests';
});

Route::get('/index/dd', 'IndexController@dd');
