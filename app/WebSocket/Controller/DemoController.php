<?php

namespace App\WebSocket\Controller;

use App\WebSocket\Service\LoginService;
use ImStart\Server\Server;
use Swoole\WebSocket\Server as SwooleServer;

/**
 * Author: 萧乐乐
 * Date: 2020-10-23
 * Time: 16:37
 */

class DemoController {

    public function open(Server $webSocketServer,SwooleServer $server, $request)
    {
        //分配连接客服或用户
        $token = $request->header['sec-websocket-protocol'];
        $userInfo = LoginService::getUserInfo($token);
        if(empty($userInfo->uid))
            return false;
        $bindKey = app()->make('config')->get('server.route.im.bind_key');
        if($userInfo->type == 'admin'){
            //获取所有未绑定的用户，获取消息
            $userList = $webSocketServer->getRedis()->zRangeByScore($bindKey, 0, 0);
            if(!empty($userList)){
                foreach ($userList as $value){
                    $webSocketServer->getRedis()->zAdd($bindKey, $userInfo->uid, $value);
                }
            }
            $server->push($request->fd, json_encode([
                'method' => 'forwarding',
                'msg' => '登陆成功',
                'sourceId' => 0,
            ]));
        }else{
            //绑定已存在的客服，没有则为0
            $adminId = LoginService::bingAdmin($webSocketServer, $userInfo->uid);
            $server->push($request->fd, json_encode([
                'method' => 'forwarding',
                'msg' => '绑定管理员成功'.$adminId,
                'sourceId' => $adminId,
            ]));
        }


    }

    public function message()
    {
        echo 'this is ws demo controller message action'.PHP_EOL;
    }

    public function close()
    {
        echo 'this is ws demo controller close action'.PHP_EOL;
    }
}
