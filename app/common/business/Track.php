<?php
/**
 * Created by PengJu
 * User: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/12/13/14:18
 */

namespace app\common\business;

use app\common\model\mysql\Track as TrackModel;
use app\common\model\mysql\TrackStaff as TrackStaffModel;
use app\common\model\mysql\Department;
use app\common\model\mysql\Template as TemplateModel;
use app\common\model\mysql\StaffDepartment as StaffDepartmentModel;
use app\common\model\mysql\Staff as StaffModel;
use app\common\model\mysql\Sport as SportModel;
use app\common\business\Match;
use think\Exception;
use think\facade\Db;

class Track extends BusBase
{
    public $model;
    public $TrackStaffModel;
    public $departmentModel;
    public $templateModel;
    public $staffDepartmentModel;
    public $staffModel;
    public $sportModel;
    public function __construct(){
        $this->model = new TrackModel();
        $this->TrackStaffModel = new TrackStaffModel();
        $this->departmentModel = new Department();
        $this->templateModel = new TemplateModel();
        $this->staffDepartmentModel = new StaffDepartmentModel();
        $this->staffModel = new StaffModel();
        $this->sportModel = new SportModel();
    }
    public function lists($where,$page = 15,$page_size = 1,$staff_arr = []){
        $staff_str = implode(',',$staff_arr);
        // 建立子查询
        $querySql = $this->TrackStaffModel
                    ->where(['staff_uuid' => $staff_arr])
                    ->field("track_uuid,sum(if(FIND_IN_SET(staff_uuid,'{$staff_str}'),1,0)) num")
                    ->group("track_uuid")
                    ->buildSql();

        $tracks = Db::name('track')
                    ->alias('a')
                    ->join("{$querySql} b",'a.uuid = b.track_uuid','left')
                    ->field('a.*')
                    ->where($where)
                    ->where('a.staff_quantity = b.num')
                    ->page($page,$page_size)
                    ->select();

        $count = Db::name('track')
                    ->alias('a')
                    ->join("{$querySql} b",'a.uuid = b.track_uuid','left')
                    ->field('a.*')
                    ->where($where)
                    ->where('a.staff_quantity = b.num')
                    ->count();
        $list['tracks'] = $tracks;
        $list['count'] = $count;

    }
    public function add($params,$currentUser){
        try {
            $this->model->startTrans();
            // 验证code码
            if (isset($params['code'])){
                $step = 1;
                $code = '';
                $count = $this->model->inquiryCount(['code' => $params['code']]);
                while ($count > 0){
                    $code = (new Match())->getCode(['type' => 2],$step);
                    $count = $this->model->inquiryCount(['code' => $code]);
                    if ($count){
                        $step++;
                    }
                }
                $params['code'] = empty($code) ? $params['code'] : $code;
            }
            // 检测是否是末级部门
            $this->checkDepartmentLast($params['department_uuid']);
            // 查询相关的人员在时间上是否有冲突
            $where[] = ['a.start_date','<=',$params['start_date']];
            $where[] = ['a.end_date','>=',$params['start_date']];
            $staff_arr = explode(',',$params['staff_uuid']);
            foreach ($staff_arr as $staff_uuid){
                $staff_track = Db::name('track_staff')
                    ->alias('a')
                    ->join('staff b','a.staff_uuid = b.uuid','left')
                    ->where(['a.staff_uuid' => $staff_uuid])
                    ->where($where)
                    ->field('a.end_date,a.start_date,a.address,a.staff_uuid,a.track_uuid,b.name')
                    ->order(['a.end_date' => 'desc'])
                    ->select()->toArray();
                if ($staff_track){
                    throw new Exception($staff_track[0]['name']. "在" . ' '.$staff_track[0]['start_date'] . '--' . $staff_track[0]['end_date'] .' '. "时间段内，还在" . $staff_track[0]['address'] . "呢，请重新输入开始时间");
                }
            }
            /*foreach ($staff_arr as $staff_uuid){
                $staff_track = Db::name('track_staff')
                                    ->alias('a')
                                    ->join('staff b','a.staff_uuid = b.uuid','left')
                                    ->where(['a.staff_uuid' => $staff_uuid])
                                    ->field('a.end_date,a.start_date,a.address,a.staff_uuid,a.track_uuid,b.name')
                                    ->order(['a.end_date' => 'desc'])
                                    ->select()->toArray();

                if ($params['start_date'] < $staff_track[0]['end_date']){
                    throw new Exception($staff_track[0]['name']. "在" . ' '.$staff_track[0]['start_date'] . '--' . $staff_track[0]['end_date'] .' '. "时间段内，还在" . $staff_track[0]['address'] . "呢，请重新输入");
                }
            }*/
            $params['uuid'] = guid();
            $params['staff_uuid_str'] = $params['staff_uuid'];
            unset($params['staff_uuid']);
            $params['create_by'] = $currentUser['name'];
            $params['create_time'] = date('Y-m-d H:i:s');
            $params['coach_name_str'] = isset($params['coach_name_str']) ? $params['coach_name_str'] : '';
            $params['staff_quantity'] = count($staff_arr);
            $add_track = $this->model->add($params);
            if (!$add_track){
                throw new Exception('添加失败');
            }
            $track_staff_data = [];
            foreach ($staff_arr as $key => $staff_uuid){
                $track_staff_data[$key]['staff_uuid'] = $staff_uuid;
                $track_staff_data[$key]['track_uuid'] = $params['uuid'];
                $track_staff_data[$key]['department_uuid'] = $params['department_uuid'];
                $track_staff_data[$key]['start_date'] = $params['start_date'];
                $track_staff_data[$key]['end_date'] = $params['end_date'];
                $track_staff_data[$key]['address'] = $params['address'];
                $track_staff_data[$key]['demo'] = $params['demo'];
                $track_staff_data[$key]['create_time'] = $params['create_time'];
                $track_staff_data[$key]['create_by'] = $params['create_by'];
            }
            $track_staff_add = $this->TrackStaffModel->addAll($track_staff_data);
            if (!$track_staff_add){
                throw new Exception('添加失败');
            }
            $template_add = $this->templateModel->modifyWhere(['uuid' => $params['template_uuid']],['is_used' => 1]);
            if ($template_add === false){
                throw new Exception('添加失败');
            }
            $this->model->commit();
        }catch (Exception $e){
            $this->model->rollback();
            throw new Exception($e->getMessage());
        }
        return true;
    }

