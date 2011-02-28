<?php
global $wpdb;
//define('WP_DEBUG',true);
if(!is_object($wpdb))
{
    ob_start();
    require_once('../../../wp-load.php');
    ob_end_clean();
}
$check = get_option('search_engine_token');
if(!isset($_GET['token'])||$_GET['token']!=$check)
    die('Invalid Token');

require_once "classes/Index.class.php";
require_once "classes/Spider.class.php";
require_once "classes/Search.class.php";
require_once "classes/Sitemap.class.php";
require_once "classes/API.class.php";

$spider = new Search_Engine_Spider();
if(isset($_GET['template_id'])&&0<$_GET['template_id'])
{
    $spider->set_template($_GET['template_id']);
}
elseif(isset($_GET['site_id'])&&0<$_GET['site_id'])
{
    $spider->set_site($_GET['site_id']);
}
if($spider->site_id!==false)
{
    if($spider->template_id!==false)
    {
        $continue = $spider->api->get_queue(array('site'=>$spider->site_id,'template'=>$spider->template_id));
        if($continue!==false)
        {
            $spider_vars = json_decode($continue['queue'],true);
            foreach($spider_vars as $var_name=>$var_value)
            {
                $spider->$var_name = $var_value;
            }
            $result = $spider->munch($spider->links_current,$spider->current_depth);
        }
        else
        {
            $result = $spider->spider();
        }
    }
    else
    {
        $result = $spider->spider();
    }
}
else
{
    die('Error, please contact plugin developer at http://scottkclark.com/');
}