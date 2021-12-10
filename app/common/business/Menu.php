<?php
/**
 * Created by PengJu
 * RoleUser: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/9/26/15:45
 */

namespace app\common\business;


use app\common\lib\Show;
use think\App;
use think\Exception;
use app\common\model\mysql\Menu as MenuModel;
use app\common\model\mysql\RoleMenu as RoleMenuModel;
class Menu extends BusBase
{
    public $model;
    public $RoleMenuModel;
    protected $type = [1 => '模块',2 => '菜单', 3 => '按钮'];
//'1:列表2:查3:增4:改5:删6:下载7:导入8:导出9:上传10:打印11:启/停用12:还原密码13:选择档案14:新建子类15:选择指标16:选择动作
//17:周18:月19:年20:批量新增日计划21:批量新增周计划22:日计划反馈23:新增周计划24:修改周计划25:删除周计划26:批量新增月计划27:批量新增年计划...31:上传体成分数据'
    protected $btn_type = [1 => '列表', 2 => '查', 3 => '增', 4 => '改', 5 => '删', 6 => '下载', 7 => '导入',
        8 => '导出', 9 => '上传', 10 => '打印', 11 => '启/停用', 12 => '还原密码', 13 => '选中档案', 14 => '新建子类',
        15 => '选择指标', 16 => '选择动作', 17 => '周', 18 => '月', 19 => '年', 20 => '批量新增日计划', 21 => '批量新增周计划',
        22 => '日计划反馈', 23 => '新增周计划', 24 => '修改周计划', 25 => '删除周计划', 26 => '批量新增月计划', 27 => '批量新增年计划'];
    public function __construct()
    {
        $this->model = new MenuModel();
        $this->RoleMenuModel = new RoleMenuModel();
    }

    public function add($params,$currentUser){
        $params['uuid'] = guid();
        $params['create_by'] =$currentUser['name'];
        $params['create_time'] = date('Y-m-d H:i:s');
        if (empty($params['filter']) && isset($params['uri'])){
            $params['filter'] = $params['uri'];
        }
        try {

            if (!in_array($params['type'], [1,2,3] )){
                throw new Exception('类型错误');
            }

            if (empty($params['puuid'])){
                $params['level']  = 1;
            }else{
                $result = $this->model->read($params['uuid']);
                if (!$result){
                    throw new Exception('父级不存在');
                }
                if (!$result['status']){
                    throw new Exception('父级菜单已经被禁用');
                }
                $params['level'] = $result['level'] + 1;
                $params['menu_path'] = trim(trim($result['menu_path'],'/') . '/' .$params['uuid'],'/' );
            }
            $res = $this->model->add($params);
            if (!$res){
                throw new Exception('添加失败');
            }
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
        return $res;
    }
    public function lists($where = [],$page,$page_size,$field = ['*'],$order = []){
        $list = $this->model->inquiry($where,$page,$page_size,$field,$order);
        foreach ($list as $k => $v){
            $list[$k]['type_name'] = empty($v['type']) ? "" : $this->type[$v['type']];
            $list[$k]['btn_type_name'] = empty($v['btn_type']) ? "" : $this->btn_type[$v['btn_type']];
        }
        return $list;
    }
    // 修改
    public function update($params){
        try {
            $uuid = $params['uuid'];
            $menu = $this->model->read($uuid);
            if (empty($menu)){
                throw new Exception('数据不存在');
            }
            if ($menu['puuid'] != $params['puuid']){
                if (!empty($params['puuid'])){

                    $parent_menu = $this->model->read($params['puuid']);
                    if (empty($parent_menu)){
                        throw new Exception('菜单不存在');
                    }
                    if (empty($parent_menu['status'])){
                        throw new Exception('菜单已禁用');
                    }
                    $params['level'] = intval($parent_menu['level']) + 1;
                    $params['menu_path'] = trim(trim($parent_menu['menu_path'],'/') . '/' . $parent_menu['uuid'] ,'/');
                }else{
                    $params['level'] = 0;
                    $params['menu_path'] = '';
                }
            }
            $res = $this->model->modify($uuid,$params);

            if ($res === false){

                throw new Exception('修改失败');
            }

        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
        return $res;
    }

    public function able($uuid){
        try {
            $read = $this->model->read($uuid);
            if (!$read){
                throw new Exception('数据不存在');
            }
            $this->model->startTrans();
            $status = 0;
            if (!$read['status']){
                // 如果要开启，则需要把该分类的父级都开启
                $status = 1;
                $uuids = $this->model->getParentUuids($uuid,true);
            }else{
                // 获取该菜单下的所有的子类
                $uuids = $this->model->getAllChildren('menu_path',$uuid,true);

                // 删除角色菜单表中的数据
                $role_del = $this->RoleMenuModel->where(['menu_uuid' =>  $uuids]);
                if ($role_del === false){
                    throw new Exception('删除角色菜单关联数据失败');
                }
            }
            $result = $this->model->modifyWhere(['uuid' => $uuids],$status);
            if ($result === false){
                throw new Exception('修改失败');
            }
            $this->model->commit();
        }catch (\Exception $e){
            $this->model->rollback();
            throw new Exception($e->getMessage());
        }
        return true;

    }
    public function del($uuid){
        try {
            $read = $this->model->read($uuid);
            if (!$read){
                return Show::error('数据不存在');
            }
            // 获取该菜单的所有子集包含自身
            $children_uuids = $this->model->getAllChildren('menu_path',$uuid);
            $this->model->startTrans();
            // 删除角色菜单关联的数据
            $role_menu_del = $this->RoleMenuModel->delByWhere(['menu_uuid' => $children_uuids]);

            if ($role_menu_del === false){
                throw new Exception('删除角色菜单表关联数据失败');
            }
            // 删除用户菜单关联数据
            $menu_del = $this->model->delByWhere(['uuid' => $children_uuids]);

            if ($menu_del === false){
                throw new Exception('删除菜单表数据失败');
            }

            $this->model->commit();
        }catch (\Exception $e){
            $this->model->rollback();
            throw new Exception($e->getMessage());
        }
        return $menu_del;
    }
}