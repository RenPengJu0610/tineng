<?php
/**
 * Created by PengJu
 * User: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/11/12/9:49
 */

namespace app\common\business;
use think\Exception;
use think\facade\Db;
use app\common\model\mysql\Menu;
use app\common\model\mysql\FavoriteMenu;
use app\common\model\mysql\Department;
use app\common\model\mysql\Staff;
class Home extends BusBase
{
    public $departmentModel;
    public $staff;
    public $menu;
    public function __construct()
    {
        $this->departmentModel = new Department();
        $this->staff = new Staff();
        $this->menu = new Menu();
    }

    public function lists($user){
        // 获取当前登录用户拥有的菜单权限
        $user_menu = $this->getUserByUuidMenu($user);
        $favorite_uuids =  (new Menu() )->inquiryColumn(['is_home' => 1,'status' => 1],'uuid');
        if ($user != 0 ){
           $favorite_uuids = array_intersect($user_menu['data'],$favorite_uuids);
        }
        $favorite_data = (new Menu())->inquiryAll(['uuid'=>$favorite_uuids],[],['uuid','favorite_name as name','0 as is_favorite','favorite_color','favorite_icon']);
        $favorite = (new FavoriteMenu())->inquiryColumn(['user_uuid' => $user['uuid']],'menu_uuid');
        if ($favorite){
            foreach ($favorite_data as $key => $val){
                if (in_array($val['uuid'],$favorite)){
                    $favorite_data[$key]['is_favorite'] = 1;
                }
            }
        }
        // 根据当前登录用户获取到该用户拥有的部门权限和人员权限
        $department = $this->getDepartmentByUserUuid($user);
        $where = [];
        if ($department){
            $where['department_uuid'] = $department['department'];
            $whereStaff['staff_uuid'] = $department['staff'];
        }
        $time = date('Y-m-d 00:00:00',strtotime('-2 month'));
        $dateTime = date('Y-m-01',strtotime('-2 month'));
        // 训练总结数
        $trainSummary = Db::name('train_summary')->where($where)->whereBetween('start_date',[$dateTime,date('Y-m-d')])->count();
        //训练计划总数
        $trainCount = Db::name('train_plan')->where($where)->whereBetween('start_date',[$dateTime,date('Y-m-d')])->count();
        //机能监控数
        $engineryCount = Db::name('enginery_monitor')->where($where)->whereBetween('test_date',[$dateTime,date('Y-m-d')])->count();
        // 医务记录数
        $mediclaCount = Db::name('medical_record')->where($whereStaff)->whereBetween('treat_time',[$dateTime,date('Y-m-d')])->count();

        $res = [
            'menu_list' =>   $favorite_data,
            'trainSummary' => $trainSummary,
            'trainCount' => $trainCount,
            'engineryCount' => $engineryCount,
            'mediclaCount' => $mediclaCount
        ];
        return $res;
    }
    public function getDepartmentByUserUuid($user){
        $res = [];
        if ($user['type'] != 0){ // 非超级管理员
            $data = Db::name('user_role')
                    ->alias('ru')
                    ->join('role_data rd','rd.role_uuid = ru.role_uuid')
                    ->where(['ru.user_uuid' => $user['uuid'],'type' => 1])
                    ->column('rd.data_uuid,rd.staff_uuid_str');
            $res = ['department' => [],'staff' => []];
            foreach ($data as $k => $v){
                $res['department'] = array_merge($res['department'],[$v['data_uuid']]);
                $res['staff'] = empty($v['staff_uuid_str']) ? $res['staff'] : array_merge($res['staff'],explode(',',$v['staff_uuid_str']));
            }
            $res['department'] = $this->getParentUuid($res['department']);
            return $res;
        }
        return $res;
    }

