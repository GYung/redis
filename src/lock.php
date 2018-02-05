<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 18-1-11
 * Time: 下午4:11
 */

index();
function index(){

    $redis=new redis();
    $redis->connect('127.0.0.1');
    $redis->set('key1','sss');

//    try{
//        $redis->watch('key1');
//        $redis->set('key1',40);
//        $redis->multi();
//        $redis->set('key1',999);
//        $redis->exec();
//
//    }catch (Exception $e){
//        echo $e;
//    }
//   echo $redis->get('key1');

}
//获得锁
function acquire_lock_with_timeout(Redis $conn,$lockname,$acquire_time=10,$lock_time=10){
    $identifier=uniqid();
    $end=time()+$acquire_time;
    $lockname='lock:'.$lockname;
    $lock_time=(int)(ceil($lock_time));

    while (time()<$end){
        if($conn->setnx($lockname,$identifier)){//设置一个不存在键,能设置表明没有其他进程使用
            $conn->expire($lockname,$lock_time);
            return $identifier;
        }elseif (!$conn->ttl($lockname)){//未超时更新
            $conn->expire($lockname,$lock_time);
        }
        sleep(0.001);
    }
    return FALSE;
}
//加锁购买商品
function purchase_item_with_lock(Redis $conn,$buyerid,$itemid,$sellerid,$lprice){
    $buyer="users:".$buyerid;
    $seller="users:".$sellerid;
    $item=$itemid.".".$sellerid;
    $inventory="inventory:".$buyerid;//购买者包裹

    $locked=acquire_lock($conn,'market:');
    if(!$locked)
        return FALSE; //获取锁失败

        $price=$conn->zScore('market:',$item);
        $fund=(int)$conn->hGet($buyer,'funds');//卖家资金
        if($price != $lprice || $fund<$price){
            return NULL;
        }

//       $pipe=$conn->multi(Redis::PIPELINE);
        $conn->multi()
            ->hIncrBy($seller,"funds",$price)//卖家资金增加
            ->hIncrBy($buyer,"funds",-$price)//买家资金减少
            ->sAdd($inventory,$itemid)
            ->sRem('market:',$item)
            ->exec();

    return FALSE;
}

//释放锁
function release_lock(Redis $conn,$lockname,$identifier){

    $lockname='lock'.$lockname;
    while (1){
        $conn->watch($lockname);
        if($conn->get($lockname)==$identifier){
            $conn->multi();
            $conn->delete($lockname);
            $conn->exec();
            return TRUE;
        }
        $conn->unwatch();
        break;
    }
    return FALSE;
}

