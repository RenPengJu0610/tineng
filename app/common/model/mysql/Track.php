<?php
/**
 * Created by PengJu
 * User: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/12/13/14:53
 */

namespace app\common\model\mysql;


use think\facade\Db;

class Track extends BaseModel
{
    public function trackQuery($where,$page,$page_size,$order,$field){
        $data['data'] = Db::name('staff')
                        ->alias('s')
                        ->join('track_staff ts','ts.staff_uuid = s.uuid','left')
                        ->join('sport l','l.uuid = s.sport_uuid','left')
                        ->where($where)
                        ->field($field)
                        ->order($order)
                        ->group('s.uuid')
                        ->page($page,$page_size)
                        ->select()
                        ->toArray();

        $data['total'] = Db::name('staff')
                        ->alias('s')
                        ->join('track_staff ts', 'ts.staff_uuid = s.uuid', 'left')
                        ->join('sport l', 'l.uuid = s.sport_uuid', 'left')
                        ->where($where)
                        ->field($field)
                        ->order($order)
                        ->group('s.uuid')
                        ->count();
        return $data;
    }

    public function staffDepartment(){
        $staff_department = Db::name('staff_department')
                                ->alias('sd')
                                ->join('department d','sd.department_uuid = d.uuid','left')
                                ->field('sd.staff_uuid,sd.department_uuid,d.name')
                                ->where(['d.status' => 1])
                                ->select()
                                ->toArray();
        return $staff_department;
    }
}