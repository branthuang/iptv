<?php

namespace Dadaodata\Iptv\Models;

use Illuminate\Database\Eloquent\Model;

class Price extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'price';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['id', 'title', 'introduce','type','price','is_show',
        'created_by', 'created_at', 'updated_at', 'updated_by'];
}
