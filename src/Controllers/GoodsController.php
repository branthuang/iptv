<?php
namespace Dadaodata\Iptv\Controllers;

use App\Http\Controllers\Controller;
use Dadaodata\Iptv\Services\GoodsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GoodsController extends Controller
{
    protected $request;
    protected $service;

    public function __construct(Request $request,GoodsService $service)
    {
        $this->service = $service;
        $this->request = $request;
        parent::__construct($this->request);
    }

    /**
     * 后端：商品列表
     */
    public function lists()
    {
        $data = $this->service->lists($this->request);
        $this->responseWithNull(110001, 'success', $data);
    }

    /**
     * 新增商品
     */
    public function add()
    {
        $validate = $this->service->validateAdd($this->request);
        if ($validate !== true) {
            $this->responseWithNull(910001,$validate);
        }

        $data = $this->service->add($this->request);
        if (!$data)
        {
            $status_code = $this->service->getStatusCode();
            $message = $this->service->getMessage();
            $this->responseWithNull($status_code,$message);
        }else{
            $this->responseWithNull(110001, 'success');
        }
    }

    /**
     * 商品详情
     */
    public function view()
    {
        $validate = $this->service->validateId($this->request);
        if ($validate !== true) {
            $this->responseWithNull(910001,$validate);
        }
        $id = $this->request->id;
        $fields = ['id', 'tag', 'goods_type', 'title', 'cover','sort','introduce','content_id','purchase_introduce','is_need_buy','is_show','is_active',
            'created_by', 'created_at', 'updated_at', 'updated_by'];
        $data = $this->service->view($id, $fields); // 商品详情

        if (!$data){
            $this->responseWithNull(110002);
        }
        //商品关联服务
        $data->resource_id = $this->service->goodsComposition($id);

        $this->responseWithNull(110001, 'success', $data);
    }

    /**
     * 假删除
     */
    public function delete()
    {
        $validate = $this->service->validateId($this->request);
        if ($validate !== true) {
            $this->responseWithNull(910001,$validate);
        }
        $id = $this->request->id;
        $this->service->changeActive($id, 2); //1：正常； 2:已删除
        $this->service->clearComposition($id);//清理与套餐的关联
        $this->responseWithNull(110001, 'delete success');
    }

    /**
     * 更新商品
     */
    public function update()
    {
        $validate = $this->service->validateId($this->request);
        if ($validate !== true) {
            $this->responseWithNull(910001,$validate);
        }
        $this->service->update($this->request);
        $this->responseWithNull(110001,'success');
    }

    /**
     * 商品上下架
     */
    public function status()
    {
        $validate = $this->service->validateId($this->request);
        if ($validate !== true) {
            $this->responseWithNull(910001,$validate);
        }
        $id = $this->request->id;
        $is_show = isset($this->request->is_show) ? $this->request->is_show : 1;
        $this->service->changeStatus($id, $is_show); //展示状态(1.上架 2.下架)
        $this->responseWithNull(110001, 'success');
    }

    /**
     * 服务类型
     */
    public function goodsTypes()
    {
        $data = $this->service->goodsTypes();
        $this->responseWithNull(110001, 'success', $data);
    }

    /**
     * 指定服务类型下的资源列表
     */
    public function resourceLists()
    {
        $validate = $this->service->validateResourceType($this->request);
        if ($validate !== true) {
            $this->responseWithNull(910001,$validate);
        }
        $data = $this->service->resourceLists($this->request);
        $this->responseWithNull(110001, 'success', $data);
    }

    /**
     * 指定分类的商品列表
     */
    public function frontLists(){
        $validate = $this->service->validateResourceType($this->request);
        if ($validate !== true) {
            $this->responseWithNull(910001,$validate);
        }
        $data = $this->service->frontLists($this->request);
        $this->responseWithNull(110001, 'success', $data);
    }

    public function evaluationTested(){
        $data = $this->service->evaluationTested($this->request);
        if ($data){
            $this->responseWithNull(110001, 'success', $data);
        }else{
            $this->responseWithNull(110002, 'empty data');
        }
    }

    public function orderStatus(){
        $args = $this->request->all();
        $args_str = json_encode($args);
        $data = [
            'result' => $args_str,
            'created_at' => date('Y-m-d H:i:s')
        ];
        DB::table('order_status')->insert($data);
        $this->responseWithNull(110001, 'success',$data);
    }

    //商品鉴权订购id查询
    public function contentIds(){
        $data = $this->service->contentIds($this->request);
        $this->responseWithNull(110001, 'success', $data);
    }

    //商品鉴权
    public function iptvAuthorize(){
        //检查商品是否免费，免费商品不进行后续鉴权
        $tag = $this->request->tag;
        if (!$tag){
            $this->responseWithNull(910001,"the tag field is required");
        }
        $goods_data = $this->service->getGoodsInfo($tag);
        if ($goods_data) {
            $is_need_buy = $goods_data->is_need_buy;
            if($is_need_buy == 2){
                //1:需要购买流程;2.不需要
                $data = [
                    'status' => 1,
                    'message' => '本地已经设置为不需要购买',
                    'content_id' => $goods_data->content_id,
                    'purchase_introduce' => $goods_data->purchase_introduce,
                ];
                $this->responseWithNull(110001, 'success', $data);
            }
        }else{
            $this->responseWithNull(110002,"data is empty");
        }

        //商品移动端鉴权
        $validate = $this->service->validateAuthorize($this->request);
        if ($validate !== true) {
            $this->responseWithNull(910001,$validate);
        }
        $terminalId = $this->request->terminalId;
        $userId = $this->request->userId;
        $iptvToken = $this->request->iptvToken;
        $tag = $this->request->tag;
        $data = $this->service->authorize($terminalId,$userId,$iptvToken,$tag);
        if ($data){
            $this->responseWithNull(110001, 'success', $data);
        }else{
            $status_code = $this->service->getStatusCode();
            $message = $this->service->getMessage();
            $this->responseWithNull($status_code,$message);
        }
    }

    //商品订购完成，订购记录录入到本地
    public function addOrder(){
        $validate = $this->service->validateAddOrder($this->request);
        if ($validate !== true) {
            $this->responseWithNull(910001,$validate);
        }
        $data = $this->service->addOrder($this->request);
        if ($data){
            $this->responseWithNull(110001, 'success');
        }else{
            $status_code = $this->service->getStatusCode();
            $message = $this->service->getMessage();
            $this->responseWithNull($status_code,$message);
        }
    }
}
