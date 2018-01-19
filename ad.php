<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 18-1-19
 * Time: 下午4:28
 */

function cpc_to_ecpm($views,$click,$cpc){
    return 1000*($cpc*$click/$views);
}
//
function cpa_to_ecpm($views,$action,$cpa){
    return 1000*($cpa*$action/$views);
}
