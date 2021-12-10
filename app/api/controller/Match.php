<?php
/**
 * Created by PengJu
 * User: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/11/16/13:50
 */

namespace app\api\controller;


use app\common\lib\Show;
use think\App;
use app\common\business\Match as MatchBus;
use think\Exception;

class Match extends ApiBaseController
{
    public $logic;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->logic = new MatchBus();
    }
    // 获取编码
    public function getCode(){
        $code = $this->logic->getCode(['type' => 1]);
        return $code;
    }

    public function add(){
        try {
            $params = $this->request->param();
            $res = $this->logic->add($params,$this->currentUser);
            if (!$res){
                throw new Exception('新增失败');
            }
        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success([],'OK');
    }

    public function lists(){
        try {
            $start_time = $this->request->post('start_date');
            $end_time = $this->request->post('end_date');
            $project = $this->request->post('match_project_uuid');
            $page = $this->request->post('page',1);
            $page_size = $this->request->post('page_size',15);
            $where = [];
            if (!empty($start_time) && !empty($end_time)){
                $where['start_date'] = ['>=',$start_time];
                $where['end_date'] = ['<=',$end_time];
            }elseif (!empty($start_time)){
                $where['start_date'] = ['>=',$start_time];
            }elseif (!empty($end_time)){
                $where['end_date'] = ['<=',$end_time];
            }
            if (!empty($project)){
                $where['match_project_uuid'] = $project;
            }
            $field = ['uuid,name,code,match_project_name,start_date,end_date,quantity,status'];
            $order = ['status' => 'asc','start_date' => 'desc'];
            $lists = $this->logic->lists($field,$page,$page_size,$where,$order);
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
        return Show::success();
    }
    // 文件上传，暂时先不做
    public function uploadFile(){
        $annex = $this->request->file('annex');
    }
    // 根据赛程获取参赛人员
    public function matchStaff(){
        try {
            $match_uuid = $this->request->post('match_uuid');
            $sex_type = $this->request->post('sex_type');
            $res = $this->logic->matchStaff($match_uuid,$sex_type);
        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success($res,'OK');
    }
    public function twoProject(){
        try {
            $one_project_uuid = $this->request->post('one_project_uuid');
            $sex_type = $this->request->post('sex_type');
            $res = $this->logic->twoProject($one_project_uuid,$sex_type);
        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success($res,'OK');
    }

    public function tenProject(){
        try {
            $two_project_uuid = $this->request->post('two_project_uuid');
            if (empty($two_project_uuid)){
                throw new Exception('参数错误');
            }
            $res = $this->logic->tenProject($two_project_uuid);
        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success($res,'OK');
    }
}