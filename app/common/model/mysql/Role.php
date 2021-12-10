<?php
/**
 * Created by PengJu
 * User: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/10/22/15:36
 */

namespace app\common\model\mysql;


class Role extends BaseModel
{
    public function getByUuidCount($where){
        return $this->where($where)->count();
    }
}