# php_multi_curl
php multi-curl实现curl并行

CI 框架引用

使用方式：

1.批量

$this->load->library('multihttp');

 

2.添加请求：

//$call_func 设置的请求接口前操作，和接口数据请求成功后操作，可以指定类方法

    $call_func = [  
        'pre_call'=>['class'=>$this->m_info_for_multi,'function'=>'get_menus','params'=>[$rid,$lang]],
        'end_call'=>['class'=>$this->m__info_for_multi,'function'=>'handle_get_menus','params'=>[$rid,$lang]],
    ];
    $query_data  = array('ruid' =>$rid, 'lang' => $lang);
    $query_str = http_build_query($query_data);
    $url = $this->api_url . $query_str;
    // menus 是设置的返回结果key值
    return $this->multihttp->add_get('menus', $call_func, $url);
 

    $url = $this->api_url . "/get_xx';
    $call_func = [
        'pre_call'=>['class'=>$this->m_info_for_multi,'function'=>'get_b','params'=>[$rid]],
        'end_call'=>['class'=>$this->m_info_for_multi,'function'=>'handle_get_b','params'=>[$rid]],
    ];
    return $this->multihttp->add_get('get_b', $call_func, $url);
 

 

3. 提交请求

    $multi_result = $this->multihttp->exec();

获取menus 和 get_b 结果：

    $multi_result['menus']  和  $multi_result['get_b'] 

 

代码中pre_call 主要处理一些判断是否有缓存，是否需要走后面请求逻辑。如果 pre_call有返回值，就不会进行对应项的接口请求。会把pre_call 返回值返回

eg:


    public function get_menus($restaurant_id, $lang = 'en_US')
    {
        if (empty($restaurant_id)) {
            return false;
        }
        // Check cache

        if ($this->cache->memcached->is_supported()) {
            $memcached_keys_list = $this->config->item('memcached_keys_list');
            $mcd_key = sprintf($memcached_keys_list['memus'], $xx, $lang);
            $result = $this->cache->memcached->get($mcd_key);
            if (!empty($result)) {
                return $result;
            }
        }
        return false;

    }
 

end_call 对接口返回结果的一些逻辑处理和数据整合

eg:

    public function handle_get_menus($restaurant_id,$lang){
        if(isset($data['CODE']) && $data['CODE'] == '200') {
                $menus = $data['DATA']['result'];
                if ($this->cache->memcached->is_supported()) {
                    $memcached_keys_list = $this->config->item('memcached_keys_list');
                    $mcd_key = sprintf($memcached_keys_list['memus'],  $restaurant_id, $lang);
                    $this->cache->memcached->save($mcd_key, $menus, 7200); // by sesconds
                }
        }else{
            $menus = isset($data['CODE']) ? [] : $data; // 兼容缓存中读取得数据
        }
        return $menus;
    }
