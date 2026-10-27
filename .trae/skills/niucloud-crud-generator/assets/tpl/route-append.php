    /***************************************************** {{COMMENT}} ****************************************************/
    // 在对应 Route::group('{{KEY}}', ...) 闭包内追加以下行（不要新建 group）

    Route::get('{{MODULE}}', 'addon\{{KEY}}\app\adminapi\controller\{{MODULE}}\{{MODULE_PASCAL}}@lists');
    Route::get('{{MODULE}}/:id', 'addon\{{KEY}}\app\adminapi\controller\{{MODULE}}\{{MODULE_PASCAL}}@info');
    Route::post('{{MODULE}}', 'addon\{{KEY}}\app\adminapi\controller\{{MODULE}}\{{MODULE_PASCAL}}@add');
    Route::put('{{MODULE}}/:id', 'addon\{{KEY}}\app\adminapi\controller\{{MODULE}}\{{MODULE_PASCAL}}@edit');
    Route::delete('{{MODULE}}/:id', 'addon\{{KEY}}\app\adminapi\controller\{{MODULE}}\{{MODULE_PASCAL}}@del');
