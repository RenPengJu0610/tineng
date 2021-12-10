<?php
/**
 * Created by PengJu
 * User: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/10/22/14:51
 */

namespace app\common\business;


use app\common\lib\Arr;
use think\Exception;
use app\common\model\mysql\Role as RoleModel;
use app\common\model\mysql\RoleUser as RoleUserModel;
use app\common\model\mysql\Menu as MenuModel;
use app\common\model\mysql\Department as DepartmentModel;
use app\common\model\mysql\RoleMenu as RoleMenuModel;

class Role extends BusBase
{
    public $role;
    public $roleUser;
    public $menu;
    public $roleMenu;

    public function __construct()
    {
        $this->role = new RoleModel();
        $this->roleUser = new RoleUserModel();
        $this->menu = new MenuModel();
        $this->departmentModel = new DepartmentModel();
        $this->roleMenu = new RoleMenuModel();
    }

    public function add($params, $currenUser)
    {
        try {

            $role_uuid = guid();
            $dateTime = date('Y-m-d H:i:s');
            // 验证角色名称的唯一性
            $role_name_count = $this->role->inquiryCount(['name' => $params['name']]);
            if ($role_name_count){
                throw new Exception('角色名称已存在');
            }
            $user_uuids = [];
            if (!empty($params['user_uuids'])){
                $user_uuids = explode(',',$params['user_uuids']);
            }
            unset($params['user_uuids']);
            $role_data = [
                'uuid' => $role_uuid,
                'name' => $params['name'],
                'create_by' => $currenUser['name'],
                'create_date' => $dateTime,
                'account_quantity' => count($user_uuids)
            ];
            $this->role->startTrans();
            $roleAdd = $this->role->add($role_data);
            if (!$roleAdd){
                throw new Exception('添加失败');
            }
            $role_user_data = [];
            foreach ($user_uuids as $user_uuid){
                $role_user_data[] = [
                    'role_uuid' => $role_uuid,
                    'user_uuid' => $user_uuid,
                    'create_by' => $currenUser['name'],
                    'create_time' => $dateTime
                ];
            }
            if ($user_uuids){
                $userAdd = $this->roleUser->add($role_user_data);
                if (!$userAdd){
                    throw new Exception('角色和用户关联表添加失败');
                }
            }
            $this->role->commit();
        } catch (\Exception $e) {
            $this->role->rollback();
            throw new Exception($e->getMessage());
            return false;
        }
        return true;
    }

    public function update($params,$currenUser){
        try {
            $role_uuid = $params['role_uuid'];
            $where = [
                'uuid' => $role_uuid,
                'status' => 1
            ];
            $date = date('Y-m-d H:i:s');
            $single = $this->role->single($where);
            if (!$single){
                throw new Exception('参数错误');
            }
            if ($single['name'] != $params['name']) {
                $nameCount = $this->role->inquiryCount(['name' => $params['name']]);
                if ($nameCount) {
                    throw new Exception('角色名称已存在');
                }
            }
                $delByRoelUuid = $this->roleUser->delByWhere(['role_uuid' => $role_uuid]);
                if ($delByRoelUuid ===false){
                    throw new Exception('删除关联表失败');
                }
                $user_ids = [];
                if (!empty($params['user_uuids'])){
                    $user_ids = explode(',',$params['user_uuids']);
                }
                $role_data = [
                    'name' => $params['name'],
                    'create_date' => $date,
                    'create_by' => $currenUser['name'],
                    'account_quantity' => count($user_ids)
                ];
                $this->role->startTrans();
                $role_add = $this->role->modifyWhere(['uuid' => $role_uuid],$role_data);

                if ($role_add === false){
                    throw new Exception('修改失败');
                }
            $role_count = $this->roleUser->inquiryCount(['role_uuid' => $role_uuid]);
            if ($user_ids && $role_count){
                    $user_data = [];
                    foreach($user_ids as $user_id){
                        $user_data[] = [
                            'role_id' => $role_uuid,
                            'user_id' => $user_id,
                            'create_time' => $date,
                            'create_by' => $currenUser['name']
                        ];
                    }
                    $user_add = $this->roleUser->modifyWhere(['role_uuid' => $role_uuid],$user_data);
                    if ($user_add === false){
                        throw new Exception('修改用户表失败');
                    }
            }
            return true;
            $this->role->commit();
        }catch (\Exception $e){
            $this->role->rollback();
            throw new  Exception($e->getMessage());
        }
        return true;
    }

