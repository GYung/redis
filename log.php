<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 18-1-4
 * Time: 下午3:17
 */
index();
function index(){

    $redis=new redis();
    $redis->connect('127.0.0.1');

    log_recent($redis,'log','ok');
    $destination='recent:log:info';
    var_dump($redis->lRange($destination,0,-1)) ;

}
//记录消息
function log_recent(Redis $conn,$name,$message,$severity='info',$pipe=null){
    $destination='recent:'.$name.":".$severity;
    $message=time()." ".$message;
    $pipe=($pipe==null)?$conn->multi(Redis::PIPELINE):$pipe;
    $pipe->lPush($destination,$message);
    $pipe->lTrim($destination,0,99);
    $pipe->exec();
}
//日志轮替
function log_common(Redis $conn,$name,$message,$severity='info',$timeout=5){
    $destination='common:'.$name.":".$severity;
    $start_key=$destination.':start';

    $message=time()." ".$message;
    $pipe=$conn->multi(Redis::PIPELINE);
    $end=time()+$timeout;
    while(time()<$end){
        $pipe->watch($start_key);
        $hour_start=date('H');
        $existing=$pipe->get($start_key);
        $pipe->multi();
        if($existing && $existing<$hour_start){
            $pipe->rename($destination,$destination.':last');
            $pipe->rename($start_key,$destination.'pstart');
            $pipe->set($start_key,$hour_start);//
        }else if (!$existing){
            $pipe->set($start_key,$hour_start);
        }
        $pipe->zIncrBy($destination,1,$message);//日志消息计数器
        log_recent($pipe,$name,$message,$pipe);
        return;
    }

}
//更新计数
define('PRECISION',array(1,5,60,300,3600,18000,86400));
function update_counter(Redis $conn,$name,$count=1,$now=NULL){
    $now=($now==null)?time():$now;
    $pipe=$conn->multi(Redis::PIPELINE);
    foreach (PRECISION as $prec){
        $pnow=(int)($now/$prec)*$prec;//时间片开始时间
        $hash=$prec.":".$name;
        $pipe->zAdd('known:',0,$hash);
        $pipe->hIncrBy('count:'.$hash,$pnow,$count);
        $pipe->exec();
    }
}
//获得计数
function get_counter(Redis $conn,$name,$precision){
    $hash=$name.":".$precision;
    $data=$conn->hGetAll('count:'.$hash);
    $to_return=array();
    foreach($data as $k=>$v){
        $to_return[$k]=$v;
    }
    ksort($to_return);
    return $to_return;
}
function clean_counters(Redis $conn){
    $pipe=  $conn->multi(Redis::PIPELINE);
    $passes=0;
    while(1){
        $start=time();//清理开始时间
        $index=0;
        while ($index<$conn->zCard('known:')){
            $hash=$conn->zRange('know:',$index,$index);//取值
            $index++;
            if($hash){
                break;
            }
            $hash=$hash[0];
            $prec=explode($hash,":");
            $bprec=(int)($prec[0]/60);
            if($bprec==0)
                $bprec=1;
            if($passes%$bprec){
                continue;//更新时间
            }

            $hkey='count:'.$hash;
            $cutoff=time();
            $conn->zUnion();
        }
    }
}
//转换ip地址
function ip_to_score($ip){
    $score=0;
    foreach (explode($ip,'.') as $item){
        
    }

}