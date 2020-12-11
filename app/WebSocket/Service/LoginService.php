<?php

namespace App\WebSocket\Service;

use Firebase\JWT\JWT;
/**
 * Author: 萧乐乐
 * Date: 2020-10-29
 * Time: 15:05
 */

class LoginService {

    /**
     * 解析jwt获取用户信息
     * @param $token
     * @return mixed
     * Author: 萧乐乐
     * Date: 2020-10-29
     * Time: 15:10
     */
    public static function getUserInfo($token)
    {
        $config = app()->make('config');
        $key = $config->get('server.route.jwt.key');
        // 1. 进行jwt验证
        $jwt = JWT::decode($token, $key, $config->get('server.route.jwt.alg'));

        return $jwt->data;
    }

    /**
     * 用户绑定管理员id
     * @param $webSocketServer
     * @param $uid
     * @return bool
     * Author: 萧乐乐
     * Date: 2020-11-02
     * Time: 15:18
     */
    public static function bingAdmin($webSocketServer, $uid){
        $bindKey = app()->make('config')->get('server.route.im.bind_key');
        $key = app()->make('config')->get('server.route.im.admin_key');
        $adminList = $webSocketServer->getRedis()->hKeys($key);
        //客服id
        if(empty($adminList))
            return false;
        //随机取id
        $adminId = $adminList[array_rand($adminList)];
        if($webSocketServer->getRedis()->zAdd($bindKey, $adminId, $uid))
            return $adminId;
        else
            return false;
    }
}
