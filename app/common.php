<?php
// 应用公共文件

if(!function_exists("guid")) {
    function guid(){
        mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
        $charid = strtoupper(md5(uniqid(rand(), true)));

        $hyphen = chr(45);// "-"
        $uuid = substr($charid, 0, 8).$hyphen
            .substr($charid, 8, 4).$hyphen
            .substr($charid,12, 4).$hyphen
            .substr($charid,16, 4).$hyphen
            .substr($charid,20,12);
        return $uuid;
    }

    if(!function_exists("get_logon_user")) {
        function get_logon_user($s = '', $cookipre = '') {
            if (!$s) $s = !empty($_COOKIE[$cookipre.'_token']) ? $_COOKIE[$cookipre.'_token'] : '';
            if (!$s) return array();
            $s2 = Security::decrypt($s, config('AUTH_KEY'));
            if (!$s2) return array();
            $arr = explode("\t", $s2);
            if (count($arr) < 6) return array();
            $token = array();
            $token['id'] = $arr[0];
            $token['uuid'] = $arr[1];
            $token['account'] = $arr[2];
            $token['name'] = $arr[3];
            $token['type'] = $arr[4];
            $token['avatar'] = $arr[5];

            return $token;
        }
    }

    if (!function_exists('get_salt')){
        function get_salt(){
            $str = "0123456789zxcvbnmasdfghjklqwertyuiop";
            $len = strlen($str);
            $hash = "";
            for ($i = 0; $i < 6; $i++){
                $hash .= $str[mt_rand(0,$len-1)];
            }
            return $hash;
        }

    }
    if (!function_exists('get_password')){
        function get_password($password,$salt){
            return md5(md5($password.$salt));
        }
    }
}
