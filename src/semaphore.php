<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 18-1-12
 * Time: 下午3:46
 */
index();
function index(){

    $redis=new redis();
    $redis->connect('127.0.0.1');

}

//获取信号量
function acquire_semaphore(Redis $conn,$sename,$limit,$timeout=10){
    $identifier=uniqid();
    $now=time();
    $conn->multi();
    $conn->zRemRangeByScore('semaphore:'.$sename,'-inf',$now-$timeout);
    $conn->zAdd('semaphore:'.$sename,$now,$identifier);//系统时间慢的排在前面
    $conn->zRank('semaphore:'.$sename,$identifier);
    $conn->exec();
    return $identifier;

}
//公平信号量（系统时间不统一
function acquire_fair_semaphore(Redis $conn,$sename,$limit,$timeout=10){
    $identifier=uniqid();
    $czset=$sename.":owner";//信号量拥有者
    $ctr=$sename.":counter";//计数器

    $now=time();
    $conn->multi();
    $conn->zRemRangeByScore($sename,'-inf',$now-$timeout);
    $conn->zInter($czset,array($sename,$czset),array(0,1));//求交集
    $conn->incr($ctr);//计数器自增
    $counter=$conn->exec()[-1];//最后的返回结果

    $conn->zAdd($sename,$now,$identifier);//获得信号量
    $conn->zAdd($czset,$counter,$identifier);//拥有者增加

    $conn->zRank($czset,$identifier);
    if($conn->exec()[-1]<$limit)
        return $identifier;
    $conn->zRem($sename,$identifier);
    $conn->zRem($czset,$identifier);
    return NULL;
}
//消除竞争　利用锁，先取得锁　在去获得信号量