    public function checkDepartmentLast($department_uuids){
        $department_uuids_arr = explode(',',$department_uuids);
        // 循环判断是否是末级部门
        foreach ($department_uuids_arr as $department_uuid){
            $read = $this->departmentModel->read($department_uuid);
            if (!$read['leaf_flag']){ // leaf_flag == 1时不做判断
                // 判断该部门下是否有子类
                $children = $this->departmentModel->read(['puuid' => $department_uuid]);
                if ($children){
                    throw new Exception($read['name'] . '不是末级部门');
                }
            }
        }
        return true;
    }
    public function updata($params,$currentUser){
        try {
            if (empty($params['uuid'])){
                throw new Exception('参数错误');
            }
            unset($params['code']);
            // 查询要修改的数据是否存在
            $read = $this->model->read($params['uuid']);
            if (!$read){
                throw new Exception('要修改的数据不存在');
            }
            // 检测是否是末级部门
            $this->checkDepartmentLast($params['department_uuid']);
            // 查询相关的人员在时间上是否有冲突
            if ($read['start_date'] != $params['start_date'] || $read['end_date'] != $params['end_date']){
                $where[] = ['a.start_date','<=',$params['start_date']];
                $where[] = ['a.end_date','>=',$params['start_date']];
                $staff_arr = explode(',',$params['staff_uuid']);
                foreach ($staff_arr as $staff_uuid){
                    $staff_track = Db::name('track_staff')
                        ->alias('a')
                        ->join('staff b','a.staff_uuid = b.uuid','left')
                        ->where(['a.staff_uuid' => $staff_uuid])
                        ->where($where)
                        ->field('a.end_date,a.start_date,a.address,a.staff_uuid,a.track_uuid,b.name')
                        ->order(['a.end_date' => 'desc'])
                        ->select()->toArray();
                    if ($staff_track){
                        throw new Exception($staff_track[0]['name']. "在" . ' '.$staff_track[0]['start_date'] . '--' . $staff_track[0]['end_date'] .' '. "时间段内，还在" . $staff_track[0]['address'] . "呢，请重新输入开始时间");
                    }
                }
            }
            $this->model->startTrans();
            $uuid = $params['uuid'];
            unset($params['uuid']);
            $params['staff_uuid_str'] = $params['staff_uuid'];
            unset($params['staff_uuid']);
            $params['create_by'] = $currentUser['name'];
            $params['create_time'] = date('Y-m-d H:i:s');
            $params['coach_name_str'] = isset($params['coach_name_str']) ? $params['coach_name_str'] : '';
            $params['staff_quantity'] = count($staff_arr);
            $update_track = $this->model->modifyWhere(['uuid' => $uuid],$params);
            if (!$update_track){
                throw new Exception('修改失败');
            }
            // 需要先删除行踪人员关联表中的数据
            $del = $this->TrackStaffModel->delByWhere(['track_uuid' => $uuid]);
            if ($del === false){
                throw new Exception('删除失败');
            }
            $track_staff_data = [];
            foreach ($staff_arr as $key => $staff_uuid){
                $track_staff_data[$key]['staff_uuid'] = $staff_uuid;
                $track_staff_data[$key]['track_uuid'] = $uuid;
                $track_staff_data[$key]['department_uuid'] = $params['department_uuid'];
                $track_staff_data[$key]['start_date'] = $params['start_date'];
                $track_staff_data[$key]['end_date'] = $params['end_date'];
                $track_staff_data[$key]['address'] = $params['address'];
                $track_staff_data[$key]['demo'] = $params['demo'];
                $track_staff_data[$key]['create_time'] = $params['create_time'];
                $track_staff_data[$key]['create_by'] = $params['create_by'];
            }
            $track_staff_add = $this->TrackStaffModel->addAll($track_staff_data);
            if (!$track_staff_add){
                throw new Exception('修改失败');
            }
            $this->model->commit();
        }catch (Exception $e){
            $this->model->rollback();
            throw new Exception($e->getMessage());
        }
        return true;
    }
    public function info($uuid){
        try {
            $read = $this->model->read($uuid);
            if (!$read){
                throw new Exception('数据不存在');
            }
            $staff_uuids = explode(',',$read['staff_uuid_str']);
            $staff_name = $this->staffModel->inquiryColumn(['uuid' => $staff_uuids],'name');
            $read['staff_name_str'] = implode(',',$staff_name);
            return $read;
        }catch (Exception $e){
            throw new Exception($e->getMessage());
        }
    }
    public function del($uuids){
        try {
            // 查询要删除的数据是否存在
            $uuid_arr = explode(',',$uuids);
            foreach ($uuid_arr as $uuid){
                $read = $this->model->read($uuid);
                if (!$read){
                    throw new Exception('要删除的数据不存在');
                }
            }

            $this->model->startTrans();
            $track_del = $this->model->where(['uuid' => $uuid_arr])->delete();
            if ($track_del === false){
                throw new Exception('删除失败');
            }
            $track_staff_del = $this->TrackStaffModel->where(['track_uuid' => $uuid_arr])->delete();
            if ($track_staff_del === false){
                throw new Exception('删除失败');
            }
            $this->model->commit();
        }catch (Exception $e){
            $this->model->rollback();
            throw new Exception($e->getMessage());
        }
        return true;
    }
    /**
     * 根据uuid获取其所有的子集
     * @param $uuid
     * @return array
     * @date 2021/12/20/13:10
     * @author RenPengJu
     */
    public function getChildrenDepartmentByUuid($uuid){
        return $this->departmentModel->getChildrenUuids($uuid);
    }

