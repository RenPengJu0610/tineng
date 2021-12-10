<?php
/**
 * Created by PengJu
 * RoleUser: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/9/26/15:49
 */

namespace app\validate;

use think\Validate;
class Menu extends Validate
{
    protected $rule = [
        'name' => 'require',
        'type'  => 'require',
        'puuid' => 'require'
    ];
    protected $message = [
        'name.require' => '名称必填',
        'puuid.require' => '父级UUID必填',
        'type.require' => '类型必填'
    ];
}