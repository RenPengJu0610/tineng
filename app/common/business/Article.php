<?php
/**
 * Created by PengJu
 * User: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/11/22/14:39
 */

namespace app\common\business;

use app\common\lib\Arr;
use app\common\model\mysql\ArticleCate as ArticleCateModel;
use app\common\model\mysql\Article as ArticleModel;
use app\common\model\mysql\Nutrition as NutritionModel;
use think\Exception;

class Article extends BusBase
{
    public $model;
    public $articleModel;
    public $nutritionModel;

    public function __construct()
    {
        $this->model = new ArticleCateModel();
        $this->articleModel = new ArticleModel();
        $this->nutritionModel = new NutritionModel();
    }

    public function all($flag, $type, $uuid, $where)
    {
        $cate = $this->model->inquiryAll($where);
        if ($flag && $uuid) {
            if ($flag == 1) { // flag == 1 说明是进行分类的编辑
                $cateParentUuids = $this->getParentUuids($uuid, false);
            } else {
                if ($type == 1) { // type = 1时，说明是文章修改
                    $article_cate_uuid = $this->articleModel->inquiryColumn(['uuid' => $uuid], 'article_cate_uuid');
                } else { // 是营养品文章的修改
                    $article_cate_uuid = $this->nutritionModel->inquiryColumn(['uuid' => $uuid], 'article_cate_uuid');
                }
                $cateParentUuids = $this->getParentUuids($article_cate_uuid[0]);
            }
            $whereUpa['uuid'] = $cateParentUuids;
            $cate_parent = $this->model->inquiryAll($whereUpa);
            foreach ($cate_parent as $value) {
                if (!$value['status'] || $value['del_flag']) {
                    $cate[] = $value;
                }
            }
        }
        return Arr::tree($cate);
    }

    public function getParentUuids($uuid, $needSelf = true)
    {
        $read = $this->model->read($uuid);
        $puuid = [];
        if (!empty($read['cate_path'])) {
            $puuid = explode('/', $read['cate_path']);
        }
        if ($needSelf) {
            return array_merge($puuid, [$uuid]);
        }
        return $puuid;
    }

