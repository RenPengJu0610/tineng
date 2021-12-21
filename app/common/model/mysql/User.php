<?php
/**
 * Created by PengJu
 * RoleUser: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/9/26/15:44
 */

namespace app\common\model\mysql;

use app\common\model\mysql\ RoleUser;
use app\common\model\mysql\ RoleData;
use app\common\model\mysql\ RoleMenu;
use app\common\model\mysql\Department;
use app\common\model\mysql\UserData;
class User extends BaseModel
{
    public $roleDataModel;
    public $roleUserModel;
    public $roleMenuModel;
    public $departmentModel;
    public $userDataModel;
    public function __construct(array $data = [])
    {
        parent::__construct($data);
        $this->roleUserModel = new RoleUser();
        $this->roleDataModel = new RoleData();
        $this->roleMenuModel = new RoleMenu();
        $this->departmentModel = new Department();
        $this->userDataModel = new UserData();
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

    public function getUserOwnDataDetail($user_info,$data_uuid,$type = 'department'){
        $res = ['data_permission' => 1,'data_flag' => 1,'department' => [],'staff' => []];
        // 获取当前登录用户的信息
        $read = $this -> read($user_info['uuid']);
        if ($read['type'] == 0) {
            $res['data_permission'] = 0;
            return $res;
        }
        // 1、根据用户的uuid去角色关联表中取出角色id，然后根据角色id去角色权限关联表中取出关联的权限，
        $role_uuids = $this->roleUserModel->where(['user_uuid' => $read['uuid']])->column('role_uuid');
        if (!$role_uuids){
            return $res;
        }
        $department_uuids = explode(',',$data_uuid);
        if (!empty($department_uuids)){
            if ($type == 'department'){
                $child_department_uuids = [];
                foreach ($department_uuids as $key => $department_uuid){
                    $child_department_uuids = array_merge($child_department_uuids,$this->departmentModel->getChildrenUuids($department_uuid));
                }
                $role_datas = $this->roleDataModel->where(['role_uuid' => $role_uuids])->where(['data_uuid' => $child_department_uuids])->where(['type' => 1])->select()->toArray();
                $user_datas = $this->userDataModel->where(['user_uuid' => $read['uuid']])->where(['data_uuid' => $child_department_uuids])->where(['type' => 1])->select()->toArray();
                $role_datas = array_merge($role_datas,$user_datas);
            }else{

            }
            foreach ($role_datas as $role_data){
                $res['department'][] = $role_data['data_uuid'];
                $res['staff'] = empty($role_data['staff_uuid_str']) ? $res['staff'] : array_merge($res['staff'],explode(',',$role_data['staff_uuid_str']));
            }
        }
        $res['department'] = $this->departmentModel->pickOutDepartmentUuid($res['department']);
        return $res;
    }
}