    public function trackQuery($where,$page,$page_size,$order,$field){
        $sport_project = $this->sportModel->where('puuid','<>',0)->select()->toArray();
        $sport_project_arr = [];
        foreach ($sport_project as $value){
            $sport_project_arr[$value['uuid']] = $value;
        }
        $data = $this->model->trackQuery($where,$page,$page_size,$order,$field);
        $staff_data = [];
        foreach ($data['data'] as $datum){
            $staff_data[$datum['uuid']] = $datum;
        }
        $staff_deparment = $this->model->staffDepartment();

        $staff_deparment_arr = [];
        foreach ($staff_deparment as $val){
            $staff_deparment_arr[$val['staff_uuid']] = $val;
        }
        foreach ($staff_data as $key => $value) {
            if (array_key_exists($key,$staff_deparment_arr)){
                 $staff_data[$key]['deparment_name'] = $staff_deparment_arr[$key]['name'];
            }
            if (!empty($staff_data[$key]['sport_event_uuid'])){
                if (array_key_exists($staff_data[$key]['sport_event_uuid'],$sport_project_arr)){
                    $staff_data[$key]['sport_event_name'] = $sport_project_arr[$staff_data[$key]['sport_event_uuid']]['name'];
                }
            }
        }
       return $data['data'] = array_values($staff_data);
    }

    public function getStaffUuidByDepartmentUuid($department_uuid){
        $staff_uuids = $this->staffDepartmentModel->inquiryColumn(['department_uuid' => $department_uuid],'staff_uuid');
        return $staff_uuids;
    }
}