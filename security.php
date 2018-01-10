<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 18-1-3
 * Time: 下午2:29
 */
index();
function index(){

    $redis=new redis();
    $redis->connect('127.0.0.1');


}
//添加销售商品
function list_item(Redis $conn,$itemid,$sellerid,$price){
    $inventory="inventory:".$sellerid;//销售者包裹
    $item=$itemid.".".$sellerid;//市场物品
    $end=time()+5;

    while(time()<$end){
        //检查是否存在该包裹
        $conn->watch($inventory);
       if($conn->sIsMember('$inventory',$itemid)){
           $conn->unwatch();
           return NULL;
       }

       try{ $conn->multi()
           ->zAdd('market:',$price,$item)
           ->srem($inventory,$itemid)
           ->exec();
           return TRUE;
       }catch (RedisException $e){
           continue;
       }

    }
    return FALSE;
}
//购买商品

function purchase_item(Redis $conn,$buyerid,$itemid,$sellerid,$lprice){
    $buyer="users:".$buyerid;
    $seller="users:".$sellerid;
    $item=$itemid.".".$sellerid;
    $inventory="inventory:".$buyerid;//购买者包裹
    $end=time()+10;//重试时间
    while(time()<$end){
        $conn->watch("marker:");
        $conn->watch($buyer);
        $price=$conn->zScore('market:',$item);
        $fund=(int)$conn->hGet($buyer,'funds');//卖家资金
        if($price != $lprice || $fund<$price){
            $conn->unwatch();
            return NULL;
        }
//       $pipe=$conn->multi(Redis::PIPELINE);
        $conn->multi()
            ->hIncrBy($seller,"funds",$price)//卖家资金增加
            ->hIncrBy($buyer,"funds",-$price)//买家资金减少
            ->sAdd($inventory,$itemid)
            ->sRem('market:',$item)
            ->exec();
    }
    return FALSE;
}
