<?php 
/**
*微信公众号支付JSAPI
*/
class WechatPay extends Controller{
	/**
     * @title 订单支付
     * @description 接口说明
     * @module 订单
     * @method POST
     * @param @name:order_sn type:string require:1 default: other: desc:订单号
     * @param @name:paytype type:int require:1 default: other:1-微信支付,2-支付宝支付 desc:支付方式
     * @return paydata:支付数据
     */
    public function payOrder(Request $request)
    {
        $order_sn = $request->post("order_sn");
        $paytype = $request->post("paytype");
        $appPay = new JsApi();
        if (!$order_sn) {
            return $this->apiReturn(300, "订单号不能为空!");
        }
        $map = [
            "order_sn" => $order_sn,
        ];
        $field = "order_id,order_sn,paystatus,status,orderamount,session_id";
        $orderInfo = OrderModel::field($field)->where($map)->find();
        if (!$orderInfo) {
            return $this->apiReturn(300, "订单不存在!");
        }
        if($orderInfo->paystatus==OrderModel::PAYSTATUS_PAID){
            return $this->apiReturn(300, "该订单已支付!");
        }
        if($orderInfo->status==OrderModel::STATUS_CLOSE){
            return $this->apiReturn(300, "该订单已关闭交易!");
        }
        $userInfo = Db::name("user")->where("user_id",$orderInfo['session_id'])->find();
//        JSAPI支付需要有openID 这里的openID已保存在数据库，具体的获取openID可参考微信授权登录那里获取openID
        $order = [
            'out_trade_no' => $orderInfo->order_sn,
            'body' => '订单支付-'.$orderInfo->order_sn,
            'total_fee'      => $orderInfo['orderamount'],
            'openid'=>$userInfo['openid'],
            'user_id'=>$userInfo['user_id']
        ];
        try {
            $retdata = $appPay->actionWxheandle($order);
//            $retdata = $wxpay->GetJsApiParameters($order);
            if($retdata==null){
                return $this->apiReturn(300,"支付类型不正确");
            }
            return $this->apiReturn(200, "操作成功!",$retdata);

        } catch (Exception $ex) {
            return $this->apiReturn(300, $ex->getMessage());
        }


    }
    /**
     * 回调
     */
    public function actionNotifyurl()
    {
        $xml = file_get_contents("php://input");
        $log = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        $id = $log['out_trade_no'];  //获取单号
//        \Log::INFO($log);
        $this->output_log_file(json_encode($log));
        //这里修改状态
        if($log['result_code'] == "SUCCESS") {
            $a = 0;
            $this->output_log_file("=========================");
//            查看订单是否支付成功了
            $orderlog = Db::name('wxpay_log')->where('out_trade_no',$id)->find();
//            $orderlog = WxpayLogModel::where('out_trade_no',$id)->find();
            $this->output_log_file("+++++++++++++++++++++++++++");
            $this->output_log_file(json_encode($orderlog));
            if($orderlog){
                if($orderlog['result_code'] == "SUCCESS"){
                    $a = 0;
                    exit('SUCCESS');  //打死不能去掉
                }else{
                    $a = 1;
                }
            }else{
                $a = 1;
            }
            $this->output_log_file($a);
            if($a == 1){
                Db::startTrans();
                try {
//            插入支付记录
                    $log['createTime'] = time();
                    Db::name('wxpay_log')->insert($log);
//                修改订单状态、订单的库存、订单的销量 开始
//                    查询商品下单数量
                    $order = OrderModel::where('order_sn',$id)->find();
                    $this->output_log_file(json_encode($order));
//                    修改订单状态
                    $data['order_id'] = $order['order_id'];
                    $data['status'] = 2;
                    $data['paystatus'] = 2;
                    $data['paytime'] = time();
                    $this->output_log_file(json_encode($data));
                    if(!OrderModel::update($data)){
                        $this->output_log_file("订单状态修改失败");
                    }
                    $orderInfo = Db::name('order_item')->where("order_id",$order['order_id'])->select();
                    foreach ($orderInfo as $k=>$v){
//                        查询商品信息
                        $goodsInfo = GoodsModel::where('goods_id',$orderInfo[$k]['goods_id'])->find();
//                        修改商品的库存、销量
                        $goods['sales_volume'] = $orderInfo[$k]['quantity'] + $goodsInfo['sales_volume'];
                        $goods['stock'] = $goodsInfo['stock']-$orderInfo[$k]['quantity'];
                        $goods['goods_id'] = $goodsInfo['goods_id'];
                        if(!GoodsModel::update($goods)){
                            $this->output_log_file('商品信息修改失败');
                        }
                    }
//                    结束
                } catch (\Exception $e) {
                    $this->output_log_file($e);
                    Db::rollback();
                }
                Db::commit();
            }
        }
            exit('SUCCESS');  //打死不能去掉
    }
}