    public function getParentUuid($uuid){
        if (!$uuid){
            return [];
        }
        $departmentUuid = (new Department())->inquiryAll(['uuid'=>$uuid],[],'department_path');
        $departmentUuidArr = [];
        if ($departmentUuid){
            foreach ($departmentUuid as $v){
                $parentUuid = explode('/',trim($v['department_path'],'/'));
                $departmentUuidArr = array_merge($departmentUuidArr,$parentUuid);
            }
            return array_unique(array_merge($uuid,$departmentUuidArr));
        }
    }
    // 获取当前登录用户拥有的菜单权限
    public function getUserByUuidMenu($user){
        $data['type'] = 1;
        if ($user != 0){ // 不是超管，需要取出该帐号拥有的菜单权限
            $user_menu = Db::name('user_role')
                            ->alias('ur')
                            ->join('role_menu rm','ur.role_uuid = rm.role_uuid')
                            ->join('menu m','rm.menu_uuid = m.uuid')
                            ->where(['ur.user_uuid' => $user['uuid']])
                            ->column('m.puuid','m.uuid');
            $menuUuids = array_merge(array_keys($user_menu),array_values($user_menu));
            $data['data'] = array_unique($menuUuids);
            $data['type'] = 2;
        }
        return $data;
    }

    public function getTrainEnginery($start_time,$end_time,$department_uuids,$currentUser){
        $department_uuids_arrs = [];
        if ($currentUser['type'] != 0){ // 不是超管登录时，需要验证该用户拥有的数据权限
            if (!empty($department_uuids)){
                $department_uuids_arr = explode(',',$department_uuids);
                $department = $this->getDepartmentByUserUuid($currentUser);
                $department_uuids_arrs = array_intersect($department_uuids_arr,$department['department']);
            }
        }else{ // 如果是超管则不做任何验证
            if (!empty($department_uuids)){
                $department_uuids_arrs = explode(',',$department_uuids);
            }

        }
        $where = [];
        if ($department_uuids_arrs){
            $where['department_uuid'] = $department_uuids_arrs;
        }

        $train = Db::name('train_plan')->where($where)->whereBetween('start_date',[$start_time,$end_time])->order('start_date','asc')
                    ->group('start_date')->column('start_date as test_date,count(1) as num','start_date');

        $enginery= Db::name('enginery_monitor')->where($where)->whereBetween('test_date',[$start_time,$end_time])->order('test_date','asc')
                    ->group('test_date')->column('test_date,count(1) as num','test_date');
        $keys = array_unique(array_merge(array_keys($train),array_keys($enginery)));
        foreach ($keys as $key){
            if (empty($train[$key])){
                $train[$key]['test_date'] = $key;
                $train[$key]['num'] = 0;
            }
            if (empty($enginery[$key])){
                $enginery[$key]['test_date'] = $key;
                $enginery[$key]['num'] = 0;
            }
            if (isset($train[$key]['start_date'])){
                unset($train[$key]['start_date']);
            }
        }
         ksort($train);
         ksort($enginery);
        return ['train' => array_values($train),'enginery' => array_values($enginery)];
    }

