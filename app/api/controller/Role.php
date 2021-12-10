<?php
/**
 * Created by PengJu
 * User: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/10/22/14:20
 */

namespace app\api\controller;


use app\common\lib\Show;
use think\App;
use app\common\business\Role as RoleBus;
use app\validate\Role as RoleValidate;
use think\Exception;

class Role extends ApiBaseController
{
    public $login;
    public $validate;
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->login = new RoleBus();
        $this->validate = new RoleValidate();
    }

    public function add(){
        $params = $this->request->post();
        try {
            if (!$this->validate->check($params)){
                throw new Exception($this->validate->getError());
            }
            $res = $this->login->add($params,$this->currentUser);
            if (!$res){
                return Show::error('添加失败');
            }
        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success([],'OK');
    }

    public function update(){
        $params = $this->request->post();
        try {
            if (!$this->validate->check($params)){
                throw new Exception($this->validate->getError());
            }
            $res = $this->login->update($params,$this->currentUser);
            if (!$res){
                return Show::error('修改失败');
            }
        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success([],'OK');
    }
    public function del(){
        try {
            $uuids = $this->request->post('uuids');
            if (empty($uuids)){
                throw new Exception('参数错误');
            }
            $res = $this->login->del($uuids);
            if (!$res){
                throw new Exception('修改失败');
            }
        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success([],'OK');
    }

    public function able(){
        try {
            $uuid = $this->request->post('uuid');
            if (empty($uuid)){
                throw new Exception('参数错误');
            }
            $res = $this->login->able($uuid);
            if (!$res){
                throw new Exception('状态修改失败');
            }
        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success([],'状态修改成功');
    }

    /**
     * 角色和帐号菜单部门等权限关联
     * @date 2021/11/26/9:42
     * @author RenPengJu
     */
    public function create(){
        try {
            $params = $this->request->post();
            $add = $this->login->create($params,$this->currentUser);
            if (!$add){
                throw new Exception('添加失败');
            }
        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success([],'OK');
    }
    public function functionalLists(){
        try {
            $uuid = $this->request->post('uuid');
            $res = $this->login->functionalLists($uuid);
        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success($res,'OK');

    }

    /**
     * 修改角色
     * @date 2021/12/3/16:58
     * @author RenPengJu
     */
    public function updateRole(){
        try {
            $params = $this->request->post();

            $update = $this->login->updateRole($params,$this->currentUser);

            if (!$update){
                throw new Exception('修改失败');
            }

        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success($update,'OK');

    }

}