<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 18-2-5
 * Time: 上午9:53
 */
/*
 * 社交网站
 */
include '../myredis.php';
$redis=myredis::getinstance();
$app=new webapp();
//$redis->zAdd('test:1',1,'good',2,'bad');
//$new=$redis->zRevRange('test:1',0,-1);
//
//$rem_str= implode(',',$new);
////var_dump($rem_str);die;
//var_dump($redis->zRem('test:1',$rem_str));
//var_dump($redis->zRevRange('test:1',0,-1));

//$id=(new webapp())->create_user($redis,'gyung');
//$id=$app->create_status($redis,'3','nihao!');
//var_dump($redis->hGetAll('user:3'));
class webapp
{
//创建新用户

    public function create_user(Redis $conn,$login,$name=111){

        $llogin=strtolower($login);
        //加防止同一时间同一用户名创建
        $lock=$this->acquire_lock_with_timeout($conn,'user:'.$llogin,1);

        if(!$lock){
            return NULl;
        }

        //该用户已经存在
        if($conn->hget('users:',$llogin)){//用户名,id映射表中已存在该用户名
          var_dump( $this->release_lock($conn,'user:'.$llogin,$lock));//释放锁

            return null;
        }
        $id=$conn->incr('user：id:');//关键自增
        $conn->multi(Redis::PIPELINE);
        $conn->hSet('users:',$llogin,$id);//用户－id映射表
        $conn->hMset('user:'.$id,array('login'=>$login,
                                            'name'=>$name,
                                            'follower'=>0,
                                            'following'=>0,
                                            'post'=>0,
                                            'signup'=>time()
        ));//设置多个值
        $conn->exec();
        $this->release_lock($conn,'user:'.$llogin,$lock);//修改完毕释放锁
        return $id;
    }

    //发布状态消息 网站主页？
    public function create_status(Redis $conn,$uid,$message){
        $conn->multi(Redis::PIPELINE);
        $conn->hGet('user:'.$uid,'login');
        $conn->incr('status:id:');

        list($login,$id)=$conn->exec();

        if(!$login){
            return false;
        }
        $data=array('id'=>$id,
                 'login'=>$login,
            'uid'=>$uid,
            'posted'=>time(),
            'message'=>$message
        );
        $conn->hIncrBy('user:'.$uid,'post',1);//用户hash表自增
        $conn->hMset('status:'.$id,$data);
        $conn->exec();

        return $id;
    }

    //家主页时间线
    public function get_status_messages(Redis $conn,$uid,$timeline='home:',$page=1,$count=30){
        //逆序，从大到小
        $status=$conn->zRevRange($timeline.$uid,($page-1)*$count,$page*$count-1);
        $conn->multi(Redis::PIPELINE);
        foreach ($status as $id){
            $conn->hGetAll('status:'.$id);
        }
        $result[]=$conn->exec();
        return  array_filter($result);//通过回调函数过滤掉数组值


    }

    //用户关注其他人后时间线更新
    public function follow_user(Redis $conn,$uid,$other_uid){
        $fkey1='following'.$uid;//我的关注列表
        $fkey2='followers'.$other_uid;//我关注的人的被关注列表
        if($conn->zScore($fkey1,$other_uid)){//如果已关注
            return null;
        }
        $now=time();
        $conn->multi(Redis::PIPELINE);
        $conn->zAdd($fkey1,$now,$other_uid);
        $conn->zAdd($fkey2,$now,$uid);
        //获得最近被关注人最近的消息
        $conn->zRevRange('profile:'.$other_uid,0,10,true);
        list($following,$followers,$status_scores)=$conn->exec();
        $conn->hIncrBy('user:'.$uid,'following',(int)$following);
        $conn->hIncrBy('user:'.$other_uid,'followers',(int)$followers);
        if(!empty($status_scores)){
            //添加进主页时间线(消息id和发布时间)
            foreach ($status_scores as $status=>$scores)
            $conn->zAdd('home:'.$uid,$scores,$status);
        }
        $conn->zRemRangeByRank('home:'.$uid,0,-11);//只保留最新的状态消息
        $conn->exec();
        return true;
    }
    //用户取消关注某个用户
    public function unfollow_user(Redis $conn,$uid,$other_uid){
        $fkey1='following'.$uid;//我的关注列表
        $fkey2='followers'.$other_uid;//我关注的人的被关注列表
        if(!$conn->zScore($fkey1,$other_uid)){//如果未关注
            return null;
        }
        //双方关注表取消
        $conn->multi(Redis::PIPELINE);
        $conn->zRem($fkey1,$other_uid);
        $conn->zRem($fkey2,$uid);
        //获得被关注者的状态列表
        $conn->zRevRange('profile:'.$other_uid,0,10);
        list($following,$followers,$status)=$conn->exec();
        //双方关注数更新
        $conn->hIncrBy('user:'.$uid,'following',(int)$following);
        $conn->hIncrBy('user:'.$other_uid,'followers',(int)$followers);
        //关注者主页时间线更新
        foreach ($status as $item){
            $conn->zRem('home:'.$uid,$item);
        }

        $conn->exec();
        return true;



    }

    //加锁
    public function acquire_lock_with_timeout(Redis $conn,$lockname,$acquire_time=10,$lock_time=1){

        $identifier=uniqid();
        $end=time()+$acquire_time;//获取锁的时间
        $lock_name='lock:'.$lockname;
        $lock_time=(int)(ceil($lock_time));//过期时间

        while (time()<$end){

            if($conn->setnx($lock_name,$identifier)){//设置一个不存在键,能设置表明没有其他进程使用

                $conn->expire($lock_name,$lock_time);
                return $identifier;
            }elseif (!$conn->ttl($lock_name)){//未超时更新

                $conn->expire($lock_name,$lock_time);//重置过期时间
            }

            sleep(0.001);
        }
        return FALSE;
    }
    //释放锁
    public function release_lock(Redis $conn,$lockname,$identifier){

        $lock_name='lock:'.$lockname;
        while (1){//循环

            $conn->watch($lock_name);

            if($conn->get($lock_name)==$identifier){
//                $conn->set($lock_name,1231);
                $conn->multi();
                $conn->delete($lock_name);
                 $ret=$conn->exec();
                var_dump('123'.$ret);
                 if(!$ret){//问题：一定只有watch错误返false吗

                     continue;
                 }
                return TRUE;
            }
            $conn->unwatch();
            break;
        }
        return FALSE;
    }




}