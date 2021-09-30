<?php

use Illuminate\Support\Facades\Route;
use Dadaodata\Iptv\Controllers\GoodsController;

Route::get('/iptv/goods/list', [GoodsController::class, 'lists'])->name('goods.lists');
Route::post('/iptv/goods/add', [GoodsController::class, 'add'])->name('goods.add');
Route::get('/iptv/goods/view', [GoodsController::class, 'view'])->name('goods.view');
