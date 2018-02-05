<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 17-12-23
 * Time: 下午6:41
 */

define('LIMIT',10000);
index();
function index(){

    $redis=new redis();
    $redis->connect('127.0.0.1');

}
//cookie 缓存
function check_token(Redis $conn,$token){
    return $conn->hGet('login:',$token);
}

function update_token(Redis $conn,$token,$user,$item=NULL)
{
    $timestamp = time();
    $conn->hSet('login:', $token, $user);
    $conn->zAdd('recent:', $timestamp, $token);
    if ($item != NULL) {
        $conn->zAdd('viewed:' . $token, $timestamp, $item);
        $conn->zRemRangeByRank('viewed:' . $token, 0, -26);
        $conn->zIncrBy('viewed',-1,$item);
    }
}

function clean_full_sessions(Redis $conn){
    while (1){

        $size=$conn->zCard('recent:');
        if($size <= LIMIT){
            sleep(1);
            continue;
        }
        $end_index= min($size-LIMIT,100);
        $tokens=$conn->zRange('recent',0,$end_index-1);//返回
        $session_keys=array();
        foreach ($tokens as $key=>$item){
            $session_keys[]='viewed:'.$key;//清除该会话下的浏览记录
            $session_keys[]='cart:'.$key;//清除该会话下的购物车
            $conn->hDel('login:',$key);
            $conn->zRem('recent:',$key);
        }
        $conn->delete($session_keys);

    }
}
function add_to_cart(Redis $conn,$session,$item,$count){
    if($count<=0){
        $conn->hDel('cart:'.$session,$item);
    }else{
        $conn->hSet('cart'.$session,$item,$count);
    }
}
//网页缓存
//数据行缓存
function cache_rows(Redis $conn){
    while(1){
        $next=$conn->zRange('schedule:',0,0,TRUE);
        $now=time();
        if($next==NULL || array_values($next)<$now){
            sleep(0.05);
            continue;
        }
        $row_id=key($next);

        $delay=$conn->zScore('delay:'.$row_id);//返回分值
        if($delay<=0){
            $conn->zRem('schedule:',$row_id);
            $conn->zRem('delay:',$row_id);
            $conn->delete('inv:'.$row_id);
            continue;
        }
        $conn->zAdd('schedule:',$now+$delay,$row_id);
        $conn->set('inv'.$row_id,json_encode($row_id));


    }
}
//部分页面缓存