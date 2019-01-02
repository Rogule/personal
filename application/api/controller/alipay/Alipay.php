<?php
namespace app\api\controller\alipay;

use think\Controller;
use think\Request;
require_once "../../../../vendor/lib/alipay/aop/AopClient.php";
require_once "../../../../vendor/lib/alipay/aop/request/AlipayTradeWapPayRequest.php";
class Alipay extends Controller{
    private $APPID;
    private $Method;
    private $CharSet;
    private $SignType;
    private $SellerID;
    private $Version;
    private $format;
    private $GetWayUrl;
    private $rsaStr;
    private $Publicstr;
    private $timestamp;
    private $ReturnUrl = "http://api.huike.jsojs.com/common/alipay/returnUrl";
    public function __construct()
    {
        $path = APP_PATH.'/config/AlipayConfig.php';
        $config = include ($path);
        $this->APPID = $config['h5']['APPID'];
        $this->Method = $config['h5']['Method'];
        $this->CharSet = $config['h5']['CharSet'];
        $this->SignType = $config['h5']['SignType'];
        $this->SellerID = $config['h5']['SellerID'];
        $this->Version = $config['h5']['Version'];
        $this->format = $config['h5']['format'];
        $this->GetWayUrl = $config['h5']['GetWayUrl'];
        $this->rsaStr = $config['h5']['rsaStr'];
        $this->Publicstr = $config['h5']['Publicstr'];
        $this->client = new \AopClient();
        $this->client->appId = $this->APPID;
        $this->seller_id = $this->SellerID;
        $str = $this->rsaStr;
        $this->client->rsaPrivateKeyFilePath = $str;
        $this->client->gatewayUrl = $this->GetWayUrl;
        $this->notifyUrl = config('app.alipay_h5_notify_url');;
        $this->timestamp = date("Y-m-d H:i:s");
    }
    /**
     * 生成签名
     * @param string $out_trade_no 订单号
     * @param string $subject 商品标题
     * @param string $total_amount 订单金额
     * @return string
     */
    public function generateSign($out_trade_no, $subject, $total_amount)
    {
        $str = array(
            "app_id" => $this->appId,
            "method" => $this->Method,
            "timestamp" => date("Y-m-d H:i:s"),
            "charset" => $this->CharSet,
            "format" => $this->format,
            'sign_type' => $this->SignType,
            'version' => $this->Version,
            'notify_url' => $this->notifyUrl,
            'biz_content' => json_encode(array(
                'seller_id' => $this->SellerID,
//                'product_code' => 'QUICK_MSECURITY_PAY',
                'total_amount' => $total_amount,
                'subject' => $subject,
                'timeout_express' => "90m",
                'out_trade_no' => $out_trade_no,
                'passback_params'
            ))
        );
        //签名
        $str['sign'] = $this->signature($str);
        $requestUrl = '';
        //系统参数放入GET请求串
        foreach ($str as $sysParamKey => $sysParamValue) {
            $requestUrl .= "$sysParamKey=" . urlencode($this->client->characet($sysParamValue, $this->client->postCharset)) . "&";
        }
        $requestUrl = substr($requestUrl, 0, -1);
        $product_code = 'QUICK_MSECURITY_PAY'; //	销售产品码，商家和支付宝签约的产品码，为固定值QUICK_MSECURITY_PAY
        $id = 1;
        $total_amount = $this->input->request('total_amount', 0); //订单总金额，单位为元，精确到小数点后两位，取值范围[0.01,100000000]

        $subject = $this->input->request('subject', ''); //商品的标题/交易标题/订单标题/订单关键字等。
        //支付订单号
        $arr2['payOrderNo'] = $out_trade_no;
        $arr2['payMoney'] = $total_amount;
        $arr2['userId'] = $id;
        $arr2['createTime'] = time();
        $arr2['payStatus'] = 1;
        $arr2['thirdNo'] = 1;
        db("pay")->data($arr2)->insert();

//        $rs = $this->createPayListObj()->insert($arr2);
        return $requestUrl;
    }
    /**
     * 签名
     * @param $str
     * @return string
     */
    public function signature($str)
    {
        return $this->client->generateSign($str);
    }
    public function createPayListObj()
    {
//        var_dump($this->notify_url);die;
//        $obj = new paylist_Model();
//        return $obj;
    }
    /**
     * 验签方法
     * @param $arr :验签支付宝返回的信息，使用支付宝公钥。
     * @return boolean
     */
    function check($arr)
    {
        $aop = new AopClient();
        $aop->alipayrsaPublicKey = $this->alipay_public_key;
        $result = $aop->rsaCheckV1($arr, $this->alipay_public_key);
        return $result;
    }
    /**
     * 发起订单
     * @param float $totalFee 收款总费用 单位元
     * @param string $outTradeNo 唯一的订单号
     * @param string $orderName 订单名称
     * @param string $notifyUrl 支付结果通知url 不要有问号
     * @param string $timestamp 订单发起时间
     * @return array
     */
    public function payUrlAlipayDBK($orderNo)
    {
        //业务参数
        $where['orderNo'] = $orderNo;
        $resorder = db("order")->where($where)->find();
        $id = $resorder['userId'];
        $total_amount = $resorder['payableMoney'];
        $subject = $resorder['userName'];
        //请求参数
        $requestConfigs = array(
            'out_trade_no'=>$orderNo,
            'product_code'=>'QUICK_WAP_WAY',
            'total_amount'=>$total_amount, //单位 元
            'subject'=>$subject,  //订单标题
        );
        $commonConfigs = array(
            //公共参数
            'app_id' => $this->appId,
            'method' => $this->Method,             //接口名称
            'format' => 'JSON',
            'return_url' => $this->ReturnUrl,
            'charset'=>$this->CharSet,
            'sign_type'=>$this->SignType,
            'timestamp'=>date('Y-m-d H:i:s'),
            'version'=>'1.0',
            'notify_url' => $this->notifyUrl,
            'biz_content'=>json_encode($requestConfigs),
        );
        $commonConfigs["sign"] = $this->generateSign($commonConfigs, $commonConfigs['sign_type']);
        return $commonConfigs;
    }

