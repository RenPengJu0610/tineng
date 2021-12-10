<?php
/**
 * Created by PengJu
 * RoleUser: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/9/26/15:27
 */

namespace app\api\controller;


use app\common\lib\Show;
use think\App;
use app\validate\Menu as MenuValiDate;
use think\Exception;
use app\common\business\Menu as MenuBus;
class Menu extends ApiBaseController
{
    private $validate;
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->validate = new MenuValiDate();
        $this->logic = new MenuBus();
    }

    /**
     * 添加菜单
     * @return \think\response\Json
     * @date 2021/9/27/16:49
     * @author RenPengJu
     */
    public function add(){
        $params = $this->request->param();
        try {
            if (!$this->validate->check($params)){
                throw new Exception($this->validate->getError());
            }
            $res = $this->logic->add($params,$this->currentUser);
            if (!$res){
                return Show::error('添加失败');
            }
        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }

        return Show::success([],'添加成功');
    }

    /**
     * 菜单列表
     * @return \think\response\Json
     * @date 2021/9/30/14:36
     * @author RenPengJu
     */
    public function lists(){
        $page = $this->request->post('page',config('page.page'));
        $page_size = $this->request->post('page_size',config('page.page_size'));
        $keyword = trim($this->request->post('keyword'));
        $where = array();
        if (!empty($keyword)){
            $where['name'] = ['like',"%".$keyword."%"];
        }
        $order = ['order_no' => 'desc'];
        $result = $this->logic->lists($where,$page,$page_size,[],$order);
        return Show::success($result,'OK');
    }

    /**
     * 查看单条
     * @return \think\response\Json
     * @date 2021/9/30/14:36
     * @author RenPengJu
     */
    public function read(){
        $uuid = trim($this->request->post('uuid'));
        if (empty($uuid)){
            return Show::error('参数错误');
        }
    }

    /**
     * 修改菜单
     * @return \think\response\Json
     * @date 2021/9/30/14:36
     * @author RenPengJu
     */
    public function update(){
        $params = $this->request->param();
        try {
            if (!$this->validate->check($params)){
                throw new Exception($this->validate->getError());
            }

            $result = $this->logic->update($params);
            if ($result === false){
                return Show::error('修改失败');
            }
        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }
        return Show::success([],'修改成功');
    }

    /**
     * 删除
     * @date 2021/9/30/14:37
     * @author RenPengJu
     */
    public function del(){
        $uuid = trim($this->request->post('uuid'));
        if (empty($uuid)){
            return Show::error('参数错误');
        }
        try {
            $result = $this->logic->del($uuid);
            if ($result === false){
                return Show::error('修改失败');
            }
        }catch (\Exception $e){
            return Show::error($e->getMessage());
        }

        return Show::success([],'OK');
    }

    // 状态修改
    public function able(){
        $uuid = trim($this->request->post('uuid'));

        if (empty($uuid)){
            return Show::error('参数错误');
        }

        $result = $this->logic->able($uuid);

        if (!$result){
            return  Show::error('修改失败');
        }

        return Show::success([],'OK');

    }
}