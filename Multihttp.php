<?php
/**
 * Created by PhpStorm.
 * User: Yong
 * Date: 2019/9/4
 * Time: 16:37
 */

class Multihttp
{
    private $ch_array = [];
    private $query_array = [];
    private $result;
    private $end_call = [];
    public function __construct()
    {
    }
    public function add_query($query_key, $funcs_set = [], $url, $data = [], $method, $userpwd = '', $header = [], $time_out = 30, $cookie = null, $referer = null, $userAgent = null)
    {
        if (isset($funcs_set['end_call']) && $funcs_set['end_call']) {
            $this->end_call[$query_key] = $funcs_set['end_call'];
        }
        if (isset($funcs_set['pre_call']) && $funcs_set['pre_call']) {
            $rs = call_user_func_array([$funcs_set['pre_call']['class'],$funcs_set['pre_call']['function']], $funcs_set['pre_call']['params']);
            if ($rs) { // 说明获取到了数据或者不需要执行网络请求
                $this->result[$query_key] = $rs;
                return true;
            }
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt_array($ch, array(
            CURLOPT_URL           => $url,
            CURLOPT_COOKIE        => $cookie,
            CURLOPT_REFERER       => $referer,
            CURLOPT_USERAGENT     => $userAgent,
            CURLOPT_TIMEOUT       => $time_out,
        ));
        if ($userpwd) {
            curl_setopt($ch, CURLOPT_USERPWD, $userpwd);
        }
        if ($header) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }
        if ($method == 'post') {
            is_array($data) && $data = http_build_query($data);
            $data && curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        $this->ch_array[$query_key] = $ch;
        $this->query_array[$query_key] = ['url'=>$url,'data'=>$data,'method'=>$method];
        return true;
    }

    /**
     * @param        $query_key
     * @param array  $funcs_set ['pre_call'=>[class=>xx,function=>xx,params=>[]],'end_call'=>[class,function,params]]
     * @param        $url
     * @param string $userpwd
     * @param array  $header
     * @param int    $time_out
     * @param null   $cookie
     * @param string $referer
     * @param null   $userAgent
     */
    public function add_get($query_key, $funcs_set = [], $url, $userpwd = '', $header = [], $time_out = 30, $cookie = null, $referer = "", $userAgent = null)
    {
        return $this->add_query($query_key, $funcs_set, $url, [], 'get', $userpwd, $header, $time_out, $cookie, $referer, $userAgent);
    }

    /**
     * @param        $query_key
     * @param array  $funcs_set
     * @param        $url
     * @param array  $data
     * @param string $userpwd
     * @param array  $header
     * @param int    $time_out
     * @param null   $cookie
     * @param null   $referer
     * @param null   $userAgent
     */
    public function add_post($query_key, $funcs_set = [], $url, $data = [], $userpwd = '', $header = [], $time_out = 30, $cookie = null, $referer = null, $userAgent = null)
    {
        return $this->add_query($query_key, $funcs_set, $url, $data, 'post', $userpwd, $header, $time_out, $cookie, $referer, $userAgent);
    }
    public function exec()
    {
        if ($this->ch_array) {
            $mh = curl_multi_init();
            foreach ($this->ch_array as $val) {
                curl_multi_add_handle($mh, $val);
            }
            $running = null;
            do {
                $status = curl_multi_exec($mh, $running);
                if ($status > 0) {
                    echo "ERROR!\n " . curl_multi_strerror($status);
                }
            } while ($status === CURLM_CALL_MULTI_PERFORM || $running);
            foreach ($this->ch_array as $key => $val) {
                $rs = curl_multi_getcontent($val);
                $res = json_decode($rs, true);
                if (!$res) {
                    $this->result[$key] = [];
                    Service_log::notice(
                        "multi_curl_fail",
                        0,
                        $this->query_array[$key]
                    );
                } else {
                    $this->result[$key] = $res;
                }
                curl_multi_remove_handle($mh, $val);
            }
            curl_multi_close($mh);
        }
        $results = [];
        foreach ($this->result as $key => $val) {
            if (isset($this->end_call[$key])) {
                $params = $this->end_call[$key]['params'];
                $params[] = $val;
                $results[$key] = call_user_func_array([$this->end_call[$key]['class'],$this->end_call[$key]['function']], $params);
            } else {
                $results[$key] = $val;
            }
        }
        $this->ch_array = [];
        $this->query_array = [];
        $this->result = [];
        return $results;
    }
}
