<?php
/**
 * Created by PengJu
 * RoleUser: RenPengJu
 * Motto: 现在的努力是为了小时候吹过的牛逼
 * Time: 2021/9/26/16:12
 */

namespace app\common\lib;


class Show
{
    public static function error($message, $data = [], $status = 0)
    {
        $result = [
            'status' => $status,
            'message' => $message,
            'data' => $data
        ];
        return json($result);
    }

    public static function success($data = [], $message = 'success'){
        $result = [
            'status' => config('status.success'),
            'message' => $message,
            'data' => $data
        ];
        return json($result);
    }
}