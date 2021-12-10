<?php
/**
 * Created by PengJu
 * User: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/11/22/15:19
 */

namespace app\common\lib;


class Arr
{
    public static function tree($date){
        $items = [];
        foreach ($date as $value){
            $items[$value['uuid']] = $value;
        }
        $tree = [];
        foreach ($items as $uuid => $item){
            if (isset($items[$item['puuid']])){
                $items[$item['puuid']]['children'][] = &$items[$uuid];
            }else{
                $tree[] = &$items[$uuid];
            }
        }
        foreach ($tree as $key => $value){
            if ($tree[$key]['level'] != 1){
                unset($tree[$key]);
            }
        }
        return $tree;
    }
}