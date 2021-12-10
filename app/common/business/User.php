<?php
/**
 * Created by PengJu
 * User: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/10/27/16:28
 */

namespace app\common\business;


use app\common\lib\Show;
use think\Exception;
use app\common\model\mysql\Department as DepartmentModel;
use app\common\model\mysql\User as UserModel;
use app\common\model\mysql\Station as StationModel;
use app\common\model\mysql\Role as RoleModel;
use app\common\model\mysql\RoleUser as RoleUserModel;
class User extends BusBase
{
    protected $departmentModel;
    protected $model;
    protected $StationModel;
    protected $roleModel;
    protected $roleUserModel;
    public function __construct(){
        $this->model = new UserModel();
        $this->roleUserModel = new RoleUserModel();
        $this->roleModel = new RoleModel();
        /*$this->departmentModel = new DepartmentModel();
        $this->StationModel = new StationModel();
        $this->roleModel = new RoleModel();*/
    }
    public function add($params,$curentUser){
        try {
            $user_uuid = guid();
            // 验证部门是否存在
            if (!empty($params['department_uuid'])){
                $department = $this->departmentModel->read($params['department_uuid']);
                if (!$department || !$department['status'] || $department['is_del']){
                    throw new Exception('部门不存在');
                }
            }
            // 验证岗位是否存在
            if(!empty($params['station_uuid'])){
                $station = $this->StationModel->read($params['station_uuid']);
                if (!$station || !$station['status'] || $station['is_del']){
                    throw new Exception('岗位不存在');
                }
            }
           // 验证帐号有效期
            if(empty($params['start_date']) || empty($params['end_time'])){
                $params['start_date'] = isset($params['start_date']) ? $params['start_date'] : date('Y-m-d H:00:00');
                $params['end_date'] = isset($params['end_date']) ? $params['end_date'] : date('Y-m-d H:00:00',strtotime('+1years',strtotime($params['start_date'])));
            }
            // 验证帐号的唯一性
           $accountCount = $this->model->inquiryCount(['account' => $params['account']]);
            if ($accountCount){
                throw new Exception('帐号已存在');
            }
            $date = date('Y-m-d H:i:s');
            $params['uuid'] = $user_uuid;
            $params['salt'] = get_salt();
            $pwd = get_password($params['pwd'],$params['salt']);
            $params['pwd'] = $pwd;
            $params['create_time'] = $date;
            $params['create_by'] = $curentUser['name'];
            $user_add = $this->model->add($params);
            if (!$user_add){
                throw new Exception('帐号创建失败');
            }
            // 帐号和角色关联
            if (!empty($params['role_uuids'])){
                $role_uuids = explode(',',$params['role_uuids']);
            }
            unset($params['role_uuids']);
            $role_user_data = [];
            if($role_uuids){
                foreach ($role_uuids as $role_uuid){
                    $role_user_data[] = ['user_uuid' => $user_uuid,'role_uuid' => $role_uuid,'create_time' => $date,'create_by' => $curentUser['name']];
                }
            }
            // 角色和用户表关联添加
           if ($role_user_data){
                $role_user_add = $this->roleUserModel->addAll($role_user_data);
                if (!$role_user_add){
                    throw new Exception('关联表添加失败');
                }
            }
            // 修改角色表中关联帐号数
            if ($role_uuids){
                $update_role = $this->roleModel->where(['uuid'=>$role_uuids])->inc('account_quantity',1)->update();
                if ($update_role){
                    throw new Exception('更新关联帐号数失败');
                }
            }
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
            return false;
        }
        return true;
    }

