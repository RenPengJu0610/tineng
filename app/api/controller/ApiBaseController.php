<?php
/**
 * Created by PengJu
 * RoleUser: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/9/26/15:26
 */

namespace app\api\controller;

// 基类控制器
use app\BaseController;
use think\App;

class ApiBaseController extends BaseController
{
    protected $currentUser;

    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    public function initialize(){
        parent::initialize();
        //$this->currentUser = get_logon_user();
        $this->currentUser = ['id' => 2, 'uuid' => 'A94E875C-2BFC-2374-2C71-D3991867E795', 'name' => '张三', 'account' => 'zhangsan', 'type' => 1];
    }
    public function getCurrentUser() {
        return $this->currentUser;
    }

}