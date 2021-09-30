<?php

namespace Dadaodata\Iptv\Models;

use Illuminate\Database\Eloquent\Model;

class Goods extends Model
{
    /**
     * 与模型关联的数据表。
     *
     * @var string
     */
    protected $table = 'goods';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['id', 'tag', 'goods_type', 'title', 'cover', 'introduce','sort','is_show','content_id','purchase_introduce','is_need_buy','is_active',
        'created_by', 'created_at', 'updated_at', 'updated_by'];

    /**
     * 获取商品信息
     * @param  [type] $id [description]
     * @return [type]     [description]
     */
    public function getGoods($id,$fields=['id','title'])
    {
        $where = ['id'=>$id];
        $goods = $this->where($where)->select($fields)->first();
        if(!$goods)
        {
            return false;
        }
        return $goods;
    }

}
