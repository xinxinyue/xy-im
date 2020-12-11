<?php
namespace App\Listener;

use ImStart\Server\WebSocket\Connections;
use ImStart\Server\WebSocket\WebSocketServer;
use ImStart\Event\Listener;
use Firebase\JWT\JWT;

class WSCloseListener extends Listener
{
    protected $name = 'ws.close';

    public function handler(WebSocketServer $swoStarServer = null, $swooleServer  = null, $fd = null)
    {
        // 获取删除的用户 -> jwt -> token  -> header -> request
        $request = Connections::get($fd)['request'];
        $token = $request->header['sec-websocket-protocol'];
        $msgType = isset($request->header['msg_type']) ? $request->header['msg_type'] : false;      //获取消息类别

        if($msgType != 'transfer'){
            echo $msgType.PHP_EOL;
            //非转发消息则删除用户连接
            $config = $this->app->make('config');
            $key = $config->get('server.route.jwt.key');
            // 1. 进行jwt验证
            $jwt = JWT::decode($token, $key, $config->get('server.route.jwt.alg'));
            // 删除登陆状态--不为0则为用户连接
            if(!empty($jwt->data->uid)) {
                echo 'close'.$jwt->data->uid.PHP_EOL;
                $swoStarServer->getRedis()->hDel($key, $jwt->data->uid);
            }
        }

    }


}