    /**
     * 获取支付url
     * @param $total_amount
     * @param $id
     * @param $ord_no
     */
//    h5支付，返回的数据和APP支付的结果不同
    public function payUrlAlipay($orderNo)
    {
        //业务参数
        $where['orderNo'] = $orderNo;
        $resorder = db("order")->where($where)->find();
        $id = $resorder['userId'];
        $total_amount = $resorder['payableMoney'];
//        $subject = $resorder['userName'];
        $subject = '订单编号：'.$orderNo;
        //公共参数
        $result = $this->basePay($id, $subject, $total_amount, $orderNo,$resorder['userId']);
        $this->output_log_file("\r\n".json_encode($result));
        return resultArray(['data' => $result]);
//        echo json_encode($result);
    }

    public function basePay($id, $subject, $total_amount, $ord_no, $userId,$type = 'd', $balance = 0, $points = 0)
    {
        $product_code = 'QUICK_MSECURITY_PAY'; //	销售产品码，商家和支付宝签约的产品码，为固定值QUICK_MSECURITY_PAY

        //支付订单号
//        $out_trade_no = time() . $this->code(4);
        $arr2['out_trade_no'] = $ord_no;
        $arr2['product_code'] = $product_code;
        $arr2['subject'] = $subject;
        $arr2['timeout_express'] = '30m';
        $arr2['total_amount'] = $total_amount;

        $biz_content = $this->createBiz_content($arr2);

        $arr['app_id'] = $this->APPID;
        $arr['biz_content'] = $biz_content;
        $arr['charset'] = $this->CharSet;
        $arr['format'] = "json";
        $arr['method'] = $this->Method;
        $arr['notify_url'] = $this->notifyUrl;
        $arr['sign_type'] = $this->SignType;
        $arr['timestamp'] = $this->timestamp;
        $arr['version'] = "1.0";
        $arr['out_trade_no'] = $ord_no;
        $arr['product_code'] = $product_code;
        $arr['subject'] = $subject;
        $arr['timeout_express'] = '30m';
        $arr['total_amount'] = $total_amount;
        unset($arr2['product_code']);
        unset($arr2['timeout_express']);

        $arr3['orderNo'] = $ord_no;
        $arr3['payMoney'] = $total_amount;
        $arr3['payType'] = 1;
        $arr3['payStatus'] = 1;
        $arr3['thirdNo'] = 1;
        $arr3['userId'] = $userId;
        $arr3['createTime'] = time();
        db("pay")->data($arr3)->insert();
        $c = new \AopClient();
        $c->gatewayUrl = $this->GetWayUrl;
        $c->appId = $this->APPID;

        $c->rsaPrivateKey = $this->client->rsaPrivateKeyFilePath;
        $c->alipayPublicKey = $this->Publicstr;
        $c->apiVersion = $this->Version;
        $c->signType = $this->SignType;
        $c->postCharset = $this->CharSet;
        $c->format = $this->format;
        $request = new \AlipayTradeWapPayRequest();
        $request->setBizContent($biz_content);
        $request ->setNotifyUrl($this->notifyUrl);
        $sign = $c->generateSign($arr, $this->SignType);
        $arr['return_url'] = $this->ReturnUrl;
        $arr['sign'] = $sign;
        $from=$c->pageExecute($request);
        return $from;
    }

