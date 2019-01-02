<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件
function apiReturn($status, $message = '', $data = null)
{
//        header("Access-Control-Allow-Origin:*");
    if (!is_numeric($status) || !is_string($message)) {
        return ["status" => "400", "message" => "参数错误"];
    }
    $res = array();
    $res['status'] = $status;
    $res['message'] = $message;
    if ($data) {
        $res['data'] = $data;
    }
    else{
        $res['data']=(object)array();
    }
    return $res;
}
/**
 * @author Randolph
 * @date 2018/12/28
 * description 该接口中的日志文件需要手动创建，并修改对应的权限
 * @param $str
 */
function output_log_file($str,$file = 'randolph')
{
    $date = date('Y-m-d');
    if (PHP_OS == 'Linux') {
        $path =  "/var/log";
//        var_dump($path);
        $filename = $path . '/' . $file . '.log';
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