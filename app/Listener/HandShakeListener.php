<?php
namespace App\Listener;

use App\WebSocket\Service\LoginService;
use Firebase\JWT\JWT;

use ImStart\Event\Listener;
use ImStart\Server\WebSocket\WebSocketServer;
use Swoole\Http\Request;
use Swoole\Http\Response ;
use ImStart\Console\Input;

class HandShakeListener extends Listener
{
    protected $name = 'ws.hand';

    public function handler(WebSocketServer $server = null, Request  $request = null, Response $response = null)
    {
        // 这是接收websocket连接传递的参数
        $token = $request->header['sec-websocket-protocol'];
        $msgType = isset($request->header['msg_type']) ? $request->header['msg_type'] : false;      //获取消息类别
        // 进行用户的校验
        if (empty($token) || !($this->check($server, $token, $request->fd, $msgType))) {
            $response->end();
            return false;
        }

        // websocket的加密过程
        $this->handshake($request, $response);
    }

    protected function check(WebSocketServer $server, $token, $fd, $msgType = '')
    {
        try {
            $key = $this->app->make('config')->get('server.route.jwt.key');
            $userInfo = LoginService::getUserInfo($token);

            // 2. 存储连接信息到redis中
            if(!empty($userInfo->uid) && $msgType != 'transfer'){     //正常用户连接
                echo $userInfo->uid.PHP_EOL;
                $url = $userInfo->serverUrl;
                $key = $userInfo->type == 'admin' ? $key.'_admin' : $key;
                echo $key.PHP_EOL;
                $server->getRedis()->hset($key, $userInfo->uid, \json_encode([
                    'fd' => $fd,
                    'name' => $userInfo->name,
                    'type' => $userInfo->type,
                    'serverUrl' => $url
                ]));
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }

    }

    protected function handshake($request, $response)
    {
        // websocket握手连接算法验证
        $secWebSocketKey = $request->header['sec-websocket-key'];
        $patten          = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';
        if (0 === preg_match($patten, $secWebSocketKey) || 16 !== strlen(base64_decode($secWebSocketKey))) {
           $response->end();
           return false;
        }
        echo $request->header['sec-websocket-key'];
        $key = base64_encode(sha1(
           $request->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11',
           true
        ));

        $headers = [
           'Upgrade'               => 'websocket',
           'Connection'            => 'Upgrade',
           'Sec-WebSocket-Accept'  => $key,
           'Sec-WebSocket-Version' => '13',
        ];

        // WebSocket connection to 'ws://127.0.0.1:9502/'
        // failed: Error during WebSocket handshake:
        // Response must not include 'Sec-WebSocket-Protocol' header if not present in request: websocket
        if (isset($request->header['sec-websocket-protocol'])) {
           $headers['Sec-WebSocket-Protocol'] = $request->header['sec-websocket-protocol'];
        }

        foreach ($headers as $key => $val) {
           $response->header($key, $val);
        }

        $response->status(101);
        $response->end();
    }
}
