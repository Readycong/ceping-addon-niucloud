<?php

use think\facade\Route;
use app\adminapi\middleware\AdminCheckRole;
use app\adminapi\middleware\AdminCheckToken;
use app\adminapi\middleware\AdminLog;

/**
 * {{TITLE}}
 */
Route::group('{{KEY}}', function () {

    /***************************************************** 示例条目 ****************************************************/
    //列表
    Route::get('item', 'addon\{{KEY}}\app\adminapi\controller\Entity@lists');
    //详情
    Route::get('item/:id', 'addon\{{KEY}}\app\adminapi\controller\Entity@info');
    //添加
    Route::post('item', 'addon\{{KEY}}\app\adminapi\controller\Entity@add');
    //编辑
    Route::put('item/:id', 'addon\{{KEY}}\app\adminapi\controller\Entity@edit');
    //删除
    Route::delete('item/:id', 'addon\{{KEY}}\app\adminapi\controller\Entity@del');

})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
