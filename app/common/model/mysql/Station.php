<?php
/**
 * Created by PengJu
 * RoleUser: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/9/26/15:44
 */

namespace app\common\model\mysql;


class Station extends BaseModel
{
    public function getParentUuids($uuid,$needSelf = false){
        $read = $this->read($uuid);
        $parentUuids = [];

        if (!$read['menu_path']){
            $parentUuids = explode('/',$read['menu_path']);
        }

        if ($needSelf){
            $parentUuids[] = $uuid;
        }

        return $parentUuids;
    }
}