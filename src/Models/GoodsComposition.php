<?php

namespace Dadaodata\Iptv\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsComposition extends Model
{
    /**
     * 商品服务关联表
     *
     * @var string
     */
    protected $table = 'goods_composition';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['id', 'goods_id', 'resource_type', 'resource_id'];
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'created_at'   => 'datetime:Y-m-d H:i:s',
        'updated_at'   => 'datetime:Y-m-d H:i:s'
    ];
}
