<?php

use app\api\middleware\ApiCheckToken;
use app\api\middleware\ApiLog;
use app\api\middleware\ApiChannel;
use think\facade\Route;

/**
 * {{TITLE}}
 */
Route::group('{{KEY}}', function () {

    /***************************************************** 示例条目 ****************************************************/
    Route::get('item', 'addon\{{KEY}}\app\api\controller\Entity@lists');
    Route::get('item/:id', 'addon\{{KEY}}\app\api\controller\Entity@info');

})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, false) //false表示不验证登录
    ->middleware(ApiLog::class);