    public function update($data,$currentUser){
        try {
            // 第一步验证要修改的帐号是否存在
            $read = $this->model->read('uuid',$data['uuid']);
            if (!$read){
                throw new Exception('帐号不存在');
            }
            // 验证部门是否存在
            if (!empty($data['deparment_uuid'])) {
                $department_exists = $this->departmentModel->read('uuid',$data['department_uuid']);
                if (!$department_exists || !$department_exists['status'] || $department_exists['is_del']){
                    throw new Exception('部门不存在');
                }
            }
            if (!empty($data['station_uuid'])){
                $station_exists = $this->StationModel->read('uuid',$data['station_uuid']);
                if (!$station_exists || !$station_exists['status'] || $station_exists['is_del']){
                    throw new Exception('岗位不存在');
                }
            }
            if ($data['name'] != $read['name']){
                $name_exists = $this->model->inquiryCount(['name' => $data['name']]);
                if ($name_exists){
                    throw new Exception('名称已存在');
                }
            }
            // 处理帐号之前关联的角色包含数相减
            $old_user_role_uuids = $this->roleUserModel->inquiryColumn(['user_uuid'=>$read['uuid']],'role_id');
            if ($old_user_role_uuids){
                $role_update = $this->roleModel->where(['uuid',$old_user_role_uuids])->dec('account_quantity',1);
                if (!$role_update){
                    throw new Exception('帐号修改失败');
                }
            }
            $user_role_del = $this->roleUserModel->delByWhere(['uuid'=>$data['uuid']]);
            if ($user_role_del === false){
                throw new Exception('帐号角色关联表修改失败');
            }
            $role_uuids_data = [];
            if (!empty($data['role_uuids'])){
                $role_uuids_data = explode(',',$data['role_uuids']);
            }
            $user_update = $this->model->modify($data);
            if ($user_update === false){
                throw new Exception('修改失败');
            }
            $role_user_data = [];
            foreach ($role_uuids_data as $role_uuid){
                $role_user_data[] = [
                    'role_uuid' => $role_uuid,
                    'user_uuid' => $data['user_uuid'],
                    'create_time' => date('Y-m-d H-i-s'),
                    'create_by' => $currentUser['name']
                ];
            }
            if (!empty($role_uuids_data)){
                $role_user_add = $this->roleUserModel->insertAll($role_uuids_data);
                if (!$role_user_add){
                    throw new Exception('修改失败');
                }
            }

        if (!empty($role_uuids_data)){
            $role_user_update = $this->roleModel->where(['uuid',$role_uuids_data])->inc('account_quantity',1);
            if (!$role_user_update){
                throw new Exception('修改失败');
            }
        }
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
        return true;

    }
    // 修改状态
    public function able($uuid){
        try {
            $read = $this->model->read('uuid',$uuid);

            if (!$read){
                throw new Exception('要修改的数据不存在');
            }
            $status = 0; // 1 为开启 0为禁用
            if (!$read['status']){
                $status = 1;
            }
            $result = $this->model->modify($uuid,['status' =>$status]);
            if ($result === false){
                throw new  Exception('修改失败');
            }
        }catch(\Exception $e){
            throw new Exception($e->getMessage());
        }
        return true;
    }
    public function del($uuids){
        try {
            $this->model->startTrans();
            $uuid_arr = explode(',',$uuids);
            $role_users = $this->roleUserModel->inquiryAll(['user_uuid',$uuid_arr],[],['user_uuid','role_uuid']);
            foreach ($role_users as $role_user){
                $role_decre = $this->roleModel->where(['uuid',$role_user['role_uuid']])->dec('account_quantity',1);
                if ($role_decre === false){
                    throw new Exception('修改失败');
                }
            }
            // 删除角色和帐号关联表中的数据
            $role_user_del = $this->roleUserModel->delByWhere(['uuid',$uuid_arr]);
            if ($role_user_del === false){
                throw new Exception('删除关联表数据失败');
            }
            // 删除用户
            $user_del = $this->model->delByWhere(['uuid',$uuid_arr]);
            if ($user_del === false){
                throw new Exception('删除失败');
            }
            $this->model->commit();
        }catch (\Exception $e){
            $this->model->rollback();
            throw new Exception($e->getMessage());
        }
        return true;
    }
    public function resetPwd($uuid){
        try {
            $read = $this->model->read('uuid',$uuid);
            if (!$read){
                throw new Exception('帐号不存在');
            }
            $initiaPwd = config('status.initiapwd');
            $pwd = get_password($initiaPwd,$read['salf']);
            $result = $this->model->modify($uuid,['pwd' => $pwd]);

            if ($result === false){
                throw new Exception('重置密码失败');
            }

        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
        return true;

    }

    public function savePwd($uuid,$old_pwd,$new_pwd,$affirm_pwd){
        try {
            $user = $this->model->read($uuid);
            if (!$user){
                throw new Exception('参数错误');
            }
            if (get_password($old_pwd,$user['salt']) != $user['pwd']){
                throw new Exception('帐号或旧密码不正确');
            }
            if ($new_pwd != $affirm_pwd){
                throw new Exception('两次输入密码不一致');
            }
            $salt = get_salt();
            $pwd  = get_password($new_pwd,$salt);
            $result = $this->model->modify($uuid,['pwd' => $pwd,'salt' => $salt]);
            if ($result === false){
                throw new Exception('修改失败');
            }
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
        return true;
    }

}