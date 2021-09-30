<?php
namespace Dadaodata\Iptv\Services;

use App\Models\AppFeatures;
use App\Models\Goods\Goods;
use App\Models\Goods\GoodsComposition;
use App\Services\Article\VideoService;
use App\Services\BaseService;
use App\Services\ChinaMobileBusinessService;
use App\Services\Evaluation\EvaluateService;
use App\Services\Evaluation\EvaluationService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\In;

class GoodsService extends BaseService
{
    /**后端商品列表
     * @param $request
     * @return mixed
     */
    public function lists($request)
    {
        $is_active = isset($request->is_active) ? $request->is_active : 1; //状态(1.正常2.已删除), 默认显示正常状态商品

        $field = ['id', 'tag', 'goods_type', 'title', 'cover','sort','is_show','is_active',
            'created_by', 'created_at', 'updated_at', 'updated_by'];
        $query = Goods::select($field)->where('is_active', '=', $is_active);;

        //展示状态(1.上架2.下架)
        $query->when(!empty($request->is_show), function ($q) use ($request) {
            $q->where('is_show', '=', $request->is_show);
        });
        //商品类型
        $query->when($request->goods_type > 0, function ($q) use ($request) {
            $q->where('goods_type', '=', $request->goods_type);
        });
        //商品名称
        $query->when(!empty($request->title), function ($q) use ($request) {
            $q->where('title', 'like', '%' . $request->title . '%');
        });

        $pagesize = empty($request->pagesize) ? 10 : $request->pagesize;
        $lists = $query->orderBy('id', 'desc')->paginate($pagesize)->toArray();
        $goods_ids = [];
        foreach ($lists['data'] as $val){
            $goods_ids[] = $val['id'];
        }
        $price_data = $this->getPriceByGoodsId($goods_ids);
        foreach ($lists['data'] as &$val){
            $val['price'] = isset($price_data[$val['id']]) ? $price_data[$val['id']] : [];
        }

        return $lists;
    }

    /**商品关联的套餐
     * @param $goods_ids
     */
    public function getPriceByGoodsId($goods_ids = []){
        $select = ['a.goods_id','a.price_id','b.title'];
        $result = DB::table('goods_price as a')
            ->leftJoin('price as b', 'a.price_id','=','b.id')
            ->whereIn('goods_id',$goods_ids)
            ->select($select)
            ->orderBy('a.goods_id')
            ->orderBy('b.id')
            ->get();
        $data = [];
        foreach ($result as $val){
            $goods_id = $val->goods_id;
            $price_id = $val->price_id;
            $title = $val->title;
            $data[$goods_id][] = [
              'price_id' => $price_id,
              'title' => $title
            ];
        }
        return $data;
    }

    /**
     *验证添加字段
     */
    public function validateAdd($request){
        $rules = [
            'goods_type' => 'required|integer',
            'title' => 'required',
        ];

        $validator = Validator::make($request->all(),$rules);
        if ($validator->fails()) {
            return $validator->errors()->first();
        }
        return true;
    }

