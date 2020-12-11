<?php
namespace App\Listener;

use App\WebSocket\Service\LoginService;
use Firebase\JWT\JWT;
use ImStart\Server\WebSocket\Connections;
use ImStart\Server\WebSocket\WebSocketServer;
use ImStart\Event\Listener;

use Swoole\Coroutine\Http\Client;

class WSMessageFrontListener extends Listener
{
    protected $name = 'ws.message.front';
    protected $userInfo = array();


    public function handler(WebSocketServer $swoStarServer = null, $swooleServer  = null, $frame = null)
    {
        /*
            消息的格式 -》
            {
                'method' => //执行操作
                'msg' => 消息,
            }
         */
        //获取用户信息
        $config = $this->app->make('config');
        $key = $config->get('server.route.jwt.key');
        $request = Connections::get($frame->fd)['request'];
        //获取jwt信息
        $token = $request->header['sec-websocket-protocol'];
        $jwt = JWT::decode($token, $key, $config->get('server.route.jwt.alg'));
        $this->userInfo = $jwt->data;
        $data = \json_decode($frame->data, true);
        $this->{$data['method']}($swoStarServer, $swooleServer ,$data, $frame->fd);
    }
    /**
     * 服务器广播
     *
     * @param  WebSocketServer $swoStarServer [description]
     */
    protected function serverBroadcast(WebSocketServer $swoStarServer, $swooleServer ,$data, $fd)
    {
        $config = $this->app->make('config');
        //限制只能管理员广播
        if($this->userInfo->type == 'admin'){
            $cli = new Client($config->get('server.route.server.host'), $config->get('server.route.server.port'));
            if ($cli->upgrade('/')) {
                $cli->push(\json_encode([
                    'method' => 'routeBroadcast',
                    'msg' => $data['msg'],
                    'sourceId' => $this->userInfo->uid,
                    'sourceName' => $this->userInfo->name
                ]));
            }
        }else{
            //没权限
            $swooleServer->push($fd, \json_encode([
                'msg' => '没权限',
            ]));
        }


    }

    /**
     * 接收Route服务器的广播信息
     *
     * @param  WebSocketServer $swoStarServer [description]
     */
    protected function routeBroadcast(WebSocketServer $swoStarServer, $swooleServer ,$data, $fd)
    {
        $dataAck = [
            'method' => 'ack',
            'msg_id' => $data['msg_id']
        ];
        $swooleServer->push($fd, \json_encode($dataAck));
        // dd($data, 'server 中的 routeBroadcast');
        $swoStarServer->sendAll(json_encode($data['data']));
    }

    public function ack()
    {
      // code...
    }

    /**
     * 接收客户端私聊的信息
     *
     * @param  WebSocketServer $swoStarServer [description]
     */
    protected function privateChat(WebSocketServer $swoStarServer, $swooleServer ,$data, $fd)
    {

        //管理员
        // 1. 获取私聊的id
        $send = true;
        $storage = true;
        if($this->userInfo->type == 'admin') {
            $clientId = $data['clientId'];
            $swocloudKey = $this->app->make('config')->get('server.route.jwt.key');
        }else{
            echo 'fd:'.$fd.PHP_EOL;
            //用户
            //1.获取当前绑定adminId
            $swocloudKey = $this->app->make('config')->get('server.route.im.admin_key');
            $bindKey = $this->app->make('config')->get('server.route.im.bind_key');
            $clientId = $swoStarServer->getRedis()->zScore($bindKey, $this->userInfo->uid);
            //2.如果未绑定则判断一次绑定
            if(empty($clientId)){
                if(LoginService::bingAdmin($swoStarServer, $this->userInfo->uid)){
                    $clientId = $swoStarServer->getRedis()->zScore($bindKey, $this->userInfo->uid);
                }else {
                    //没获取到绑定客服id则只存储不发送
                    $send = false;
                }
            }
        }
        // 2. 从redis中获取对应的服务器信息
        if($send === true){
            $clientIMServerInfoJson = $swoStarServer->getRedis()->hGet($swocloudKey, $clientId);
            $clientIMServerInfo = json_decode($clientIMServerInfoJson, true);
            //查找到再发送--返回发送失败？？
            if(!empty($clientIMServerInfo)){
                // 3. 指定发送
                $request = Connections::get($fd)['request'];
                $token = $request->header['sec-websocket-protocol'];
                // $url = 0.0.0.0:9000
                $clientIMServerUrl = explode(":", $clientIMServerInfo['serverUrl']);
                $swoStarServer->send($clientIMServerUrl[0], $clientIMServerUrl[1], [
                    'method' => 'forwarding',
                    'msg' => $data['msg'],
                    'sourceId' => $this->userInfo->uid,
                    'sourceName' => $this->userInfo->name,
                    'fd' => $clientIMServerInfo['fd']
                ], [
                    'sec-websocket-protocol' => $token,
                    'msg_type' => 'transfer',
                ]);
            }
        }
        //4.存储信息--失败或者离线也存储
        $time = time();
        if($storage === true){
            $sendKey = $this->app->make('config')->get('server.route.im.storage_key').$this->userInfo->uid;
            //发送记录
            $sendData = [
                'source_id' => $this->userInfo->uid,
                'source_name' => $this->userInfo->name,
                'receive_id' => $clientId,
                'con' => $data['msg'],
                'time' => date('Y-m-d H:i:s', $time)
            ];
            $swoStarServer->getRedis()->zAdd($sendKey, $time, json_encode($sendData));
            if($send === true){     //接收记录-不发送的情况不需要接收
                $receiveKey = $this->app->make('config')->get('server.route.im.storage_key').$clientId;
                //接收消息记录
                $receiveData = [
                    'source_id' => $this->userInfo->uid,
                    'source_name' => '',
                    'receive_id' => $clientId,
                    'con' => $data['msg'],
                    'time' => date('Y-m-d H:i:s', $time)
                ];
                $swoStarServer->getRedis()->zAdd($receiveKey, $time, json_encode($receiveData));
            }

        }

    }
    /**
     * 转发私聊信息
     *
     * @param  WebSocketServer $swoStarServer [description]
     */
    protected function forwarding(WebSocketServer $swoStarServer, $swooleServer ,$data, $fd)
    {
        $swooleServer->push($data['fd'], json_encode([
            'msg' => $data['msg'],
            'sourceId' => $data['sourceId'],
            'sourceName' => $data['sourceName'],
            'type' => 'friend'
        ]));
    }
}
