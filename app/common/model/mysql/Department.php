<?php
/**
 * Created by PengJu
 * RoleUser: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/9/26/15:44
 */

namespace app\common\model\mysql;

class Department extends BaseModel
{
    public $staffDepartment;
    public function __construct(array $data = [])
    {
        parent::__construct($data);
        $this->staffDepartment = new StaffDepartment();
    }

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

    // 验证部门下是否有组织人员
    public function canAddDepartment($department_uuid){
        $read = $this->read($department_uuid);
        $flag = false;
        if (!$read['leaf_flag']){
            $staff_department = $this->staffDepartment->inquiryCount(['department_uuid' => $department_uuid]);
            if (!$staff_department){
                $flag = true;
            }
        }
        return $flag;
    }

}