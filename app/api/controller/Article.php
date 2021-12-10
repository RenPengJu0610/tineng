<?php
/**
 * Created by PengJu
 * User: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/11/22/14:36
 */

namespace app\api\controller;


use app\common\lib\Show;
use think\App;
use app\common\business\Article as ArticleBus;
use think\Exception;

class Article extends ApiBaseController
{
    public $logic;
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->logic = new ArticleBus();
    }
    public function all(){
        try {
            $flag = $this->request->post('flag');
            $type = $this->request->post('type');
            $uuid = $this->request->post('uuid');
            $where = ['status' => 1,'del_flag' => 0,'type' => $type];
            $res = $this->logic->all($flag,$type,$uuid,$where);
        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success($res,'OK');
    }
    // 添加
    public function add(){
        try {
            $params = $this->request->post();
            $add = $this->logic->add($params,$this->currentUser);
            if (!$add){
                return false;
            }
        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success([],'OK');
    }
    public function update(){
        try {
            $params = $this->request->post();
            $update = $this->logic->update($params,$this->currentUser);
            if (!$update){
                throw new Exception('修改失败');
            }
        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success([],'OK');
    }

    /**
     * 删除
     * @return \think\response\Json
     * @date 2021/11/24/13:22
     * @author RenPengJu
     */
    public function del(){
        try {
            $uuid = $this->request->post('uuid');
            $type = $this->request->post('type');
            $del = $this->logic->del($uuid,$type);
            if (!$del){
                throw new Exception('删除失败');
            }
        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success([],'OK');
    }

    public function able(){
        $file = $this->request->file('img');
        try {
            $uuid = $this->request->post('uuid');
            $able = $this->logic->able($uuid);
            if (!$able){
                throw new Exception('状态切换失败');
            }
        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success([],'OK');
    }
}