    public function del($uuids){
        try {
            $this->role->startTrans();
            $uuid_arr = explode(',',$uuids);
            $role_count = $this->role->inquiryCount(['uuid' => ['in',$uuid_arr]]);
            if (count($uuid_arr) != $role_count){
                throw new Exception('参数错误');
            }
            // 删除用户角色关联表中的数据
            $del_roleUser = $this->roleUser->delByWhere(['role_uuid' => ['in',$uuid_arr]]);
            if ($del_roleUser === false){
                throw new Exception('用户角色表数据删除失败');
            }
            // 删除角色表中的数据
            $del_role = $this->role->delByWhere(['uuid' => ['in',$uuid_arr]]);
            if ($del_role === false){
                throw new Exception('删除失败');
            }
            return true;
            $this->role->commit();
        }catch (\Exception $e){
            $this->role->rollback();
            throw new Exception($e->getMessage());
            return false;
        }
        return true;
    }

    public function able($uuid){
        try {
            $read = $this->role->read($uuid);
            if (!$read){
                throw new Exception('数据不存在');
            }
            $status = 0;
            if (!$read['status']){
                $status = 1;
            }
            $role_status = $this->role->modify($uuid,['status' => $status]);
            if ($role_status === false){
                throw new Exception('状态修改失败');
            }
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
            return false;
        }
        return true;

    }

