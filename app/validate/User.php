<?php
/**
 * Created by PengJu
 * User: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/10/27/15:55
 */

namespace app\validate;



use think\Validate;

class User extends Validate
{
    protected $rule = [
        'account' => 'require',
        'pwd'=> 'require',
        'email' => ' email'
    ];

    protected $message = [
        'account.require' => '用户名必填',
        'pwd.require' => '密码必填',
        'email' => '邮箱格式错误'
    ];
    protected $scene = [
        'update' => ['account','uuid','email']
    ];
}