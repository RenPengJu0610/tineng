<?php
/**
 * Created by PengJu
 * User: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/12/6/15:10
 */

namespace app\common\business;


use app\common\lib\Show;
use think\Exception;
use app\common\model\mysql\Department as DepartmentModel;
use app\common\model\mysql\StaffDepartment as StaffDepartmentModel;
class Department extends BusBase
{
    public $model;
    public $staffDepatmentModel;
    public function __construct(){
        $this->model = new DepartmentModel();
        $this->staffDepatmentModel = new StaffDepartmentModel();
    }
    public function add($params,$currentUser){
        try {
            // 验证名称是否存在
            $name_exists = $this->model->inquiryCount(['name' => $params['name']]);
            if ($name_exists){
                throw new Exception('名称已存在');
            }
            // 生成UUID
            $uuid = guid();
            if ($params['puuid'] != 'classA'){
                // 验证父级是否存在
                $parent_department = $this->model->read($params['puuid']);
                if (!$parent_department){
                    throw new Exception('父级不存在');
                }
                if (!$this->model->canAddDepartment($params['puuid'])){
                    throw new Exception('此部门下已有组织人员');
                }
                $params['department_path'] = trim($parent_department['department_path'] . '/' . trim($parent_department['uuid'],'/'),'/');
            }else{
                $params['puuid'] = 0;
            }
            $params['uuid'] = $uuid;
            $params['create_time'] = date('Y-m-d H:i:s');
            $params['create_by'] = $currentUser['name'];
            $result = $this->model->add($params);
            if (!$result){
                throw new Exception('添加失败');
            }
        }catch (Exception $e){
            throw new Exception($e->getMessage());
        }
        return true;
    }

    public function update($params,$currentUser){
        try {
            // 判断uuid是否存在
            if (empty($params['uuid'])){
                throw new Exception('参数错误');
            }
            $uuid = $params['uuid'];
            unset($params['uuid']);
            // 验证要修改的数据是否存在
            $read = $this->model->read($uuid);
            if (!$read){
                throw new Exception('要修改的数据不存在');
            }
            // 判断是否要修改名称，如果要修改名称则验证其唯一性
            if ($params['name'] != $read['name']){
                $name_exists = $this->model->inquiryCount(['name' => $params['name']]);
                if ($name_exists){
                    throw new Exception('名称已存在');
                }
            }
            $children_res = [];
            if ($params['puuid'] != $read['puuid']){
                // 验证要修改的父级是否存在
                // 获取其子集的uuid
                $children_res = $this->getChildrenUuid($uuid);
                foreach ($children_res as $key => $value){
                    $level = count(explode('/',$value['department_path'])) + 1;
                    $children_res[$key]['level'] = $level;
                }
                array_multisort(array_column($children_res,'level'),SORT_ASC,$children_res);
                $parent_info = $this->model->read($params['puuid']);
                if ($parent_info){
                    $params['department_path'] = trim($parent_info['department_path'] . '/' . trim($parent_info['uuid'],'/'),'/');
                }else{
                    $params['department_path'] = '';
                    $params['puuid'] = 0;
                }
            }
            $this->model->startTrans();
            $update = $this->model->modify($uuid,$params);
            if ($update === false){
                throw new Exception('修改失败');
            }
            foreach ($children_res as $v){
                $parent = $this->model->read($v['puuid']);
                $up = $this->model->modify($v['uuid'],['department_path' => trim($parent['department_path'] . '/' .  trim($parent['uuid'],'/'),'/')]);
                if ($up === false){
                    throw new Exception('子类路径修改失败');
                }
            }
            $this->model->commit();
        }catch (Exception $e){
            $this->model->rollback();
            throw new Exception($e->getMessage());
        }
        return true;
    }
    // 根据UUID获取其子集
    public function getChildrenUuid($uuid){
        $children_uuid = [];
        if ($uuid){
            $children_uuid = $this->model->where('department_path','like',"%{$uuid}%")->select()->toArray();
        }
        return $children_uuid;
    }

    public function del($uuid){
        try {
            // 判断要删除的数据是否存在
            $read = $this->model->read($uuid);
            if (!$read){
                throw new Exception('数据不存在');
            }
            if ($read['leaf_flag']){
                throw new Exception('该部门下有人员，禁止删除');
            }
            // 判断要删除的数据下，是否有子类
            $children = $this->model->inquiryCount(['puuid' => $uuid]);
            if ($children){
                throw new Exception('存在子类，禁止删除');
            }
            $del = $this->model->delByWhere(['uuid' => $uuid]);
            if ($del === false){
                throw new Exception('删除失败');
            }
        }catch (Exception $e){
            throw new Exception($e->getMessage());
        }
        return true;
    }

    public function able($uuid){
        try {
            // 查看要修改的数据是否存在
            $read = $this->model->read($uuid);
            if (!$read){
                throw new Exception('数据不存在');
            }
            // 关闭需要关闭其下面的所有子类，开启则把其相对应的父级开启 status = 0 停用 status = 1 启用
            $status = 0;
            $uuids = [];
            if (!$read['status']){ // status == 0 时，说明是要开启
                $status = 1;
                // 取出其父级
                if(!empty($read['department_path'])){
                    $uuids = explode('/',$read['department_path']);
                }
                $uuids[] = $uuid;
            }else{ // 关闭则把其相对应的子类都关闭
                // 根据uuid 获取对应的子类
                $childrentRes = $this->getChildrenUuid($uuid);
                // 判断其子类下是否有关联的人员，如果有，则不让其关闭
                foreach ($childrentRes as $v){
                    if ($v['leaf_flag'] == 1){
                        throw new Exception("{$v['name']}" . '存在人员，不能关闭');
                    }
                    $uuids[] = $v['uuid'];
                }
                $uuids[] = $uuid;
            }
            $able = $this->model->modifyWhere(['uuid' => $uuids],['status' => $status]);
            if ($able === false){
                throw new Exception('状态修改失败');
            }
        }catch (Exception $e){
            throw new Exception($e->getMessage());
        }
        return Show::success([],'OK');
    }

    /**
     * 查看单条记录
     * @param $uuid
     * @return \app\common\model\mysql\BaseModel|array|\think\Model|null
     * @date 2021/12/10/10:31
     * @author RenPengJu
     */
    public function info($uuid){
        $read = $this->model->read($uuid);
        if (!$read){
            $read = [];
        }
        return $read;
    }


}