    protected function createBiz_content($para)
    {
        $arg = "{";
//        $arg = "";
        while (list ($key, $val) = each($para)) {
            $arg .= '"' . $key . '"' . ":" . '"' . $val . '"' . ",";
        }

        $arg = substr($arg, 0, count($arg) - 2);

        $arg .= "}";
        return $arg;
    }

    public function test()
    {
        $str = $this->input->request('a');
        echo $str ? $str : 'ha';
    }

    /**
     * 数组组成url
     * @param $para :url参数
     * @return string
     *
     */

    protected function createLinkstring($para, $encode = true)
    {
        $arg = "";
        while (list ($key, $val) = each($para)) {
            if ($encode)
                $val = urlencode($val);
            $arg .= $key . "=" . $val . "&";
        }
        //去掉最后一个&字符
        $arg = substr($arg, 0, count($arg) - 2);

        //如果存在转义字符，那么去掉转义
        if (get_magic_quotes_gpc()) {
            $arg = stripslashes($arg);
        }
        return $arg;
    }

    /**
     * 异步验证
     */
    public function checkRsaSign()
    {

        $this->output_log_file("H5支付宝调用一步验证");
//        $this->output_log('123','支付宝调用一步验证');
        $param = $_REQUEST;
        $this->output_log_file("\r\n".json_encode($param));
        $aop = new \AopClient();
        //支付宝公钥
        $whereStatus['orderNo'] = $param['out_trade_no'];
        $checkOrder = db("pay")->where($whereStatus)->find();
        if ($checkOrder['payStatus'] == 3) {
            $this->output_result_alipay('0', '验证成功，已返回支付宝服务器！', true);
        }else {
            $this->output_log_file("支付宝调用二步验证");
            $aop->alipayrsaPublicKey = $this->alipay_public_key;
            $this->output_log_file("支付宝调用三步验证");
            $result = $aop->rsaCheckV1($param, $this->Publicstr, $this->SignType);
            $this->output_log_file("支付宝调用四步验证");
            $this->output_log_file($result);
            if ($result) {
                $this->output_log_file("支付宝调用⑤步验证");
                //插入trade_status验证
                $this->output_log_file($param['trade_status']);
                if ($param['trade_status'] != 'TRADE_SUCCESS')
                    $this->output_result_alipay('1', '支付状态出现问题。');

                $this->output_log_file("支付宝调用6步验证");
                //插入app_id验证
                if ($param['app_id'] != $this->APPID)
                    $this->output_result_alipay('4', 'app_id验证出现问题。');

                $this->output_log_file("支付宝调用7步验证");
                //插入sell_id验证
                $this->output_log_file($param['seller_id']);
                if ($param['seller_id'] != $this->SellerID)
                    $this->output_result_alipay('2', 'seller_id验证出现问题。');

                $this->output_log_file("支付宝调用8步验证");
//            项目逻辑处理开始
//            支付状态修改
                $this->output_log_file("order调用成功");
                $arrStatus['payStatus'] = 3;
                $arrStatus['thirdOrderNo'] = $param['trade_no'];
                $arrStatus['updateTime'] = time();
//                $whereStatus['orderNo'] = $param['out_trade_no'];
                $orderid = db("order")->where($whereStatus)->find();
                //插入total_amount验证
                if (!$orderid)
                    $this->output_result_alipay('5', '查无此单。');

//            $total_amount = $orderid['payableMoney'];
//
//            if ($param['total_amount'] != $total_amount)
//                $this->output_result_alipay('6', '金额验证出现问题。');

                $this->output_log_file(json_encode($orderid));
                $res = Order::payOrder(['orderNo' => $param['out_trade_no'], 'payMoney' => $param['total_amount'], 'payType' => 1]);
                $this->output_log_file(json_encode($res));
                db("pay")->where($whereStatus)->update($arrStatus);
                /**
                 * 支付记录新增
                 */
                $this->output_log_file("\r\n" . "H5支付宝调用记录新增开始");
                db("log_alipay")->data($param)->insert();
                $this->output_log_file("\r\n" . "支付宝调用记录新增结束");
//            项目逻辑处理结束
                $this->output_result_alipay('0', '验证成功，已返回支付宝服务器！', true);
            } else {
                $arrStatus['payStatus'] = 2;
                $arrStatus['thirdOrderNo'] = $param['trade_no'];
                $arrStatus['updateTime'] = $this->timestamp;
                $whereStatus['orderNo'] = $param['out_trade_no'];
                db("pay")->where($whereStatus)->update($arrStatus);
                $this->output_result_alipay('3', '支付验证出错。');
            }
        }
    }


