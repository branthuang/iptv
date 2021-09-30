<?php

namespace Dadaodata\Iptv\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsPrice extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'goods_price';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['id', 'goods_id', 'price_id'];
}
