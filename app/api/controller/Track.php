<?php
/**
 * Created by PengJu
 * User: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/12/13/14:17
 */

namespace app\api\controller;


use app\common\lib\Show;
use think\App;
use app\common\business\Track as TrackBus;
use think\Exception;

class Track extends ApiBaseController
{
    public $logic;
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->logic = new TrackBus();
    }

    public function add(){
        try {
            $params = $this->request->post();
            // 此处应该有validate机制验证，先省略...
            $add = $this->logic->add($params,$this->currentUser);
            if (!$add){
                throw new Exception('添加失败');
            }
        }catch (Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success([],'添加成功');
    }
    public function lists(){
        $page = $this->request->post('page',1);
        $page_size = $this->request->post('page_size',10);
        $search_k = $this->request->post('search_k');
        $search_v = $this->request->post('search_v');
        $start_date = $this->request->post('start_date');
        $end_date = $this->request->post('end_date');
        $department_uuid = $this->request->post('department_uuid');
        if (empty($department_uuid)){
            return Show::error('请选择部门');
        }
        $data_permission = $this->getUserOwnDataDetail($department_uuid);
        // 根据传过来得部门uuid获取其子集
        $children_department_uuids = $this->logic->getChildrenDepartmentByUuid($department_uuid);

        if ($data_permission['data_permission']) {
            $children_department_uuids = array_intersect($data_permission['department'],$children_department_uuids);
        }

        // 根据部门，获取其部门下相关联的人员
        $staff_uuids = $this->logic->getStaffUuidByDepartmentUuid($children_department_uuids);

        if ($data_permission['data_permission'] && $data_permission['data_flag']) {
            $staff_uuids = array_intersect($staff_uuids,$data_permission['staff']);
        }
        $where['a.department_uuid'] = $children_department_uuids;
        if (!empty($start_date) && !empty($end_date)){
            $where['a.start_date'] = ['elt',$start_date];
            $where['a.end_date'] = ['egt',$end_date];
        }elseif(!empty($start_date)){
            $where['a.start_date'] = ['elt',$start_date];
            $where['a.end_date'] = ['egt',$end_date];
        }elseif (!empty($end_date)){
            $where['a.start_date'] = ['elt',$end_date];
            $where['a.end_date'] = ['egt',$end_date];
        }
        if (!empty($search_k) && !empty($search_v)) {
            $where["{$search_k}"] = ['like',"%{$search_v}%"];
        }
        $lists = $this->logic->lists($where,$page,$page_size,$staff_uuids);

        return Show::success($lists,'OK');
    }
    public function update(){
        try {
            $params = $this->request->post();
            // validata验证，暂时不写
            $updata = $this->logic->updata($params,$this->currentUser);
            if (!$updata){
                throw new Exception('修改失败');
            }
        }catch (Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success([],'OK');
    }
    public function del(){
        try {
            $uuid = $this->request->post('uuid');
            if (empty($uuid)){
                throw new Exception('参数错误');
            }
            $del = $this->logic->del($uuid);
            if (!$del) {
                throw new Exception('删除失败');
            }
        }catch (Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success([],'OK');
    }

    // 查看单条记录
    public function info(){
        try {
            $uuid = $this->request->post('uuid');
            if (empty($uuid)) {
                throw new Exception('参数错误');
            }
            $info = $this->logic->info($uuid);
        }catch (Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success([],'OK');
    }

    public function trackQuery(){
        $page = $this->request->post('page',1);
        $page_size = $this->request->post('page_size',10);
        $search_k = $this->request->post('search_k');
        $search_v = $this->request->post('search_v');
        $department_uuid = $this->request->post('department_uuid');
        if (empty($department_uuid)){
            return Show::error('请选择部门');
        }
        $data_permission = $this->getUserOwnDataDetail($department_uuid);
        // 根据传过来得部门uuid获取其子集
        $children_department_uuids = $this->logic->getChildrenDepartmentByUuid($department_uuid);
        if ($data_permission['data_permission']) {
            $children_department_uuids = array_intersect($data_permission['department'],$children_department_uuids);
        }

        $staff_uuids = $this->logic->getStaffUuidByDepartmentUuid($children_department_uuids);

        if ($data_permission['data_permission'] && $data_permission['data_flag']) {
            $staff_uuids = array_intersect($staff_uuids,$data_permission['staff']);
        }
        $where['s.uuid'] = $staff_uuids;
        if (!empty($search_k) && !empty($search_v)) {
            $where["s.". "{$search_k}" ] = ['like',"%{$search_v}%"];
        }
        $order = ['ts.create_time' => 'desc'];
        $fields = [
            's.uuid, s.name, s.personnel_umber, s.sex, s.mobile, s.sport_event_uuid, s.sport_uuid, s.coach_name, l.name as sport_type, ts.create_time'
        ];
        $track_query = $this->logic->trackQuery($where,$page,$page_size,$order,$fields);
        return Show::success($track_query);exit();
    }

    public function trackInfo(){
        $staff_uuid = $this->request->post('staff_uuid');
        $search_date = $this->request->post('search_date');
        $page = $this->request->post('page',1);
        if (empty($staff_uuid)){
            return Show::error('参数错误');
        }
        $where = [];
        if (!empty($search_date)){
            $where[] = ['start_date','<=',$search_date];
            $where[] = ['end_date','>=',$search_date];
            //$search_date = 2021-12-15
            // 2021-12-08 <= $search_date && 2021-12-16 >= $search_date
        }
        $data = $this->logic->trackInfo($staff_uuid,$where,$page);
        return Show::success($data,'OK');
    }
}