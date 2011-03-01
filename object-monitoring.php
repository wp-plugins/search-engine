<?php
// Code related to Monitoring Object save / deletion

// WP Posts
add_action('delete_post','search_engine_object_post_delete');
add_action('trash_post','search_engine_object_post_delete');
add_action('save_post','search_engine_object_post_save');
add_action('untrash_post','search_engine_object_post_save');

function search_engine_object_post_delete ($post_ID)
{
    if(empty($post_ID))
        return;
    $post = get_post($post_ID);
    if($post->post_status=='auto-draft'||$post->post_type=='revision')
    {
        return;
    }
    global $wpdb;
    $url = get_permalink($post_ID);
    $siteurl = get_option('siteurl');
    $check = @parse_url($siteurl);
    if($check['scheme']=='http')
    {
        $url = str_replace('https://','http://',$url);
    }
    require_once SEARCH_ENGINE_DIR."/classes/API.class.php";
    $api = new Search_Engine_API();
    $api->silent = true;
    $default_site = get_option('search_engine_default_site');
    if(0<$default_site)
    {
        $site = $api->get_site(array('id'=>$default_site));
        if(false!==$site)
        {
            $parsed = @parse_url($url);
            if(false!==$parsed)
            {
                $parsed['scheme'] = $site['scheme'];
                $parsed['host'] = $site['host'];
                $url = $parsed['scheme'].'://'.$parsed['host'].(!empty($parsed['path'])?$parsed['path']:'/').(!empty($parsed['query'])?'?'.$parsed['query']:'').(!empty($parsed['fragment'])?'#'.$parsed['fragment']:'');
            }
        }
    }
    $api->delete_page(array('url'=>$url));
}
function search_engine_object_post_save ($post_ID)
{
    if(empty($post_ID))
        return;
    $post = get_post($post_ID);
    if($post->post_status=='draft'||$post->post_status=='pending')
    {
        search_engine_object_post_delete($post_ID);
        return;
    }
    elseif($post->post_status=='auto-draft'||$post->post_type=='revision')
    {
        return;
    }
    global $wpdb;
    $url = get_permalink($post_ID);
    $siteurl = get_option('siteurl');
    $check = @parse_url($siteurl);
    if($check['scheme']=='http')
    {
        $url = str_replace('https://','http://',$url);
    }
    require_once SEARCH_ENGINE_DIR."/classes/API.class.php";
    $api = new Search_Engine_API();
    $api->silent = true;
    $default_site = get_option('search_engine_default_site');
    if(0<$default_site)
    {
        $site = $api->get_site(array('id'=>$default_site));
        if(false!==$site)
        {
            $parsed = @parse_url($url);
            if(false!==$parsed)
            {
                $parsed['scheme'] = $site['scheme'];
                $parsed['host'] = $site['host'];
                $url = $parsed['scheme'].'://'.$parsed['host'].(!empty($parsed['path'])?$parsed['path']:'/').(!empty($parsed['query'])?'?'.$parsed['query']:'').(!empty($parsed['fragment'])?'#'.$parsed['fragment']:'');
            }
        }
    }
    $api->spider_page(array('url'=>$url));
}

// Pods - WAITING FOR 2.0
//add_action('pods_pre_drop_pod','search_engine_object_pod_delete');
//add_action('pods_pre_save_pod','search_engine_object_pod_save');
//add_action('pods_post_save_pod','search_engine_object_pod_save');
//add_action('pods_pre_drop_pod_item','search_engine_object_pod_item_delete');
//add_action('pods_pre_save_pod_item','search_engine_object_pod_item_save');
//add_action('pods_post_save_pod_item','search_engine_object_pod_item_save');
/*
function search_engine_object_pod_delete ()
{
    $args = func_get_args();
    $pod_id = $args[0];
    require_once SEARCH_ENGINE_DIR."/classes/API.class.php";
    $podsapi = new PodAPI();
    $pod = $podsapi->load_pod(array('id'=>$pod_id));
    if(!empty($pod['detail_page_url']))
    {
        global $wpdb;
        $url = pods_sanitize($pod['detail_page_url']);
        $url = str_replace('%%','%',preg_replace('/({\@[a-zA-Z0-9_\-\/,]+})/','%',$url).'%');
        $wpdb->query("DELETE FROM ".SEARCH_ENGINE_TBL."links WHERE url LIKE '{$url}'");
    }
}
function search_engine_object_pod_save ()
{
    $args = func_get_args();
    $pod_id = $args[0];
    require_once SEARCH_ENGINE_DIR."/classes/API.class.php";
}
function search_engine_object_pod_item_delete ()
{
    $args = func_get_args();
    $id = $args[0];
    $pod_id = $args[1];
    require_once SEARCH_ENGINE_DIR."/classes/API.class.php";
    $podsapi = new PodAPI();
    $pod = $podsapi->load_pod(array('id'=>$pod_id));
    if(!empty($pod['detail_page_url'])&&strpos($pod['detail_page_url'],'{@'))
    {
        $pod_item = new Pod($pod['name'],$id);
        $url = $pod_item->get_field('detail_url');
        $api = new Search_Engine_API();
        $api->silent = true;
        $pages = $api->get_page(array('url'=>$url));
        $api->delete_page(array('pages'=>$pages));
    }
    return;

}
function search_engine_object_pod_item_save ()
{
    $args = func_get_args();
    $pod_id = $args[1];
    require_once SEARCH_ENGINE_DIR."/classes/API.class.php";
    $podsapi = new PodAPI();
    $pod = $podsapi->load_pod(array('id'=>$pod_id));
    if(!empty($pod['detail_page_url'])&&strpos($pod['detail_page_url'],'{@'))
    {
        $pod_item = new Pod($pod['name'],$id);
        $url = $pod_item->get_field('detail_url');
        $check = @parse_url(get_bloginfo('wpurl'));
        if($check['scheme']=='http')
        {
            $url = str_replace('https://','http://',$url);
        }
        $params = array('url'=>$url);
        $api = new Search_Engine_API();
        $api->silent = true;
        $api->spider_page($params);
    }
}
*/