<?php
// +----------------------------------------------------------------------
// | 爱测评 ceping 站点后台路由
// +----------------------------------------------------------------------

use think\facade\Route;
use app\adminapi\middleware\AdminCheckRole;
use app\adminapi\middleware\AdminCheckToken;
use app\adminapi\middleware\AdminLog;

Route::group('ceping', function () {

    // 题库管理
    Route::get('test/lists', 'addon\ceping\app\adminapi\controller\Test@lists');
    Route::get('test/info', 'addon\ceping\app\adminapi\controller\Test@info');
    Route::put('test/online', 'addon\ceping\app\adminapi\controller\Test@setOnline');
    Route::put('test/hot', 'addon\ceping\app\adminapi\controller\Test@setHot');
    Route::put('test/sort', 'addon\ceping\app\adminapi\controller\Test@setSort');

    // 测评记录
    Route::get('record/lists', 'addon\ceping\app\adminapi\controller\Record@lists');
    Route::get('record/info', 'addon\ceping\app\adminapi\controller\Record@info');

    // AI/广告配置
    Route::get('config', 'addon\ceping\app\adminapi\controller\Config@index');
    Route::post('config', 'addon\ceping\app\adminapi\controller\Config@set');

})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
