<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 18-2-5
 * Time: 下午2:30
 */

class myredis
{
 private static $redis;
 function __construct()
 {
 }
 function __clone()
 {
     // TODO: Implement __clone() method.
 }
 public static function getinstance(){
     if(self::$redis == null){
         self::$redis=new redis();
         self::$redis->connect('127.0.0.1');
     }
     return self::$redis;
 }
}