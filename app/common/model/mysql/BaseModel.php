<?php
/**
 * Created by PengJu
 * RoleUser: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/9/26/15:44
 */

namespace app\common\model\mysql;


use think\Model;

class BaseModel extends Model
{
    // 根据条件查询一条数据
    public function read($uuid,$field = ['*']){
        $result = $this->where('uuid',$uuid)->field($field)->find();
        if ($result){
            $result = $result->toArray();
        }
        return $result;
    }
    // 根据条件查询一条记录
    public function single($where){
        if (empty($where)){
            $result = $this->select();
        }else{
            $result = $this->where($where)->find();
        }
        if ($result){
            $result = $result->toArray();
        }
        return $result;
    }

    /**
     * 查询满足条件的记录
     * @param $where
     * @param $page
     * @param $page_size
     * @param string[] $field
     * @param bool $order
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @date 2021/9/27/17:40
     * @author RenPengJu
     */
    public function inquiry($where,$page,$page_size,$field = ['*'],$order = true){
        return $this->where($where)->order($order)->field($field)->page($page,$page_size)->select()->toArray();
    }
    /**
     * 查询满足条件的总记录条数
     * @param array $where
     * @return int
     * @date 2021/9/27/16:51
     * @author RenPengJu
     */
    public function inquiryCount($where = []){
        return $this->where($where)->count();
    }
    public function inquiryAll($where = [], $orderBy = [], $field = ['*'], $fieldExcept = false){
        if ($fieldExcept){
            return $this->where($where)
                        ->order($orderBy)
                        ->field($field,true)
                        ->select()
                        ->toArray();
        }
        return $this->where($where)
                    ->field($field)
                    ->order($orderBy)
                    ->select()
                    ->toArray();
    }
    /**
     * 添加记录
     * @param $data
     * @param false $replace
     * @return int|string
     * @date 2021/9/27/16:23
     * @author RenPengJu
     */
    public function add($data,$replace = false){
        return $this->insertGetId($data,$replace);
    }

    public function addAll($dataSet,$replace = false){
        return $this->insertAll($dataSet,$replace);
    }

    public function modify($uuid,$data){
        return $this->where(['uuid' => $uuid])->update($data);
    }

    /**
     * 获取某一列的值
     * @param $where
     * @param $field 要取的列名
     * @param $key 用作键的值
     * @return array
     * @date 2021/9/30/16:23
     * @author RenPengJu
     */
    public function inquiryColumn($where, $field, $key = ''){
        if ($key){
            return $this->where($where)->column($field,$key);
        }
        return $this->where($where)->column($field);

    }

    // 获取子类
    public function getAllChildren($field, $uuid, $needSelf = true, $all = false){
        $child_uuids = [];

        if ($uuid){
            if ($all){
                // 获取全部，包含禁用
                $child_uuids = $this->where([[$field ,'like',"%$uuid%"],['del_flag', '= ',0]])->column('uuid');
            }else{
                $child_uuids = $this->where([[$field,'like',"%{$uuid}%"],['status','=', 1/*,'del_flag' = 0*/]])->column('uuid');
            }
        }
        if ($needSelf){
            $child_uuids = array_merge($child_uuids,[$uuid]);
        }
        return $child_uuids;
    }

    // 根据条件进行删除
    public function delByWhere($where){
        return $this->where($where)->delete();
    }

    public function modifyWhere($where,$data){
        return $this->where($where)->update($data);
    }
}