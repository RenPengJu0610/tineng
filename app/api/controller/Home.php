<?php
/**
 * Created by PengJu
 * User: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/11/12/9:24
 */

namespace app\api\controller;


use app\common\lib\Show;
use think\App;
use app\common\business\Home as HomeLogic;
use think\Exception;

class Home extends ApiBaseController
{
    public $logic;
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->logic = new HomeLogic();
    }

    public function lists(){
        try {
           $user = $this->getCurrentUser();
           $result = $this->logic->lists($user);
        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success($result,'OK');
    }
    public function getTrainEnginery(){
        try {
            $start_time = $this->request->post('start_time');
            $end_time = $this->request->post('end_time');
            $department_uuids = $this->request->post('department_uuid',null);
            $res = $this->logic->getTrainEnginery($start_time,$end_time,$department_uuids,$this->currentUser);

        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success($res,'OK');

    }
    public function status(){
        try {
            $uuids = $this->request->post('uuids');
            $res = $this->logic->status($uuids,$this->currentUser);
        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success([],'OK');
    }

    public function homeDate(){
        try {
            $res = $this->logic->homeDate();
        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success($res);
    }
}