<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 17-12-16
 * Time: 下午5:56
 */


define("ONE_WEEK",7*86400);
define("VOTE_SCORE",86400/200);
define("ARTICLE_PER_PAGE",10);
index();
function index(){

    $redis=new redis();
    $redis->connect('127.0.0.1');

    $article_id= post_vote($redis,12,"今日头条","www.baidu.com");

    add_remove_group($redis,$article_id,array('study'));

    var_dump(get_group_articles($redis,'study',2));
    print_r(($redis->getLastError()));die;
//    vote($redis,1,"article:123");

}

//建立新的文章
function post_vote(Redis $conn,$user,$title,$link){
$article_id=$conn->incr('article');

$voted="voted:".$article_id;
$conn->sAdd($voted,$user);
$conn->expire($voted,ONE_WEEK);

$now=time();

$article='article:'.$article_id;
$conn->delete($article);
if(!$conn->exists($article)){
    $conn->hMset($article,array(
        'title'=>$title,
        'link'=>$link,
        'poster'=>$user,
        'time' =>$now,
        'votes'=>1

    ));

}

$conn->zAdd('score:',$now+VOTE_SCORE,$article);
$conn->zAdd('time:',$now,$article);

return $article_id;

}
//投票
function vote(Redis $conn,$user,$article){
    $cutoff=time()-ONE_WEEK;

    $article_id=explode(':',$article);
    //判断是否过期
    if($conn->zScore('time',$article) < $cutoff)
        return;

    //已投用户表添加
    if($conn->sAdd('voted:'.$article_id[1],$user));
    {   //文章分数表自增
        $conn->zIncrBy('score:',VOTE_SCORE,$article);
        //文章表增加投票数
        $conn->hIncrBy($article,'votes',  1);


    }

}


//获取排序文章投票
function get_articles(Redis $conn,$page,$order='score:'){
$start=($page-1)*ARTICLE_PER_PAGE;
$end=$start+ARTICLE_PER_PAGE-1;

$ids=$conn->zRevRange($order,$start,$end);

$articles=array();
foreach ($ids as $id){
    $article_data=$conn->hGetAll($id);
    $article_data['id']=$id;
    $articles[]=$article_data;
}
return $articles;
}
//添加群组
function add_remove_group(Redis $conn,$article_id,$to_add=array(),$to_remove=array()){
    $article='article:'.$article_id;
    foreach ($to_add as $item){

        $conn->sAdd('group:'.$item,$article);
    }


        foreach ($to_remove as $item){
            $conn->srem('group:'.$item,$article);
        }

}

//获取排序文章组投票
function get_group_articles(Redis $conn,$group,$page,$order='score:'){
    $key=$order.$group;

    $conn->delete($key);
    if(!$conn->exists($key)){
        var_dump($conn->zInter($key,array($order,'group:'.$group),null,"MAX"));

    }

    $conn->expire($key,60);

    return get_articles($conn,$page,$key);
}
include('pagev.php');