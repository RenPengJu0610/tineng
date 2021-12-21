<?php
/**
 * Created by PengJu
 * User: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/11/16/14:09
 */

namespace app\common\business;


use think\Exception;
use app\common\model\mysql\Match as MatchModel;
use app\common\model\mysql\Department as DepartmentModel;
use app\common\model\mysql\Annex as AnnexModel;
use app\common\model\mysql\Track as TrackModel;
use app\common\model\mysql\MatchProject as MatchProjectModel;
use think\facade\Db;

class Match extends BusBase
{
    public $model;
    public $trackModel;
    public $departmentModel;
    public $annexModel;
    public $matchProjectModel;
    public function __construct(){
        $this->model = new MatchModel();
        $this->departmentModel = new DepartmentModel();
        $this->annexModel = new AnnexModel();
        $this->trackModel = new TrackModel();
        $this->matchProjectModel = new MatchProjectModel();
    }
    public function getCode($param,$step = 1){
        try {
            if (!isset($param['type'])){
                throw new Exception('参数错误');
            }
            $max = [];
            $prefix = '';
            switch ($param['type']){
                case 1:
                    $prefix = 'SC';
                    $max = $this->model->field('max(right(code,4)) code')->select();
                    break;
                case 2:
                    $prefix = 'XZ';
                    $max = $this->trackModel->field('max(right(code,4)) code')->select();
            }
            if (!empty($max)){
                $max = $max->toArray();
            }
            if (empty($max[0]['code'])){
                return $code = '0001';
            }else{
                $code = str_pad(intval($max[0]['code']) + $step,4,0,STR_PAD_LEFT);
            }
            $year = date('Y');
            return $prefix.$year.$code;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }

    }
    public function add($params,$currentUser){
        try {
            // 验证code编码唯一性
            if(isset($params['code'])){
                $step = 1;
                $codeCount = $this->model->inquiryCount(['code' => $params['code']]);
                    while ($codeCount > 0){
                        $code = $this->getCode(['type' => 1]);
                        $codeCount = $this->model->inquiryCount(['code' => $code],$step);
                        if ($codeCount){
                            $step++;
                        }
                    }
                    if(!empty($code)){
                        $params['code'] = $code;
                    }
                }
            // 验证是否是末级部门
            if (isset($params['department_uuid_str'])){
                $department_uuid_arr = explode(',',$params['department_uuid_str']);
                $checkDepartment = $this->checkDepartment($department_uuid_arr);
            }
            $params['uuid'] = guid();
            // 验证一级项目是否存在
            $checkProject = Db::name('match_project')->where(['uuid' => $params['match_project_uuid']])->find();

            if (!$checkProject){
                throw new Exception('项目不存在');
            }
            $params['quantity'] = count(explode(',',$params['staff_uuid_str']));
            $params['match_project_name'] = $checkProject['name'];
            $params['match_project_uuid'] = $checkProject['uuid'];
            $params['create_by'] = $currentUser['name'];
            $date = date('Y-m-d H:i:s');
            $params['create_time'] = $date;
            $files = [];
            if (isset($params['file']) && !empty($params['file'])){
                $files = json_decode($params['file'],true);
                unset($params['file']);
                foreach ($files as $key => $value){
                    $files[$key]['uuid'] = guid();
                    $files[$key]['master_uuid'] = $params['uuid'];
                    $files[$key]['create_by'] = $currentUser['name'];
                    $files[$key]['create_time'] = $date;
                }
            }
        // 添加入库
            $this->model->startTrans();
            $matchAdd = $this->model->add($params);
            if (!$matchAdd){
                throw new Exception('新建赛程失败');
            }
            if (!empty($files)){
                $filesAdd = $this->annexModel->addAll($files);
                if (!$filesAdd){
                    throw new Exception('附件上传失败');
                }
            }
            $this->model->commit();
        }catch (\Exception $e){
            $this->model->rollback();
            throw new Exception($e->getMessage());
        }
        return true;
    }

    // 判断是否是末级部门
    public function checkDepartment($data){
        try {
            foreach ($data as $key => $value){
                if (empty($value)){
                    unset($data[$key]);
                }
                $read = $this->departmentModel->read($value);
                if (!$read){
                    throw new Exception('部门不存在');
                }
                if (empty($read['leaf_flag'])){ // 1：是叶子节点 0：不是叶子节点
                    $child = $this->departmentModel->single(['puuid' => $read['uuid']]);
                    if ($child){
                        throw new Exception('不是末级部门');
                    }
                }
            }
            return $data;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function lists($field,$page,$page_size,$where,$order){
        $lists = $this->model->inquiry($where,$page,$page_size,$field,$order);
        // 赛程是有状态的，在另一个项目中做过一些判断，暂时先不写
        return $lists;
    }

    public function matchStaff($match_uuid,$sex_type){
        try {
            if (empty($match_uuid)){
                throw new Exception('参数错误');
            }
            $matchInfo = $this->model->read($match_uuid);
            if (!$matchInfo){
                throw new Exception('赛程不存在');
            }
            if (!empty($sex_type)){ // 1:男。2女。3男女混合
                if ($sex_type == 1){
                    $where['sex'] = [1];
                }elseif ($sex_type == 2){
                    $where['sex'] = [2];
                }else{
                    $where['sex'] = [1,2];
                }
            }
            $staff_uuids = explode(',',$matchInfo['staff_uuid_str']);
            if (count($staff_uuids) > 0){
                $where['uuid'] = $staff_uuids;
            }
            $res = Db::name('staff')->where($where)->select()->toArray();
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
        return $res;
    }

    public function twoProject($projectUuid,$sexType){
        try {
            if (empty($projectUuid) || empty($sexType)){
                throw new Exception('参数错误');
            }
            $project_parent = $this->matchProjectModel->read($projectUuid);
            if (empty($project_parent)){
                throw new Exception('一级项目不存在');
            }
            $twoProject = $this->matchProjectModel->inquiryAll(['puuid' => $project_parent['uuid'],'sex_type' => $sexType,'level' => 2]);
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
        return $twoProject;
    }
    public function tenProject($two_project_uuid){
        try {
            $read = $this->matchProjectModel->read($two_project_uuid);
            if ($read['special_flag'] != 1){
                throw new Exception($read['name'] . '不是全能项目');
            }
            $ten_project = $this->matchProjectModel->inquiryAll(['puuid' => $read['uuid'],'level' => 3]);
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
        return $ten_project;
    }
    public function fileUpload($annex){
        try {
            $data = [];
            $file_name = $annex->getOriginalName();
            $image_path = \think\facade\Filesystem::disk('public')->putFile('match',$annex);

        }catch (Exception $e){
            throw new Exception($e->getMessage());
        }
        return true;
    }
}