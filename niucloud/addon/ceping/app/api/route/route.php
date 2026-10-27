<?php
// +----------------------------------------------------------------------
// | 爱测评 ceping 前台路由
// +----------------------------------------------------------------------

use app\api\middleware\ApiCheckToken;
use app\api\middleware\ApiLog;
use app\api\middleware\ApiChannel;
use think\facade\Route;

// 免登录（浏览类；小程序端会先静默登录）
Route::group('ceping', function () {

    // 开发联调：模拟openid登录
    Route::post('dev/mock-login', 'addon\ceping\app\api\controller\Dev@mockLogin');

    // 首页与分类
    Route::get('home', 'addon\ceping\app\api\controller\Home@index');
    Route::get('categories', 'addon\ceping\app\api\controller\Home@categories');
    Route::get('category', 'addon\ceping\app\api\controller\Test@category');
    Route::get('search', 'addon\ceping\app\api\controller\Test@search');

    // 介绍与试卷
    Route::get('intro', 'addon\ceping\app\api\controller\Test@intro');
    Route::get('paper', 'addon\ceping\app\api\controller\Test@paper');

    // 双人邀请查看（未登录可浏览状态）
    Route::get('partner/join', 'addon\ceping\app\api\controller\Partner@join');

})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, false)
    ->middleware(ApiLog::class);

// 需登录（作答/记录/资料/广告/AI/双人发起）
Route::group('ceping', function () {

    // 作答提交
    Route::post('submit', 'addon\ceping\app\api\controller\Record@submit');
    Route::get('records', 'addon\ceping\app\api\controller\Record@records');
    Route::get('record', 'addon\ceping\app\api\controller\Record@detail');
    Route::post('record/delete', 'addon\ceping\app\api\controller\Record@delete');
    Route::post('records/clear', 'addon\ceping\app\api\controller\Record@clear');

    // 站内资料
    Route::get('profile', 'addon\ceping\app\api\controller\Profile@info');
    Route::post('profile/update', 'addon\ceping\app\api\controller\Profile@update');

    // 激励视频解锁
    Route::post('ad/verify', 'addon\ceping\app\api\controller\Ad@verify');

    // AI深度报告
    Route::post('ai/request', 'addon\ceping\app\api\controller\Ai@request');
    Route::get('ai/poll', 'addon\ceping\app\api\controller\Ai@poll');

    // 双人契合度
    Route::post('partner/invite', 'addon\ceping\app\api\controller\Partner@invite');
    Route::post('partner/submit', 'addon\ceping\app\api\controller\Partner@submit');

})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, true)
    ->middleware(ApiLog::class);
