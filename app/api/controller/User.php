<?php
/**
 * Created by PengJu
 * User: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/10/27/15:18
 */

namespace app\api\controller;


use app\common\lib\Show;
use app\validate\User as UserValidate;
use app\common\business\User as UserBus;
use think\App;
use think\Exception;

class User extends ApiBaseController
{
    protected $validate;
    protected $logic;
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->validate = new UserValidate();
        $this->logic = new UserBus();

    }

    public function add(){
        try {
            $params = $this->request->post();
            if (!$this->validate->check($params)){
                throw new Exception($this->validate->getError());
            }
            $result = $this->logic->add($params,$this->currentUser);
            if (!$result){
                throw new Exception('添加失败');
            }
        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success([],'添加帐号成功');
    }
    public function update(){
        try {
            $params = $this->request->post();
            if ($this->validate->scene('update')->check($params)){
                return Show::error($this->validate->getError());
            }
            $res = $this->logic->update($params);
            if (!$res){
                return Show::error('修改失败');
            }
        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success([],'修改成功');
    }

    public function able(){
        try {
            $uuid = $this->request->post('uuid');
            $result = $this->logic->able($uuid);
            if (!$result){
                return Show::error('状态修改失败');
            }
        }catch (Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success([],'OK');
    }
    public function del(){
        try {
            $uuids = $this->request->post('uuids');
            $result = $this->logic->del($uuids);
            if (!$result){
                return Show::error('修改失败');
            }
        }catch(\Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success([],'OK');
    }
    public function restePwd(){
        try {
            $uuid = $this->request->post('uuid');
            $result = $this->logic->resetPwd($uuid);
            if (!$result){
                throw new Exception('重置密码失败');
            }
        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }
    }
    public function savePwd(){
        try {
            $uuid = $this->request->post('uuid');
            $old_pwd = $this->request->post('old_pwd');
            $new_pwd = $this->request->post('new_pwd');
            $affirm_pwd = $this->request->post('affirm_pwd');
            $result = $this->logic->savePwd($uuid,$old_pwd,$new_pwd,$affirm_pwd);
            if (!$result){
                throw new Exception('密码修改失败');
            }
        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success([],'OK');
    }
}
