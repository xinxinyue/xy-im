<?php
namespace App\Listener;

use ImStart\Event\Listener;
use Swoole\Coroutine;

class StartListener extends Listener
{
    protected $name = 'start';

    public function handler($swoStarServer = null, $swooleServer  = null)
    {
        $config = $this->app->make('config');
        Coroutine::create(function() use ($swoStarServer, $config){
            $cli = new \Swoole\Coroutine\Http\Client($config->get('server.route.server.host'), $config->get('server.route.server.port'));
            $ret = $cli->upgrade("/"); //升级的websockt
            if ($ret) {
                $data=[
                    'method'     =>'register', //方法
                    'serviceName'=>'IM1',
                    'ip'         => '127.0.0.1',
                    'port'       => $swoStarServer->getPort()
                ];
                $cli->push(json_encode($data));
                //心跳处理
                swoole_timer_tick(3000, function () use( $cli){
                    if($cli->errCode==0){
                        // $cli->push('',WEBSOCKET_OPCODE_PING); //
                    }
                });
            }
        });
    }
}
