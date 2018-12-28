<?php
/**
*微信授权登录控制器层，不同的方法对应不同的目录，这是不使用官方的sdk
*/
class WeChatLogins extends Controller
{
    public $appId = 'xxxxxxxxxxxxx'; // 公众号AppId
    public $appSecret = 'xxxxxxxxxxxxx'; // 公众号AppSecret
    public $starwalkUrl = 'http://xxxxxxxx?code=';//此url找前端要
    private $backurl = 'xxxxxxxxx/getToken';//这个是配置好的接口，对应getToken的接口
	//微信配置的地方，需要调用的接口，这里不需要做处理
    public function getToken()
    {
    }
    /**
     * 微信授权登录的入口，客户端调用此接口
     */
    public function login()
    {
    	$url = "xxxxxxxxxxxx/getTokens";//这是发起接口调用，微信调用此接口返回code
        $codeBackUrl = urlencode($url);
        $weixin = new WeChatLogin($this->appId, $this->appSecret);
        $weixin->wxAuthUrl($this->appId, $codeBackUrl);
    }
    //获取用户授权code
    public function getTokens()
    {
        $code = $_GET['code'];
        //获取到code以后，返回此url重定向到登录的接口 客户端会使用到此接口，显示登录的页面，后面加上code
        $starwalkUrl = $this->starwalkUrl.$code;
        $this->output_log_file($starwalkUrl);
        header("Location:" . $starwalkUrl);
    }
    /**
     * 根据code获取openID
     */
    public function getOpenID($code)
    {
        $wxAppId = $this->appId;
        $wxAppSecret = $this->appSecret;
        $weixin = new WeChatLogin($this->appId, $this->appSecret);
        $codeBackUrl = urlencode($this->backurl);
        //通过code换取网页授权access_token
        $codeUrl = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . $wxAppId . '&secret=' . $wxAppSecret . '&code=' . $code . '&grant_type=authorization_code';
        $res = $weixin->https_request($codeUrl);
        $this->output_log_file($res);
        $res = (json_decode($res, true));
        if (isset($res['openid'])) {
            return $res['openid'];
        } else {
            $weixin->wxAuthUrl($this->appId, $codeBackUrl);
        }
    }
    /**
    * 根据openID获取微信用户基本信息,保存到数据库，返回
    */
    public function getUserInfo($openid){
    	// 获取用户基本信息
        $wx_info = $weixin->get_user_info($openid);
        $wechat = json_encode($wx_info);
        // 用户的基本信息
        $this->output_log_file(json_encode($wx_info));
    }
    /**
     * @author Randolph
     * @date 2018/12/28
     * description 该接口中的日志文件需要手动创建，并修改对应的权限
     * @param $str
     */
    public function output_log_file($str)
    {
        $date = date('Y-m-d');
        if (PHP_OS == 'Linux') {
            $path =  "/var/log";
//        var_dump($path);
            $filename = $path . '/' . "randolph.log";
        } else {
        	//windows下面 根据需要可以打开调试
//            $path = DOCROOT . "logs\\$type\\$date";
////        var_dump($path);
//            $filename = $path . '\\' . $id . ".log";
        }
//        var_dump($path);
//        if (!is_dir($path)) {
//            mkdir($path, 0777, true);
//        }
        $files = fopen($filename, 'a');
//        var_dump($filename);
        fwrite($files, "\r\n".$str);
        fclose($files);
    }
}