    public function gateWay()
    {
        $this->input->request('resultStatus');
    }

    /**
     * 同步验证
     */

    public function returnUrl()
    {

        $params = $this->input->request();

        $aop = new \AopClient();
        //支付宝公钥

        if ($params['resultStatus'] != '9000') {
            $this->output_error(2001);
        }

//        echo "<pre>";
//        print_r(json_encode($params));exit();
        $param = array();
        if (isset($params['result'])) {
            $temp = json_decode($params['result'], true);
            if (isset($temp['alipay_trade_app_pay_response'])) {
                $param = $temp['alipay_trade_app_pay_response'];
            } else
                $this->output_error(2003);

        } else
            $this->output_error(2002);


        //插入app_id验证
        if ($param['app_id'] != $this->appId)
            $this->output_result_alipay('4', 'app_id验证出现问题。');

        //插入sell_id验证
        if ($param['seller_id'] != $this->seller_id)
            $this->output_result_alipay('2', 'seller_id验证出现问题。');
        $order = new Order();
        $this->output_log_file("order调用成功");
//            $arrStatus['orderStatus'] = 2;
        $whereStatus['orderNo'] = $param['out_trade_no'];
        $orderid = db("order")->where($whereStatus)->find();
        //插入total_amount验证
        if (!$orderid)
            $this->output_result_alipay('5', '查无此单。');

        $total_amount = $orderid['payableMoney'];

        if ($param['total_amount'] != $total_amount)
            $this->output_result_alipay('6', '金额验证出现问题。');

        return [
            'message' => '支付成功'
        ];

    }

    protected function output_result_alipay($error_num, $errinfo = '', $result = false)
    {
        if ($errinfo) $str = json_encode(array('err_code' => $error_num . '', 'err_info' => $errinfo));
        else $str = json_encode(array('errcode' => $error_num . ''));

//        $str = preg_replace("/\\\u([0-9a-f]{4})/ie", "mb_convert_encoding(pack('V', hexdec('U$1')),'UTF-8','UCS-4LE')", $str);

//        $this->output_log_file($str);

        if ($result)
            echo 'success';
        else
            echo "fail,error_num:" . $error_num;

        exit;
    }

}