<?php
/**
 * Created by PengJu
 * User: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/11/24/16:23
 */

namespace app\common\model\mysql;


use think\Exception;

class File
{
    static $basePath = 'static/upload';

    /**
     * 判断文件是否存在
     * @param $file_path
     * @return false|string
     * @date 2021/11/24/16:54
     * @author RenPengJu
     */
    public function fileExists($file_path){
        if (!$file_path){
            return false;
        }
        $file = root_path('public') . $file_path;
        if (!is_file($file)){
            $file = root_path('public') . $file_path;
        }

        if (!is_file($file)){
            return false;
        }
        return $file;
    }

    /**
     * 上传文件
     * @param $file // 上传文件的对象
     * @param null $filePartPath 文件的上一层目录
     * @param array $rule 验证规则
     * @param bool $fileName 上传后文件的名称 有值的话用传过来的值，没有的话就随机生成
     * @param false $replace
     * @param bool $compress 上传后文件是否是图片，是图片是否需要裁剪
     * @date 2021/11/24/17:21
     * @author RenPengJu
     */
    public function upload($file,$filePartPath = null, $rule = [], $fileName = true, $replace = FALSE ,$compress = true){
        try {
            if ($file){
                throw new Exception('没有监测到要上传的文件');
            }
            $file->check($rule);
            if (!$file->check($rule)){
                throw new Exception($file->getError());
            }

        }catch (Exception $e){

        }
    }
}