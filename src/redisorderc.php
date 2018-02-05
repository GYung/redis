<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 18-1-2
 * Time: 上午9:13
 */

index();
function index(){

    $redis=new redis();
    $redis->connect('127.0.0.1');
    rediSort($redis);

}

function rediString(Redis $conn){

    echo $conn->setRange('new',6,'H');
    echo $conn->setBit('nb',2,1);
    echo $conn->get('nb');die;
    echo $conn->append('new','hello　');

}
function redisList(Redis $conn){
    $conn->rPush('list-key','last');
    $conn->lPush('list-key','first');
    $conn->lPush('list-key2','the two');
    $conn->rpoplpush('list-key','list-key2');
    var_dump($conn->lRange('list-key2',0,-1) );
//    var_dump($conn->blPop('list-key',10) );

}
function rediSet(Redis $conn){
    $conn->sAdd('set-key','a','b','c');
    $conn->sRem('set-key','b','d');
    echo $conn->sCard('set-key');
    $conn->sMove('set-key','set-key2','a');

    $conn->sadd('skey1','a','b','c','d');
    $conn->sadd('skey2','f','e','c','d');

    var_dump($conn->sUnion('skey1','skey2'));
//    var_dump($conn->sMembers('set-key'));
}

function redisHash(Redis $conn){
    $conn->hMset('hash-key',array('k1'=>'v1','k2'=>'v2'));
    ($conn->hMGet('hash-key',array('k1','k3')));
    $conn->hLen('hash-key');
    $conn->hDel('hash-key','key1','key2');

    var_dump($conn->hKeys('hash-key')) ;
}
function redisZset(Redis $conn){
    $conn->zAdd('zset-key',4,'a',2,'b',1,'c');
    $conn->zAdd('zset-key2',5,'a',1,'b',1,'c');
    $conn->zInter('zset-i',array('zset-key','zset-key2'),'MIN');
//     $conn->zIncrBy('zset-key',3,'a');
    var_dump($conn->zRange('zset-i',0,-1,true));
//    echo $conn->zCard('zset-key');

}
function rediSort(Redis $conn){

    var_dump($conn->multi()
            ->set('key1', 'val1')
            ->get('key1')
            ->set('key2', 'val2')
            ->get('key2')
        ->exec());

    echo $conn->get('key2');
    die;
   $conn->set('key','value');
   $conn->expire('key',2);
   sleep(1);
   echo $conn->ttl('key');
}