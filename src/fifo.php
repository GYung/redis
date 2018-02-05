<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 18-1-15
 * Time: 上午10:35
 */
index();
function index(){

    $redis=new redis();
    $redis->connect('127.0.0.1');

}
//推入消息
function send_email_queue(Redis $conn,$seller,$item,$price,$buyer){

    $data=array(
        'seller_id'=>$seller
    );
    $conn->rPush('queue:email',json_encode($data));

}
//创建群组
function create_chat(Redis $conn,$sender,$recipients,$message,$chat_id=NULL){
    $chat_id=$chat_id !=NULL?$chat_id:$conn->incr('ids:chat:');//生成群的id
    $recipients[]=$sender;//添加发送者

    $conn->multi();
    foreach ($recipients as $item){
        $conn->zAdd('chat:'.$chat_id,0,$item);//聊天群组
        $conn->zAdd('seen:'.$item,0,$chat_id);//用户已读集合
    }
    $conn->exec();
    return send_message();
}
//发送消息
function send_message(Redis $conn,$chat_id,$sender,$message){

}