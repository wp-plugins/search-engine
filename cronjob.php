<?php
global $wpdb;
//define('WP_DEBUG',true);
if(!is_object($wpdb))
{
    ob_start();
    require_once('../../../wp-load.php');
    ob_end_clean();
}

set_time_limit(6000);
@ini_set('zlib.output_compression',0);
@ini_set('output_buffering','off');
@ini_set('memory_limit','64M');
ignore_user_abort(true);

if ( !headers_sent() ) {
    status_header(200);
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
}

$wpdb->query('SET session wait_timeout = 800');

$check = get_option('search_engine_token');
if(!isset($_GET['token'])||$_GET['token']!=$check)
    die('Invalid Token');

require_once "classes/Index.class.php";
require_once "classes/Spider.class.php";
require_once "classes/Search.class.php";
require_once "classes/Sitemap.class.php";
require_once "classes/API.class.php";

?>
<!--
abcdefghijklmnopqrstuvwxyz1234567890aabbccddeeffgghhiijjkkllmmnnooppqqrrssttuuvvwwxxyyzz11223344556677889900abacbcbdcdcededfefegfgfhghgihihjijikjkjlklkmlmlnmnmononpopoqpqprqrqsrsrtstsubcbcdcdedefefgfabcadefbghicjkldmnoepqrfstugvwxhyz1i234j567k890laabmbccnddeoeffpgghqhiirjjksklltmmnunoovppqwqrrxsstytuuzvvw0wxx1yyz2z113223434455666777889890091abc2def3ghi4jkl5mno6pqr7stu8vwx9yz11aab2bcc3dd4ee5ff6gg7hh8ii9j0jk1kl2lmm3nnoo4p5pq6qrr7ss8tt9uuvv0wwx1x2yyzz13aba4cbcb5dcdc6dedfef8egf9gfh0ghg1ihi2hji3jik4jkj5lkl6kml7mln8mnm9ono
abcdefghijklmnopqrstuvwxyz1234567890aabbccddeeffgghhiijjkkllmmnnooppqqrrssttuuvvwwxxyyzz11223344556677889900abacbcbdcdcededfefegfgfhghgihihjijikjkjlklkmlmlnmnmononpopoqpqprqrqsrsrtstsubcbcdcdedefefgfabcadefbghicjkldmnoepqrfstugvwxhyz1i234j567k890laabmbccnddeoeffpgghqhiirjjksklltmmnunoovppqwqrrxsstytuuzvvw0wxx1yyz2z113223434455666777889890091abc2def3ghi4jkl5mno6pqr7stu8vwx9yz11aab2bcc3dd4ee5ff6gg7hh8ii9j0jk1kl2lmm3nnoo4p5pq6qrr7ss8tt9uuvv0wwx1x2yyzz13aba4cbcb5dcdc6dedfef8egf9gfh0ghg1ihi2hji3jik4jkj5lkl6kml7mln8mnm9ono
-->
<?php
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
    wp_die('<strong>Error:</strong> Please contact plugin developer at http://scottkclark.com/');