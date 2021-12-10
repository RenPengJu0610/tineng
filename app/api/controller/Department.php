<?php
/**
 * Created by PengJu
 * User: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/12/6/15:07
 */

namespace app\api\controller;


use app\common\lib\Show;
use think\App;
use think\Exception;
use app\common\business\Department as DepartmentLogic;
use app\common\model\mysql\RoleUser;
use app\common\model\mysql\RoleData;
use app\common\model\mysql\UserData;
use app\common\model\mysql\User;
use app\common\model\mysql\Department as DepartmentModel;
class Department extends ApiBaseController
{
    public $logic;
    public $roleUserModel;
    public $roleDataModel;
    public $userDataModel;
    public $model;
    public $userModel;
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->logic = new DepartmentLogic();
        $this->userDataModel = new UserData();
        $this->roleUserModel = new RoleUser();
        $this->roleDataModel = new RoleData();
        $this->model = new DepartmentModel();
        $this->userModel = new User();
    }

    /**
     * 列表
     * @date 2021/12/10/14:06
     * @author RenPengJu
     */
    public function lists(){
        $page = 1;
        $page_size = 99999;
        // 当前登录用户
        $user = $this->getCurrentUser();
        // 获取当前登录用户的权限信息
        $currentUserDataDatail = $this->getCurrentUserDataDatail($user['uuid']);
        $where = [];
        if ($currentUserDataDatail['data_permission']) {
            $where = ['uuid' => $currentUserDataDatail['department_uuid']];
        }
        $serach_k = $this->request->post('serach_k',null);
        $serach_v = $this->request->post('serach_v',null);
        if (!empty($serach_k) && !empty($serach_v)){
            $where[$serach_k] = ['like',"%{$serach_v}%"];
        }
        // 根据条件查询即可，过程过于简单，暂时未写
    }

    /**
     * 获取当前登录用户的数据权限
     * @param $user
     * @param null $data_uuid
     * @date 2021/12/10/14:14
     * @author RenPengJu
     */
    public function getCurrentUserDataDatail($user_uuid,$data_uuid = null){
        $res = ['department_uuid' => [],'staff_uuid' => [],'data_permission' => 1];
        // 判断是否是超管登录
        $user = $this->userModel->read($user_uuid);
        if ($user['type'] == 0){
            $res['data_permission'] = 0;
            return $res;
        }
        // 根据用户uuid获取其角色的uuid
        $role_uuids = $this->roleUserModel->inquiryColumn(['user_uuid' => $user_uuid],'role_uuid');
        if (!$role_uuids){
            return $res;
        }
        $role_data = $this->roleDataModel->where(['role_uuid' => $role_uuids])->select()->toArray();
        $department_uuid = [];
        $staff_uuid = [];
        foreach ($role_data as $value){
            $department_uuid[] = $value['data_uuid'];
            $staff_uuid = array_merge($staff_uuid,explode(',',$value['staff_uuid_str']));
        }
        // 根据部门的uuid找到其父级
        $department_uuids = [];
        foreach ($department_uuid as $v){
            $read = $this->model->read($v);
            $department_uuids = array_merge($department_uuids,explode('/',trim($read['department_path'],'/')),[$v]);
        }
        $department_uuids = array_unique($department_uuids);
        $res['department_uuid'] = $department_uuids;
        $res['staff_uuid'] = $staff_uuid;
        return $res;
    }
    /**
     * 添加部门
     * @date 2021/12/6/15:07
     * @author RenPengJu
     */
    public function add(){
        try {
            $params = $this->request->post();
            $add = $this->logic->add($params,$this->currentUser);
            if (!$add){
                throw new Exception('添加失败');
            }
        }catch (Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success([],'OK');
    }

    /**
     * 修改部门
     * @date 2021/12/7/9:33
     * @author RenPengJu
     */
    public function update(){
        try {
            $params = $this->request->param();
            $update = $this->logic->update($params,$this->currentUser);
            if (!$update){
                throw new Exception('修改失败');
            }
        }catch (Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success([],'OK');
    }

    /**
     * 删除
     * @date 2021/12/7/15:57
     * @author RenPengJu
     */
    public function del(){
        try {
            $uuid = $this->request->post('uuid');
            if (empty($uuid)){
                throw new Exception('参数错误');
            }
            $del = $this->logic->del($uuid);
            if (!$del){
                throw new Exception('删除失败');
            }
        }catch (Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success([],'OK');
    }

    /**
     * 修改状态
     * @date 2021/12/7/16:15
     * @author RenPengJu
     */
    public function able(){
        try {
            $uuid = $this->request->post('uuid');
            if (!$uuid){
                throw new Exception('参数错误');
            }
            $able = $this->logic->able($uuid);
            if (!$able){
                throw new Exception('状态修改失败');
            }
        }catch (Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success([],'OK');
    }

    public function info(){
        try {
            $uuid = $this->request->post('uuid');
            if (empty($uuid)){
                throw new Exception('参数错误');
            }
            $info = $this->logic->info($uuid);
        }catch (Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success($info,'OK');
    }
}