    public function status($uuids,$currentUser){
        try {
            Db::startTrans();
            $uuid_arr = explode(',',$uuids);
            $favoriteDel = Db::name('favorite_menu')->where(['user_uuid',$uuid_arr])->delete();
            if ($favoriteDel === false){
                throw new Exception('修改失败');
            }
            $dataAll = [];
            foreach ($uuid_arr as $key => $uuid){
                $dataAll[$key]['user_uuid'] = $currentUser['uuid'];
                $dataAll[$key]['menu_uuid'] = $uuid;
                $dataAll[$key]['create_time'] = date('Y-m-d H:i:s');
            }
            $res = Db::name('favorite_menu')->insertAll($dataAll);
            if (!$res){
                throw new Exception('修改失败');
            }
            Db::commit();
        }catch (\Exception $e){
            Db::rollback();
            throw new Exception($e->getMessage());
        }
        return true;
    }
    public function homeDate(){
        $data = ['num_department' => 0,'num_staff' => 0,'num_staff_in_department' => 0,'num_staff_out_department' => 0,'staff_age' => [],'staff_department'=>[],'staff_sex' => [],'staff_sex_ratio' => []];
        // 统计运动队个数
        $num_department = $this->departmentModel->inquiryCount(['leaf_flag' => 1,'status' => 1,'is_show' => 1]);
        // 统计总人数
        $num_staff = $this->staff->inquiryCount(['is_show' => 1]);
        // 统计在队人数
        $num_staff_in_department = $this->staff->inquiryCount(['is_show' => 1,'is_out' => 0]);
        // 统计离队人数
        $num_staff_out_department = $this->staff->inquiryCount(['is_show' => 1,'is_out' => 1]);

        // 各运动队人员统计
        $where = [
            'c.is_show' => 1,
            'c.is_out' => 0,
            'a.leaf_flag' => 1,
            'a.status' => 1,
            'a.is_show' => 1
        ];
        // 各运动队人数统计
        $staff_department = Db::name('department')
                            ->alias('a')
                            ->join('staff_department b','a.uuid = b.department_uuid','left')
                            ->join('staff c','c.uuid = b.staff_uuid','left')
                            ->where($where)
                            ->group('a.uuid')
                            ->field('a.name,count(c.id) as staff_num,a.uuid')
                            ->select()
                            ->toArray();

        // 运动员年龄及性别结构统计
        $staff = $this->staff->inquiryAll(['is_show' => 1,'is_out' => 0,'is_athlete' => 1],['age' => 'asc'],['sex,birthday']);
        if ($staff){
            $sex = [1 => 0,2 => 0];
            $age = [];
            foreach ($staff as $v){
                $sex[$v['sex']] = $sex[$v['sex']] + 1;
                $staff_age = date('Y') - explode('-',$v['birthday'])[0] < 0 ? 0 : date('Y') - explode('-',$v['birthday'])[0];
                if (isset($age[$staff_age])){
                    $age[$staff_age] = $age[$staff_age] +1;
                }else{
                    $age[$staff_age] = 1;
                }
            }
            foreach ($sex as $key => $value){
                $staff_sex_ratio[$key] = round($value/($sex[1] + $sex[2]) * 100,2);
            }
        }
        $data['staff_age'] = $age;
        $data['staff_sex'] = $sex;
        $data['staff_department'] = $staff_department;
        $data['num_department'] = $num_department;
        $data['num_staff_in_department'] = $num_staff_in_department;
        $data['num_staff_out_department'] = $num_staff_out_department;
        $data['num_staff'] = $num_staff;
        $data['staff_sex_ratio'] = $staff_sex_ratio;
        return $data;
    }

    public function home($user){
        $userMenu = $this->getUserMenu($user);
        $whereFavorite = ['is_home' => 1];
        $field = ['uuid,favorite_icon,favorite_color,favorite_name as name'];
        $favoriteMenuUuid = $this->menu->inquiryColumn($whereFavorite,'uuid');
        if ($userMenu['type'] == 2){
            $userMenuUuid = array_intersect($userMenu['menu_uuid'],$favoriteMenuUuid);
            $whereFavorite['uuid'] = $userMenuUuid;
        }
        $favorite = $this->menu->inquiryAll($whereFavorite,[],$field);
        var_dump($favorite);exit();

    }
    public function getUserMenu($user){
        $date['type'] = 1;
        if ($user['type'] !=0 ){
            $menu_uuids = Db::name('user_role')
                            ->alias('ur')
                            ->join('role r','r.uuid =  ur.role_uuid')
                            ->join('role_menu rm','rm.role_uuid = r.uuid')
                            ->join('menu m','m.uuid = rm.menu_uuid')
                            ->where(['ur.user_uuid' => $user['uuid']])
                            ->column('m.puuid','m.uuid');
            $menu_uuids_arr = array_merge(array_keys($menu_uuids),array_values($menu_uuids));
            $date['menu_uuid'] = array_unique($menu_uuids_arr);
            $date['type'] = 2;
        }
        return $date;
    }
}