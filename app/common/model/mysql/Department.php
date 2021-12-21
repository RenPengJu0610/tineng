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

    /**
     * 获取其子集
     * @param $uuid
     * @param false $all
     * @return array
     * @date 2021/12/17/15:20
     * @author RenPengJu
     */
    public function getChildrenUuids($uuid,$all = false){
        $childrenUuids = [];
        if ($uuid){
            if ($all){
                $childrenUuids = $this->where('department_path','like',"%{$uuid}%")->where(['is_show' => 1])->column('uuid');
            }else{
                $childrenUuids = $this->where('department_path','like',"%{$uuid}%")->where(['is_show' => 1,'status' => 1])->column('uuid');
            }
        }
        return array_merge($childrenUuids,[$uuid]);
    }

    // 获取有效父级
    public function pickOutDepartmentUuid($department_uuids){
        $valid_uuid_arr = [];
        if ($department_uuids){
            $where['uuid'] = $department_uuids;
            $where['status'] = 1;
            $where['is_show'] =1;
            $departments = $this->where($where)->column('department_path','uuid');
            foreach ($departments as $key => $val){
                $departments_path = explode(',',$val);
                $valid_uuid_arr = array_merge($department_uuids,[$key],$departments_path);
            }
        }
        return array_unique($valid_uuid_arr);
    }

}