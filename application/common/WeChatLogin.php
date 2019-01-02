<?php
/**
*微信授权登录 ThinkPHP5.1为例
*/
namespace common; //根据实际情况做修改

class WeChatLogin
{

    public $appid = "";
    public $appsecret = "";
    public $access_token = "";

//    构造函数，获取Access Token
    public function __construct($appid = NULL, $appsecret = NULL)
    {
        if ($appid) {
            $this->appid = $appid;
        }
        if ($appsecret) {
            $this->appsecret = $appsecret;
        }

        $this->lasttime = 1395049256;
        if (time() > $this->lasttime + 7200) {
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $this->appid . "&secret=" . $this->appsecret;
            $res = $this->https_request($url);
            $result = json_decode($res, true);
//            var_dump($result);die;
            //$this->access_token = $result["access_token"];
            $this->lasttime = time();
        }
    }

    //引导用户授权
    public function wxAuthUrl($wxAppId,$codeBackUrl){
        $url='https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$wxAppId.'&redirect_uri='.$codeBackUrl.'&response_type=code&scope=snsapi_userinfo&state=STATE#wechat_redirect';
        header("Location:" . $url);
    }
    //获取用户基本信息
    public function get_user_info($openid)
    {
        $url = "https://api.weixin.qq.com/sns/userinfo?access_token=".$openid['access_token']."&openid=".$openid['openid']."&lang=zh_CN";
        $res = $this->https_request($url);
        return json_decode($res, true);
    }

//https请求
    public function https_request($url, $data = null)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }
}