    /**
     * 添加分类
     * @param $params
     * @date 2021/11/23/13:34
     * @author RenPengJu
     */
    public function add($params,$currentUser){
        try {
            $params['uuid'] = guid();
            if ($params['puuid']){
                $cate_exists = $this->model->read($params['puuid']);
                if (empty($cate_exists) || $cate_exists['del_flag']){
                    throw new Exception('父级分类不存在或已删除');
                }
                if ($cate_exists['status']){
                    throw new Exception('父级分类被禁用');
                }
                $params['cate_path'] = trim(trim($cate_exists['cate_path'],'/') . '/' . $cate_exists['uuid'],'/');
                $params['level'] = intval($cate_exists['level']) +1;

            }else{
                $params['level'] = 1;
                $params['cate_path'] = '';
                $params['create_time'] = date('Y-m-d H:i:s');
                $params['create_by'] = $currentUser['name'];
            }
            $add = $this->model->add($params);
            if (!$add){
                throw new Exception('添加失败');
            }
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
        return true;
    }
    public function update($params,$currentUser){
        try {
            $uuid = $params['uuid'];
            unset($params['uuid']);
            $cate_exists = $this->model->read($uuid);

            if (empty($cate_exists)){
                throw new Exception('要修改的分类不存在');
            }
            $childrens = [];
            if ($params['puuid'] != $cate_exists['puuid']){
                $parent_cate_exists = $this->model->read($params['puuid']);
                if (empty($parent_cate_exists) || $parent_cate_exists['del_flag']){
                    throw new Exception('父级分类不存在或已删除');
                }
                if (!$parent_cate_exists['status']){
                    throw new Exception('父级分类被禁用');
                }
                // 获取该分类下的子集
                $childrenUuids = $this->getChildrenUuids($uuid,false,true);
                $childrens = $this->model->inquiryAll(['uuid' => $childrenUuids],['level' => 'asc']);
                $params['cate_path'] = trim(trim($parent_cate_exists['cate_path'],'/'). '/' .$parent_cate_exists['uuid'],'/');
                $params['level'] = intval($parent_cate_exists['level']) + 1;
                $params['create_time'] = date('Y-m-d H:i:s');
                $params['create_by'] = $currentUser['name'];
            }
            $this->model->startTrans();
            $update = $this->model->modify($uuid,$params);
            if ($update === false){
                throw new Exception('修改失败');
            }
            foreach ($childrens as $children){
                $read = $this->model->read($children['puuid']);
                $arr = ['cate_path' => trim(trim($read['cate_path'],'/') . '/' . $read['uuid'],'/'),'level' => intval($read['level'] +1)];
                $childrenUpdate = $this->model->modify($children['uuid'],$arr);
                if ($childrenUpdate === false){
                    throw new Exception('修改失败');
                }
            }
            $this->model->commit();
        }catch (\Exception $e){
            $this->model->rollback();
            throw new Exception($e->getMessage());
        }
        return true;
    }
    // 根据uuid找到其子类
    public function getChildrenUuids($uuid,$needSelf = true,$all = false){
        $childrens = [];
        if ($uuid){
            if ($all){ // 包含禁用的
                $childrens = $this->model->where([['cate_path','like',"%{$uuid}%"],['del_flag','=', 0]])->column('uuid');
            }else{ // 启用的
                $childrens = $this->model->where([['cate_path','like',"%{$uuid}%"],['del_flag','=', 0],['status' ,'=', 1]])->column('uuid');
            }
        }
        if($needSelf){ // 是否合并自身
            $childrens = array_merge($childrens,[$uuid]);
        }
        return $childrens;
    }
    public function del($uuids,$type){
        try {
            if (empty($uuids)){
                throw new Exception('参数错误');
            }
            $uuids = explode(',',$uuids);
            $childrenUuids = [];
            foreach ($uuids as $uuid){
                $childrenUuids = array_merge($childrenUuids,$this->getChildrenUuids($uuid,true,true));
            }
            // 查看要删除的分类和其子类有没有被引用的，如果有则提示不能删除
            if ($type == 1){
                $model = $this->articleModel;
            }else{
                $model = $this->nutritionModel;
            }
            $articleInfo = [];
            foreach ($childrenUuids as $childrenUuid){
                $articleInfo = array_merge($articleInfo,$model->inquiryColumn(['article_cate_uuid' =>$childrenUuid],'article_cate_uuid'));
            }
            if (!empty($articleInfo)){
                $articleInfo = array_unique($articleInfo);
                $exception = $this->model->inquiryColumn(['uuid' => $articleInfo,'type' => $type],'name');
                $exceptionstr = implode(',',$exception);
                throw new Exception($exceptionstr.'等分类被引用，不能删除');
            }
            // 如果存在分类没被引用，则直接删除
            $del = $this->model->modifyWhere(['uuid' => $childrenUuids,'type' => $type],['del_flag' => 1]);
            if ($del === false){
                throw new Exception('删除失败');
            }
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
        return true;
    }
    public function able($uuid){
        try {
            if (empty($uuid)){
                throw new Exception('参数错误');
            }
            // 查询要修改的这条数据是否存在
            $read = $this->model->read($uuid);
            if (!$read){
                throw new Exception('数据不存在');
            }
            if ($read['status']){
                $status = 0;
                // 禁用时，需要把其所有的子类都禁用
                $uuids = $this->getChildrenUuids($uuid);
            }else{
                // 启用时需要把父类也开启
                $status = 1;
                $uuids = $this->getParentUuids($uuid);
            }
            $able = $this->model->modifyWhere(['uuid' => $uuids],['status' => $status]);
            if ($able === false){
                throw new Exception('修改失败');
            }
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
        return true;
    }
}