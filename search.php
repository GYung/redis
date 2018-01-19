<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 18-1-19
 * Time: 下午2:46
 */
index();
function index()
{
    $redis = new redis();
    $redis->connect('127.0.0.1');
}
//获得常用关键　　完善
function tokensize($content){
    $word=array();
    array_push($word,$content);
    return $word;
}
//建立索引集合
function index_document(Redis $conn ,$docid,$content){
    $words=tokensize($content);

    $conn->multi();
    foreach ($words as $item){
        $conn->sAdd('idx:'.$item,$item);
    }
    return $conn->exec();
}
//对集合进行交集、并集、差集操作
function _set_common(Redis $conn,$method,$names,$ttl=30,$execute=TRUE){
    $id=uniqid();
    $conn->multi();
    foreach ($names as $name){
        //问题　循环
        $conn->$method('idx:'.$id,$name);
    }

    $conn->expire('idx:'.$id,$ttl);
    if($execute)
        $conn->exec();
    return $id;
}
//交集
function inter(Redis $conn,$method,$names,$ttl=30){
    return _set_common($conn,'sinterstore',$names,$ttl,TRUE);
}
//分析语句
//分析并查询
//排序

