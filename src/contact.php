<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 18-1-10
 * Time: 下午4:59
 */
index();
function index(){

    var_dump(strncasecmp('好hello','好hel',strlen('好hell')));die;
    $redis=new redis();
    $redis->connect('127.0.0.1');
    add_update_contact($redis,'userone','liming');
   var_dump( $redis->lRange('recent:userone',0,-1));
}
//添加联系人
function add_update_contact(Redis $conn,$user,$contact){
    $ac_list='recent:'.$user;
    $pip=$conn->multi();
    $pip->lRem($ac_list,$contact,0);
    $pip->lPush($ac_list,$contact);
    $pip->lTrim($ac_list,0,99);
    $pip->exec();
}
//查找联系人
function fetch_autocomplete_list(Redis $conn,$user,$prefix){
$candidates=$conn->lRange('recent:'.$user,0,-1);
$match=array();

    foreach ($candidates as $item){
     if(strncasecmp($item,$prefix,strlen($prefix))==0)
         $match[]=$item;

}
return $match;
}