    public function create($params,$currentUser){
        try {
            $uuid = guid();
            $date = date('Y-m-d H:i:s');
            $role['name'] = $params['name'];
            $role['uuid'] = $uuid;
            $role['create_time'] = $date;
            $role['create_by'] = $currentUser['name'];
            if (!empty($params['dome'])){
                $role['dome'] = $params;
            }
            // 根据传过来的角色名称查看数据库是否已经存在该名称
            $count_name = $this->role->inquiryCount(['name' => $params['name']]);
            if ($count_name > 0){
                throw new Exception('角色名称已存在');
            }
            $role_user = [];
            if (!empty($params['user_uuid'])){
                 $user_uuids = explode(',',$params['user_uuid']);
                 foreach ($user_uuids as $key => $value){
                     $role_user[$key]['user_uuid'] = $value;
                     $role_user[$key]['role_uuid'] = $uuid;
                     $role_user[$key]['create_time'] = $date;
                     $role_user[$key]['create_by'] = $currentUser['name'];
                 }
            }
            $role_meun = [];
            if (!empty($params['menu_uuid'])){
                $menu_uuids = array_unique(explode(',',$params['menu_uuid']));
                $check_menu_uuids = $this->menu->checkMenu($menu_uuids);
                foreach ($check_menu_uuids as $key => $value){
                    $role_meun[$key]['role_uuid'] = $uuid;
                    $role_meun[$key]['menu_uuid'] = $value;
                    $role_meun[$key]['create_time'] = $date;
                    $role_meun[$key]['create_by'] = $currentUser['name'];
                }
            }
            $typeArr = [];
            if (!empty($params['type'])){
                $typeArr = explode(',',$params['type']);
            }
            // type:1时，说明是添加的数据权限
            if (count($typeArr) == 1){

                if ($typeArr[0] == 1){
                    if (empty($params['department_uuid'])){
                        throw new Exception('参数错误');
                    }
                    // 避免提交不存在的数据，故而查询一次
                    $check_department_last = $this->checkDepartemntLast($params['department_uuid']);
                    $role_data = $this->disposeData($check_department_last,$params,1,$uuid);
                }
                var_dump($role_data);exit();
            }

        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
        exit();
    }

    public function checkDepartemntLast($uuids){
        $uuid_arr = explode(',',$uuids);
        foreach ($uuid_arr as $value){
            $read = $this->departmentModel->read($value);
            if (!$read){
                throw new Exception('部门不存在');
            }
            if (!$read['leaf_flag']){ // 判断是否是叶子节点，1不做判断，0时，看下其下面是否还有子节点
                $childrent = $this->departmentModel->inquiryCount(['puuid' => $read['uuid']]);
                if ($childrent){
                    throw new Exception($read['name'] . '不是末级部门');
                }
            }
        }
        return $uuid_arr;
    }

    public function disposeData($data,$params,$type,$uuid){
        $role_data = [];
        foreach ($data as $datum){
            $str = "";
            if (isset($params[$datum])){
                $str = $params[$datum];
            }
            $role_data[$datum]['role_uuid'] = $uuid;
            $role_data[$datum]['data_uuid'] = $datum;
            $role_data[$datum]['staff_uuid_str'] = $str;
            $role_data[$datum]['type'] = $type;
        }
        return $role_data;
    }
    public function functionalLists($uuid){
        try {
            if (empty($uuid)){
                throw new Exception('参数错误');
            }
            $read = $this->role->read($uuid);
            if (!$read){
                throw new Exception('角色不存在');
            }
            $menu_uuids = $this->roleMenu->inquiryColumn(['role_uuid' => $uuid],'menu_uuid');
            $menu_all_uuids = $this->menu->inquiryAll(['plat' => 2],[],['uuid','name','puuid','level']);
            foreach ($menu_all_uuids as $key => $value){
                $menu_all_uuids[$key]['is_check'] = false;
                if (in_array($value['uuid'],$menu_uuids)){
                    $menu_all_uuids[$key]['is_check'] = true;
                }
            }

        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
        return Arr::tree($menu_all_uuids);
    }

    /**
     * 修改角色
     * @param $params
     * @param $currentUser
     * @date 2021/12/3/16:57
     * @author RenPengJu
     */
    public function updateRole($params,$currentUser){
        try {
            $uuid = $params['uuid'];
            unset($params['uuid']);
            // 查询要修改的数据是否存在
            $read = $this->role->read($uuid);
            if (!$read){
                throw new Exception('数据不存在');
            }
            // 如果修改名称，则验证角色名称是否存在
            if ($read['name'] != $params['name']){
                $name_count = $this->role->inquiryCount(['name' => $params['name']]);
                if ($name_count){
                    throw new Exception('角色名称已存在，请重试');
                }
            }
            $date = date('Y-m-d');
            // 组装数据
            $params['create'] = $date;
            $params['create_by'] = $currentUser['name'];
            $role_menu = [];
            if (!empty($params['menu_uuid'])){
                $menu_uuids = explode(',',$params['menu_uuid']);
                $menu_uuids_arr = $this->menu->checkMenu($menu_uuids);
                $menu_uuids_arr = array_unique($menu_uuids_arr);
                foreach ($menu_uuids_arr as $key => $value){
                    $role_menu[$key]['role_uuid'] = $uuid;
                    $role_menu[$key]['menu_uuid'] = $value;
                    $role_menu[$key]['create_time'] = $date;
                    $role_menu[$key]['create_by'] = $currentUser['name'];
                }
            }
            $role_user = [];
            if (!empty($params['user_uuid'])){
                $user_uuids = explode(',',$params['user_uuid']);
                foreach ($user_uuids as $k => $v){
                    $role_user[$k]['user_uuid'] = $v;
                    $role_user[$k]['role_uuid'] = $uuid;
                    $role_user[$k]['create_time'] = $date;
                    $role_user[$k]['create_by'] = $currentUser['name'];
                }
            }
            $type_arr = [];
            if(!empty($params['type'])){
                $type_arr = explode(',',$params['type']);
            }
            if (count($type_arr)  == 1){
               if ($type_arr[0] == 1){
                   if (empty($params['department_uuid'])){
                       throw new Exception('参数错误');
                   }
                   // 循环验证是否是末级部门
                   $check_department_last = $this->checkDepartemntLast($params['department_uuid']);
                   // 组装数据
                   $role_data = $this->disposeData($check_department_last,$params,1,$uuid);
               }

            }
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
        return true;
    }
}