    /**
     * 添加商品
     */
    public function add($request){
        DB::beginTransaction();
        try {
            $sort = isset($request->sort) ? $request->sort : 0;
            $is_show = isset($request->is_show) ? $request->is_show : 2;//展示状态(1.上架2.下架)， 默认：下架
            $is_active = isset($request->is_active) ? $request->is_active : 1;//状态(1.正常2.已删除), 默认：正常
            $created_by = $request->created_by;
            $updated_by = $request->updated_by;
            $updated_at = $created_at = date('Y-m-d H:i:s');
            $resource_id = isset($request->resource_id)? trim($request->resource_id) : 0;
            $goods_type = $request->goods_type;
            $tag = isset($request->tag) ? $request->tag : ''; //商品标签
            $introduce = isset($request->introduce) ? $request->introduce : ''; //介绍
            $cover = isset($request->cover) ? $request->cover : '';
            $content_id = $request->content_id;  //移动鉴权id
            $purchase_introduce = isset($request->purchase_introduce) ? $request->purchase_introduce : ''; //购买说明
            $is_need_buy = $request->is_need_buy; //是否需要订购

            $data = [
                'tag' => $tag,
                'goods_type' => $goods_type,
                'title' => $request->title,
                'cover' => $cover,
                'sort' => $sort,
                'is_show' => $is_show,
                'is_active' => $is_active,
                'introduce' => $introduce,
                'content_id' => $content_id,
                'purchase_introduce' => $purchase_introduce,
                'is_need_buy' => $is_need_buy,
                'created_by' => $created_by,
                'updated_by' => $updated_by,
                'created_at' => $created_at,
                'updated_at' => $updated_at
            ];
            //添加商品
            $goods_id = Goods::insertGetId($data);

            if($resource_id){
                //添加商品关联的服务
                $resource_id_arr = explode(',',$resource_id);
                foreach ($resource_id_arr as $r_id){
                    $data_composition = [
                        'goods_id' => $goods_id,
                        'goods_type' => $goods_type,
                        'resource_id' => $r_id
                    ];
                    GoodsComposition::insert($data_composition);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            //接收异常处理并回滚
            DB::rollBack();
            $this->status_code = 110005;
            $this->message = $e->getMessage();
            return false;
        }
        return true;
    }

    public function validateId($request){
        $rules = [
            'id' => 'required|integer',
        ];

        $validator = Validator::make($request->all(),$rules);
        if ($validator->fails()) {
            return $validator->errors()->first();
        }
        return true;
    }

    /**
     * 商品详情
     * @param $id
     * @param $fields
     */
    public function view($id , $fields=['id','title']){
        $data = Goods::where(['id'=> $id])->select($fields)->first();
        return $data;
    }

    /**商品相关联的服务
     * @param $id 商品id
     */
    public function goodsComposition($id){
        $data = GoodsComposition::where(['goods_id' => $id])->get();
        $resource_id_arr = [];
        foreach ($data as $val){
            $resource_id_arr[] = $val->resource_id;
        }
        return implode(',',$resource_id_arr);
    }

    /**修改指定商品状态
     * @param $id
     * @param $is_active 1：正常； 2:已删除
     */
    public function changeActive($id, $is_active){
        $where = ['id' => $id];
        $data = [
            'is_active' => $is_active,
        ];
        Goods::where($where)->update($data);
        return true;
    }

    //清理商品与套餐的关联
    public function clearComposition($goods_id){
        GoodsComposition::where(['goods_id'=>$goods_id])->delete();
        return true;
    }

    //更新商品信息
    public function update($request){
        $id = $request->id;
        $resource_id = isset($request->resource_id)? trim($request->resource_id) : 0;
        $valid_fields = [
            'tag','goods_type', 'title', 'cover','sort','is_show','introduce','content_id','purchase_introduce','is_need_buy',
            'updated_by'
        ];
        $data = $request->only($valid_fields);

        $data['updated_at'] = date('Y-m-d H:i:s');

        $check = $this->view($id,['id','goods_type']);
        if (!$check)
        {
            $this->status_code = 110002;
            return false;
        }
        try {
            Goods::where(['id' => $id])->update($data);

            //更新商品关联的服务
            if($resource_id){
                $resource_id_arr = explode(',',$resource_id);
                GoodsComposition::where(['goods_id'=>$id])->delete();
                foreach ($resource_id_arr as $r_id){
                    $data_composition = [
                        'goods_id' => $id,
                        'goods_type' => $check->goods_type,
                        'resource_id' => $r_id
                    ];
                    GoodsComposition::insert($data_composition);
                }
            }
        }Catch(\Exception $e){
            $this->status_code = 110005;
            $this->message = $e->getMessage();
            return false;
        }

        return true;
    }

    /**
     * 上下架
     * @param $is_show 展示状态(1.上架 2.下架)
     */
    public function changeStatus($id, $is_show=1){
        $where = ['id' => $id];
        $data = [
            'is_show' => $is_show,
        ];
        Goods::where($where)->update($data);
        return true;
    }

    /**
     * 服务类型： 与方法resourceLists()中类型一一对应
     */
    public function goodsTypes(){
        $data = [
            [
                'goods_type' => 1,
                'name' => '工具',
            ],
            [
                'goods_type' => 2,
                'name' => '测评',
            ],
            [
                'goods_type' => 3,
                'name' => '视频',
            ],
        ];
        return $data;
    }

    public function validateResourceType($request)
    {
        $rules = [
            'goods_type' => 'required|integer',
        ];

        $validator = Validator::make($request->all(),$rules);
        if ($validator->fails()) {
            return $validator->errors()->first();
        }
        return true;
    }


    /**
     * 指定服务类型下的资源列表
     * @param $resource_type 1:工具类； 2：测评
     * @return array
     */
    public function resourceLists($request){
        $goods_type = $request->goods_type; //指定服务(商品)类型
        $pagesize = $request->pagesize ? $request->pagesize : 20;
        $page = $request->page ? $request->page : 1;

        if ($goods_type == 1){
            //工具类
            $field = ['id','title'];
            $query = AppFeatures::select($field)->where('is_active', '=', 1);

            $data = $query
                ->orderBy('id', 'desc')
                ->paginate($pagesize)
                ->toArray();

        }elseif($goods_type == 2){
            //测评(iptv应用下)
            $app_key = config('client.iptvClientId');
            $result = App::make(EvaluateService::class)->adminLists($app_key, $pagesize,$page);
            if ($result['status_code'] == 11001){
                $data = $result['data'];
                if(!empty($data['data'])){
                    foreach ($data['data'] as $val){

                        $tmp_data[] = [
                            'id' => $val['id'],
                            'title' => $val['title'],
                            'cover' => $val['cover'],
                            'content' => $val['content'],
                        ];
                    }
                    $data['data'] = $tmp_data; //过滤字段
                }
            }else{
                $data = null;
            }

        }elseif($goods_type == 3){
            //视频
            $cid = App::make(VideoService::class)->getCareerCategoryId();
            $result = App::make(VideoService::class)->getPublishedVideoLists($cid);

            if($result){
                $data = $result->toArray();
                foreach ($data['data'] as $val){
                    $tmp_data[] = [
                        'id' => $val->id,
                        'title' => $val->title,
                        'cover' => $val->cover,
                        'content' => $val->description, //描述内容
                    ];
                }
                $data['data'] = $tmp_data; //过滤字段
            }
        }else{
            $data = null;
        }
        return $data;
    }

    public function frontLists($request){
        $goods_type = $request->goods_type; //商品类型
        $where = [
            'a.is_active' => 1, //正常
            'a.is_show' => 1, //上架
            'a.goods_type' => $goods_type , //商品类型
        ];

        $field = ['a.id', 'a.tag', 'a.title', 'a.cover', 'a.introduce','a.content_id','a.purchase_introduce','a.is_need_buy', 'b.resource_id'];
        $query = DB::table('goods as a')->leftJoin('goods_composition as b', 'a.id','=', 'b.goods_id')
                    ->select($field)->where($where);

        //商品名称
        $query->when(!empty($request->title), function ($q) use ($request) {
            $q->where('title', 'like', '%' . $request->title . '%');
        });

        $pagesize = empty($request->pagesize) ? 10 : $request->pagesize;
        $lists = $query->orderBy('sort', 'desc')->orderBy('id', 'desc')->paginate($pagesize)->toArray();

        if ($goods_type == 2 && $request->login_uuid){
            //测评类商品, 检查登录用户是否已经测评过
            $result = DB::table('evaluation_result')
                        ->where(['uuid' => $request->login_uuid])
                        ->select(['evaluation_id'])
                        ->distinct()
                        ->get();
            $tested_evaluation_ids = [];
            foreach ($result as $val){
                $tested_evaluation_ids[] = $val->evaluation_id;
            }
            foreach ($lists['data'] as $val){
                if (in_array($val->resource_id,$tested_evaluation_ids)){
                    $val->tested = 1;
                }else{
                    $val->tested = 0;
                }
            }
        }
        return $lists;
    }

    public function evaluationTested($request){
        $login_uuid = $request->login_uuid;
        //用户已经测评过的记录
        $e_result = DB::table('evaluation_result')->where(['uuid' => $login_uuid])->select(['evaluation_id'])->distinct()->get();
        $resource_id = [];
        foreach ($e_result as $val){
            $resource_id[] = $val->evaluation_id;
        }

        if (!empty($resource_id)){
            $goods_type = 2; //测评商品
            $where = [
                'a.is_active' => 1, //正常
                'a.goods_type' => $goods_type , //商品类型
            ];
            $pagesize = empty($request->pagesize) ? 10 : $request->pagesize;
            $field = ['a.id', 'a.tag', 'a.title', 'a.cover', 'a.introduce', 'b.resource_id'];
            $query = DB::table('goods as a')
                ->leftJoin('goods_composition as b', 'a.id','=', 'b.goods_id')
                ->select($field)
                ->where($where)
                ->whereIn('b.resource_id',$resource_id);
            $lists = $query->orderBy('sort', 'desc')->orderBy('id', 'desc')->paginate($pagesize)->toArray();
            return $lists;
        }else{
            return false;
        }
    }

    //商品鉴权订购id查询
    public function contentIds($request){
        $tags = $request->tags;

        $fields = [
            'tag','content_id','purchase_introduce','is_need_buy'
        ];
        $query = DB::table('goods as a')->select($fields)->where('tag','!=','');
        if ($tags){
            $tag_arr = explode(',',$tags);
            $query = $query->whereIn('tag',$tag_arr);
        }
        $result = $query->get();
        $data = [];
        foreach ($result as $val) {
            $data[$val->tag] = [
                'content_id' => $val->content_id,
                'purchase_introduce' => $val->purchase_introduce,
                'is_need_buy' => $val->is_need_buy,
            ];
        }
        return $data;
    }

    /**检查商品状态，是否免费
     * 不免费，返回false
     * 免费，返回免费信息
     * @param $tag
     * @return array|void
     */
    public function getGoodsInfo($tag,$is_active = 1){
        $fields = [
            'content_id','purchase_introduce','is_need_buy'
        ];
        $where = [
            'tag' => $tag,
            'is_active' => $is_active,
        ];
        $goods_data = DB::table('goods as a')->select($fields)->where($where)->first();
        return $goods_data;
    }

    public function validateAuthorize($request){
        $rules = [
            'terminalId' => 'required',
            'userId' => 'required',
            'iptvToken' => 'required',
            'tag' => 'required', //商品标识
        ];

        $validator = Validator::make($request->all(),$rules);
        if ($validator->fails()) {
            return $validator->errors()->first();
        }
        return true;
    }

    public function authorize($terminalId,$userId,$iptvToken,$tag){
        $goods_data = $this->getGoodsInfo($tag);

        if ($goods_data){
            $is_need_buy = $goods_data->is_need_buy;
            if($is_need_buy == 2){
                //1:需要购买流程;2.不需要
                $data = [
                    'status' => 1,
                    'message' => '本地已经设置为不需要购买',
                ];
            }else{
                $content_id = $goods_data->content_id;
                if (!$content_id){
                    $this->status_code = 963029;
                    return false;
                }
                $data = [
                    'userId' => $userId,
                    'terminalId' => $terminalId,
                    'token' => $iptvToken,
                    'copyRightId' => config('ddzx.copyRightId'),
                    'systemId' => config('ddzx.systemId'),
                    'contentId' => $content_id,
                    'consumeLocal' => config('ddzx.consumeLocal'),
                    'consumeScene' => config('ddzx.consumeScene'),
                    'consumeBehaviour' => config('ddzx.consumeBehaviour'),
                ];
                $cm_service = App::make(ChinaMobileBusinessService::class);
                $result = $cm_service->authorize($data);
                if ($result['result'] == 0){
                    //鉴权成功
                    $data = [
                        'status' => 1,
                        'message' => $result['resultDesc'],
                    ];
                }elseif ($result['result'] == 1 || $result['result'] == 20){
                    //请求订购
                    $data = [
                        'status' => 2,
                        'message' => $result['resultDesc'],
                    ];
                }else{
                    //其他情况
                    $data = [
                        'status' => $result['result'],
                        'message' => $result['resultDesc'],
                    ];
                }
            }
            $msg_data = [
                'content_id' => $goods_data->content_id,
                'purchase_introduce' => $goods_data->purchase_introduce,
            ];
            $data = array_merge($data,$msg_data);
            return $data;
        }else{
            $this->status_code = 963030;
            return false;
        }

    }

    public function validateAddOrder($request){
        $rules = [
            'terminalId' => 'required',
            'userId' => 'required',
            'content_id' => 'required',
        ];

        $validator = Validator::make($request->all(),$rules);
        if ($validator->fails()) {
            return $validator->errors()->first();
        }
        return true;
    }

    public function addOrder($request){
        $data = [
            'uuid' => $request->login_uuid,
            'terminal_id' => $request->terminalId,
            'user_id' => $request->userId,
            'content_id' => $request->content_id,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        DB::table('orders_iptv')->insert($data);
        return true;
    }
}
