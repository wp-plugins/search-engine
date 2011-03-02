<?php
/*
Plugin Name: Search Engine
Plugin URI: http://scottkclark.com/wordpress/search-engine/
Description: THIS IS A BETA VERSION - Currently in development - A search engine for WordPress that indexes ALL of your site and provides comprehensive search.
Version: 0.5.7.1
Author: Scott Kingsley Clark
Author URI: http://scottkclark.com/

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

global $wpdb;
define('SEARCH_ENGINE_TBL',$wpdb->prefix.'searchengine_');
define('SEARCH_ENGINE_VERSION','057');
define('SEARCH_ENGINE_URL',WP_PLUGIN_URL.'/search-engine');
define('SEARCH_ENGINE_DIR',WP_PLUGIN_DIR.'/search-engine');
define('SEARCH_ENGINE_XML_SITEMAPS_DIR',WP_CONTENT_DIR.'/xml-sitemaps');

include_once "object-monitoring.php";

add_action('admin_init','search_engine_init');
add_action('admin_menu','search_engine_menu');

function search_engine_init ()
{
    global $current_user,$wpdb;
    $capabilities = search_engine_capabilities();
    // check version
    $version = intval(get_option('search_engine_version'));
    if(empty($version))
    {
        search_engine_reset();
    }
    elseif($version!=SEARCH_ENGINE_VERSION)
    {
        if($version<50)
            $wpdb->query("CREATE TABLE `".SEARCH_ENGINE_TBL."queue` (`id` int(10) NOT NULL AUTO_INCREMENT, `site` int(10) NOT NULL, `template` int(10) NOT NULL, `added` datetime NOT NULL, `updated` datetime NOT NULL, `queue` longtext NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARSET=".DB_CHARSET);
        if($version<51)
        {
            $wpdb->query("ALTER TABLE `".SEARCH_ENGINE_TBL."templates` ADD COLUMN `cross_scheme` INT unsigned AFTER `max_depth`");
            $wpdb->query("ALTER TABLE `".SEARCH_ENGINE_TBL."queue` ADD COLUMN `shutdown` INT unsigned AFTER `template`");
        }
        if($version<55)
        {
            $wpdb->query("ALTER TABLE `".SEARCH_ENGINE_TBL."templates` ADD COLUMN `disable_robots` INT unsigned AFTER `cross_scheme`");
        }
        delete_option('search_engine_version');
        add_option('search_engine_version',SEARCH_ENGINE_VERSION);
    }
    // thx gravity forms, great way of integration with members!
    if(function_exists('members_get_capabilities'))
    {
        add_filter('members_get_capabilities', 'search_engine_get_capabilities');
        if(current_user_can('search_engine_full_access'))
            $current_user->remove_cap('search_engine_full_access');
        if(current_user_can('administrator')&&!search_engine_current_user_can_any(search_engine_capabilities()))
        {
            $role = get_role('administrator');
            foreach($capabilities as $cap)
                $role->add_cap($cap);
        }
    }
    else
    {
        $search_engine_full_access = (current_user_can('administrator')?'search_engine_full_access':'');
        $search_engine_full_access = apply_filters('search_engine_full_access',$search_engine_full_access);
        if(!empty($search_engine_full_access))
            $current_user->add_cap($search_engine_full_access);
    }
}
function search_engine_reset ()
{
    global $wpdb;
    // thx pods ;)
    $sql = file_get_contents(SEARCH_ENGINE_DIR.'/assets/dump.sql');
    $sql_explode = preg_split("/;\n/", str_replace('DEFAULT CHARSET=utf8','DEFAULT CHARSET='.DB_CHARSET,str_replace('wp_',$wpdb->prefix,$sql)));
    if(count($sql_explode)==1)
        $sql_explode = preg_split("/;\r/", str_replace('wp_', $wpdb->prefix, $sql));
    for ($i = 0, $z = count($sql_explode); $i < $z; $i++)
    {
        $wpdb->query($sql_explode[$i]);
    }
    delete_option('search_engine_version');
    add_option('search_engine_version',SEARCH_ENGINE_VERSION);
}
function search_engine_get_capabilities ($caps)
{
    return array_merge($caps,search_engine_capabilities());
}
function search_engine_capabilities ()
{
    return array('search_engine_index_templates','search_engine_view_indexmaps','search_engine_groups','search_engine_view_index','search_engine_logs','search_engine_search_settings');
}
function search_engine_current_user_can_any ($caps)
{
    if(!is_array($caps))
        return current_user_can($caps) || current_user_can("search_engine_full_access");
    foreach($caps as $cap)
    {
        if(current_user_can($cap))
            return true;
    }
    return current_user_can("search_engine_full_access");
}
function search_engine_current_user_can_which ($caps)
{
    foreach($caps as $cap)
    {
        if(current_user_can($cap))
            return $cap;
    }
    return "";
}
function search_engine_menu ()
{
    global $wpdb;
    $has_full_access = current_user_can('search_engine_full_access');
    if(!$has_full_access&&current_user_can('administrator'))
        $has_full_access = true;
    $min_cap = search_engine_current_user_can_which(search_engine_capabilities());
    if(empty($min_cap))
        $min_cap = 'search_engine_full_access';
    $templates = @count($wpdb->get_results('SELECT id FROM '.SEARCH_ENGINE_TBL.'templates LIMIT 1'));
    $sites = @count($wpdb->get_results('SELECT id FROM '.SEARCH_ENGINE_TBL.'sites LIMIT 1'));
    $logs = @count($wpdb->get_results('SELECT id FROM '.SEARCH_ENGINE_TBL.'log LIMIT 1'));
    add_menu_page('Search Engine', 'Search Engine', $has_full_access ? 'read' : $min_cap, 'search-engine', null, SEARCH_ENGINE_URL.'/assets/icons/search_16.png');
    add_submenu_page('search-engine', 'Wizard', 'Wizard', $has_full_access ? 'read' : 'search_engine_index', 'search-engine', 'search_engine_wizard');
    if(0<$templates)
        add_submenu_page('search-engine', 'Index Templates', 'Index Templates', $has_full_access ? 'read' : 'search_engine_index_templates', 'search-engine-index-templates', 'search_engine_index_templates');
    if(0<$sites)
        add_submenu_page('search-engine', 'XML Sitemaps', 'XML Sitemaps', $has_full_access ? 'read' : 'search_engine_view_indexmaps', 'search-engine-xml-sitemaps', 'search_engine_xml_sitemaps');
    /* COMING SOON! :-)
    add_submenu_page('search-engine', 'Groups', 'Groups', $has_full_access ? 'read' : 'search_engine_groups', 'search-engine-groups', 'search_engine_groups');
    add_submenu_page('search-engine', 'View Index', 'View Index', $has_full_access ? 'read' : 'search_engine_view_index', 'search-engine-view-index', 'search_engine_view_index');
    */
    if(0<$logs)
        add_submenu_page('search-engine', 'View  Search Logs', 'View Search Logs', $has_full_access ? 'read' : 'search_engine_logs', 'search-engine-logs', 'search_engine_logs');
    add_submenu_page('search-engine', 'Settings', 'Settings', $has_full_access ? 'read' : 'search_engine_settings', 'search-engine-settings', 'search_engine_settings');
    add_submenu_page('search-engine', 'About', 'About', $has_full_access ? 'read' : $min_cap, 'search-engine-about', 'search_engine_about');
}
function search_engine_wizard ()
{
    if(isset($_GET['action'])&&$_GET['action']=='run')
        search_engine_wizard_run();
    else
    {
?>
<div class="wrap">
    <div id="icon-edit-pages" class="icon32" style="background-position:0 0;background-image:url(<?php echo SEARCH_ENGINE_URL; ?>/assets/icons/search_32.png);"><br /></div>
    <h2>Search Engine - Easy Indexing Wizard</h2>
    <div style="height:20px;"></div>
<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>&action=run">
    <table class="form-table">
<?php
    global $wpdb;
    $data = $wpdb->get_results('SELECT id,host,scheme FROM '.SEARCH_ENGINE_TBL.'sites ORDER BY CONCAT(scheme,"://",host)',ARRAY_A);
    $data_index = $wpdb->get_results('SELECT t.id,t.site,s.scheme,s.host,t.directory FROM '.SEARCH_ENGINE_TBL.'templates AS t LEFT JOIN '.SEARCH_ENGINE_TBL.'sites AS s ON s.id=t.site ORDER BY CONCAT(s.scheme,"://",s.host,t.directory)',ARRAY_A);
    $existing = $existing_indexes = array();
    $selected = $selected_index = false;
    $default_site = get_option('search_engine_default_site');
    if(0<$default_site)
        $selected = $default_site;
    if(!empty($data)) foreach($data as $row)
    {
        $existing[$row['id']] = $row['scheme'].'://'.$row['host'];
        if(false===$selected)
        {
            if($existing[$row['id']]==get_bloginfo('wpurl')||strpos($existing[$row['id']],get_bloginfo('wpurl'))!==false)
                $selected = $row['id'];
            elseif($row['id']==$obj->row['site']&&$create==0)
                $selected = $row['id'];
        }
    }
    if(!empty($data_index)) foreach($data_index as $row)
    {
        $existing_indexes[$row['id']] = $row['scheme'].'://'.$row['host'].$row['directory'];
        if($existing_indexes[$row['id']]==get_bloginfo('wpurl')||strpos($existing_indexes[$row['id']],get_bloginfo('wpurl'))!==false)
            $selected_index = $row['id'];
        elseif($row['site']==$obj->row['site']&&$create==0)
            $selected_index = $row['id'];
    }
    if($selected_index!==false)
        $selected = false;
    if($selected===false&&$selected_index===false)
    {
?>
        <tr valign="top">
            <th scope="row"><label for="site_url">Index a New Site</label></th>
            <td>
                <input name="site_url" type="text" id="site_url" value="<?php bloginfo('wpurl'); ?>" class="regular-text"<?php if(!empty($existing)) { ?> onchange="jQuery('#existing_site').val(0);jQuery('#existing_template').val(0);" onblur="jQuery('#existing_site').val((jQuery('#site_url').val()!=''?0:jQuery('#existing_site').val()));jQuery('#existing_template').val((jQuery('#site_url').val()!=''?0:jQuery('#existing_template').val()));"<?php } ?> />
                <span class="description">(Ex. http://www.yoursite.com or www.yoursite.com)</span>
            </td>
        </tr>
<?php
        if(!empty($existing_indexes))
        {
?>
        <tr valign="top">
            <th scope="row"><label for="existing_template">or Reindex a Site</label></th>
            <td>
                <select name="existing_template" id="existing_template" onchange="jQuery('#site_url').val((jQuery('#existing_template').val()==0?jQuery('#site_url').val():''));jQuery('#existing_site').val((jQuery('#existing_template').val()==0?jQuery('#existing_site').val():0));">
                    <option value="0" SELECTED>-- Select One --</option>
<?php
            foreach($existing_indexes as $id => $site)
            {
?>
                    <option value="<?php echo $id; ?>"><?php echo $site; ?>&nbsp;&nbsp;</option>
<?php
            }
?>
                </select>
            </td>
        </tr>
<?php
        }
        if(!empty($existing))
        {
?>
        <tr valign="top">
            <th scope="row"><label for="existing_site">or Configure a New Index of an Existing Site</label></th>
            <td>
                <select name="existing_site" id="existing_site" onchange="jQuery('#site_url').val((jQuery('#existing_site').val()==0?jQuery('#site_url').val():''));jQuery('#existing_template').val((jQuery('#existing_site').val()==0?jQuery('#existing_template').val():0));">
                    <option value="0" SELECTED>-- Select One --</option>
<?php
            foreach($existing as $id => $site)
            {
?>
                    <option value="<?php echo $id; ?>"><?php echo $site; ?>&nbsp;&nbsp;</option>
<?php
            }
?>
                </select>
            </td>
        </tr>
<?php
        }
    }
    else
    {
        if(!empty($existing_indexes))
        {
?>
        <tr valign="top">
            <th scope="row"><label for="existing_template">Reindex a Site</label></th>
            <td>
                <select name="existing_template" id="existing_template" onchange="jQuery('#site_url').val((jQuery('#existing_template').val()==0?jQuery('#site_url').val():''));jQuery('#existing_site').val((jQuery('#existing_template').val()==0?jQuery('#existing_site').val():0));">
                    <option value="0">-- Select One --</option>
<?php
            foreach($existing_indexes as $id => $site)
            {
?>
                    <option value="<?php echo $id; ?>"<?php echo ($selected_index==$id?' SELECTED':''); ?>><?php echo $site; ?>&nbsp;&nbsp;</option>
<?php
            }
?>
                </select>
            </td>
        </tr>
<?php
        }
?>
        <tr valign="top">
            <th scope="row"><label for="existing_site"><?php echo (!empty($existing_indexes))?'or ':''; ?>Configure a New Index of an Existing Site</label></th>
            <td>
                <select name="existing_site" id="existing_site" onchange="jQuery('#site_url').val((jQuery('#existing_site').val()==0?jQuery('#site_url').val():''));jQuery('#existing_template').val((jQuery('#existing_site').val()==0?jQuery('#existing_template').val():0));">
                    <option value="0">-- Select One --</option>
<?php
        foreach($existing as $id => $site)
        {
?>
                    <option value="<?php echo $id; ?>"<?php echo ($selected==$id?' SELECTED':''); ?>><?php echo $site; ?>&nbsp;&nbsp;</option>
<?php
        }
?>
                </select>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="site_url">or Index a New Site</label></th>
            <td>
                <input name="site_url" type="text" id="site_url" value="" class="regular-text"<?php if(!empty($existing)) { ?> onchange="jQuery('#existing_site').val(0);jQuery('#existing_template').val(0);" onblur="jQuery('#existing_site').val((jQuery('#site_url').val()!=''?0:jQuery('#existing_site').val()));jQuery('#existing_template').val((jQuery('#site_url').val()!=''?0:jQuery('#existing_template').val()));"<?php } ?> />
                <span class="description">Enter the URL of the site<br />(Ex. http://www.yoursite.com or www.yoursite.com)</span>
            </td>
        </tr>
<?php
    }
?>
    </table>
    <input type="hidden" name="show_advanced" id="show_advanced" value="0" />
    <p class="submit">
        <input type="button" name="Advanced" class="button-secondary" value="Show Advanced Options" onclick="jQuery('#search_engine_advanced').toggle();this.value=(this.value=='Show Advanced Options'?'Hide Advanced Options':'Show Advanced Options');jQuery('#show_advanced').val((jQuery('#show_advanced').val()==1?0:1));" />
        <input type="submit" name="Submit" class="button-primary" value="Index my Site &raquo;" />
    </p>
    <div id="search_engine_advanced" style="display:none;">
        <h3>Advanced Options</h3>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="max_depth">Maximum Link Depth</label></th>
                <td>
                    <input name="max_depth" type="text" id="max_depth" value="0" class="small-text" />
                    <span class="description">This is the max number of levels the search engine will crawl into your site. Enter 0 for no limit. This is not the same as the URL levels (www.mysite.com/level-1/level-2/level-3/). Levels are when the Spider crawls</span>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="cross_scheme">Index Across Schemes</label></th>
                <td>
                    <input name="cross_scheme" type="checkbox" id="cross_scheme" value="1" class="small-text" />
                    <span class="description">By default when spidering, only links that are within the same 'http://' or 'https://' scheme as the site above will be indexed. Check this box to allow indexing of both 'http://' and 'https://' for the site</span>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="blacklist_words">Blacklisted Keywords</label></th>
                <td>
                    <p>Blacklisted words will not be indexed by the search engine. Each word should be separated with a comma.</p>
                    <textarea name="blacklist_words" rows="10" cols="50" id="blacklist_words" class="large-text code"></textarea>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="blacklist_uri_words">Blacklisted URLs</label></th>
                <td>
                    <p>Blacklisted URLs will not be indexed by the search engine. You may include URLs, URIs, or Keywords to be excluded. These should be separated with a comma.</p>
                    <textarea name="blacklist_uri_words" rows="10" cols="50" id="blacklist_uri_words" class="large-text code"></textarea>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="whitelist_uri_words">Whitelisted URLs</label></th>
                <td>
                    <p>Whitelisted URLs will be the only ones that the search engine will scan. You may include URLs, URIs, or Keywords to be included. These should be separated with a comma.</p>
                    <textarea name="whitelist_uri_words" rows="10" cols="50" id="whitelist_uri_words" class="large-text code"></textarea>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="disable_robots">Disregard "Robots"-specific Rules</label></th>
                <td>
                    <input name="disable_robots" type="checkbox" id="disable_robots" value="1" class="small-text" />
                    <span class="description">Don't follow robots.txt and other meta/link robot-related rules</span>
                </td>
            </tr>
        </table>
        <h3>Password Protected Content (htaccess-only)</h3>
        <p>If parts / all of your site is  password protected content through an htaccess file and you want to scan those pages, please enter your username and password below.</p>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="htaccess_username">Username</label></th>
                <td><input name="htaccess_username" type="text" id="htaccess_username" value="" class="regular-text" autocomplete="off" /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="htaccess_password">Password</label></th>
                <td><input name="htaccess_password" type="text" id="htaccess_password" value="" class="regular-text" autocomplete="off" /></td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="Submit" class="button-primary" value="Index my Site &raquo;" />
        </p>
    </div>
</form>
</div>
<?php
    }
}
function search_engine_wizard_run ()
{
    $template_id = $site_id = 0;
    $token = get_option('search_engine_token');
    if(empty($token))
    {
        if(defined('NONCE_KEY'))
            $token = md5(NONCE_KEY);
        elseif(defined('AUTH_KEY'))
            $token = md5(AUTH_KEY);
        elseif(defined('SECURE_AUTH_KEY'))
            $token = md5(SECURE_AUTH_KEY);
        elseif(defined('LOGGED_IN_KEY'))
            $token = md5(LOGGED_IN_KEY);
        else
            $token = md5(microtime().wp_generate_password(20,true));
        update_option('search_engine_token',$token);
    }
    require_once SEARCH_ENGINE_DIR.'/classes/API.class.php';
    $created = false;
    if(!empty($_POST))
    {
        $url = '';
        if(!empty($_POST['site_url']))
        {
            $api = new Search_Engine_API();
            $parsed = parse_url($_POST['site_url']);
            $url = $_POST['site_url'];
            if(!isset($parsed['host']))
            {
                $parsed = parse_url('http://'.$_POST['site_url']);
                $url = 'http://'.$_POST['site_url'];
            }
            if($parsed===false||empty($parsed))
                die('<strong>Error:</strong> Invalid URL entered');
            $site = $api->get_site(array('host'=>$parsed['host'],'scheme'=>$parsed['scheme']));
            if($site===false)
            {
                $site_id = $api->save_site(array('host'=>$parsed['host'],'scheme'=>$parsed['scheme'],'group'=>0));
            }
            else
            {
                $site_id = $site['id'];
            }
        }
        elseif(!empty($_POST['existing_template']))
        {
            $template_id = $_POST['existing_template'];
        }
        elseif(!empty($_POST['existing_site']))
        {
            $site_id = $_POST['existing_site'];
        }
        if($template_id==0&&$site_id>0)
        {
            $_POST['site'] = $site_id;
            if(empty($parsed))
                $parsed = parse_url($url);
            if(!isset($_POST['cross_scheme'])||empty($_POST['cross_scheme']))
                $_POST['cross_scheme'] = 0;
            $_POST['directory'] = $parsed['path'];
            require_once SEARCH_ENGINE_DIR.'/wp-admin-ui/Admin.class.php';
            $columns = array('site'=>array('custom_display'=>'search_engine_display_site','custom_relate'=>array('table'=>SEARCH_ENGINE_TBL.'sites','what'=>array('scheme','host'))),'indexed'=>array('label'=>'Last Indexed','type'=>'date'),'updated'=>array('label'=>'Last Modified','type'=>'date'),'id'=>array('label'=>'Template ID'));
            $form_columns = $columns;
            unset($form_columns['id']);
            unset($form_columns['indexed']);
            $form_columns['updated']['date_touch'] = true;
            $form_columns['updated']['display'] = false;
            //$form_columns[] = 'group';
            $form_columns[] = 'directory';
            $form_columns[] = 'max_depth';
            $form_columns[] = 'cross_scheme';
            $form_columns[] = 'blacklist_words';
            $form_columns[] = 'blacklist_uri_words';
            $form_columns[] = 'whitelist_uri_words';
            $form_columns[] = 'disable_robots';
            $form_columns[] = 'htaccess_username';
            $form_columns[] = 'htaccess_password';
            $admin = new WP_Admin_UI(array('api'=>true,'do'=>'create','item'=>'Index Template','items'=>'Index Templates','table'=>SEARCH_ENGINE_TBL.'templates','columns'=>$columns,'form_columns'=>$form_columns,'icon'=>SEARCH_ENGINE_URL.'/assets/icons/search_32.png','custom'=>array('form'=>'search_engine_index_form','action_end_view'=>'search_engine_index_run','header'=>'search_engine_index_header')));
            $admin->go();
            $template_id = $admin->insert_id;
        }
        $created = true;
    }
    global $wpdb;
    if(isset($_GET['site_id']))
        $site_id = $_GET['site_id'];
    if(isset($_GET['template_id']))
        $template_id = $_GET['template_id'];
?>
<div class="wrap">
    <div id="icon-edit-pages" class="icon32" style="background-position:0 0;background-image:url(<?php echo SEARCH_ENGINE_URL; ?>/assets/icons/search_32.png);"><br /></div>
    <h2>Search Engine - Easy Indexing Wizard</h2>
<?php
    if($template_id==0)
        die('<strong>Error:</strong> Invalid Template');
    if($created!==false)
    {
?>
    <script type="text/javascript">
        document.location = 'admin.php?page=search-engine&action=run&template_id=<?php echo $template_id; ?>';
    </script>
<?php
        die();
    }
?>
    <div style="height:20px;"></div>
    <p>Please wait while your site is spidered and indexed.</p>
    <p class="submit">
        <input type="button" name="Advanced" class="button-secondary" value="Show Detailed Progress" onclick="jQuery('#search_engine_advanced').toggle();this.value=(this.value=='Show Detailed Progress'?'Hide Detailed Progress':'Show Detailed Progress');jQuery('#scroller').stop().scrollTo('100%','0%', { axis:'y' });" />
    </p>
    <link  type="text/css" rel="stylesheet" href="<?php echo SEARCH_ENGINE_URL; ?>/assets/admin.css" />
    <script type="text/javascript" src="<?php echo SEARCH_ENGINE_URL; ?>/assets/admin.js"></script>
    <script type="text/javascript" src="<?php echo SEARCH_ENGINE_URL; ?>/assets/jquery.scrollto.js"></script>
    <script type="text/javascript">
        jQuery(function()
        {
            tothetop();
        });
    </script>
    <div class="search_engine_wizard">
        <div class="loader"><img src="<?php echo SEARCH_ENGINE_URL; ?>/assets/images/ajax-loader.gif" alt="AJAX Loader" /></div>
        <div id="search_engine_advanced">
            <iframe src ="<?php echo SEARCH_ENGINE_URL; ?>/cronjob.php?<?php echo ($site_id>0)?'site_id='.$site_id.'&':''; ?>template_id=<?php echo $template_id; ?>&token=<?php echo $token; ?>" width="100%" height="500px" id="scroller" onload="iframedone('#scroller');">
                <p>Your browser does not support iframes. <a href="<?php echo SEARCH_ENGINE_URL; ?>/cronjob.php?<?php echo ($site_id>0)?'site_id='.$site_id.'&':''; ?>template_id=<?php echo $template_id; ?>&token=<?php echo $token; ?>">Click here to run the index.</a></p>
            </iframe>
            <p id="startstop"><span class="startstop stop">[<a href="#" onclick="clearTimeout(t);jQuery('.startstop').toggle();return false;">pause autoscrolling</a>]</span><span class="startstop">[<a href="#" onclick="t = setTimeout('tothetop()',1000);jQuery('.startstop').toggle();return false;">resume autoscrolling</a>]</span></p>
            <div class="loader"></div>
        </div>
        <p><em>To automatically run this on your server via a Cronjob, use the following URL:<br /><input type="text" value="<?php echo SEARCH_ENGINE_URL; ?>/cronjob.php?<?php echo ($site_id>0)?'site_id='.$site_id.'&':''; ?>template_id=<?php echo $template_id; ?>&token=<?php echo $token; ?>" style="width:100%;" /></em></p>
    </div>
</div>
<?php
}
function search_engine_index_templates ()
{
    require_once SEARCH_ENGINE_DIR.'/classes/API.class.php';
    if(isset($_GET['do'])&&$_GET['do']=='save'&&!empty($_POST))
    {
        if(!empty($_POST['site_url']))
        {
            $api = new Search_Engine_API();
            $parsed = parse_url($_POST['site_url']);
            $site = $api->get_site(array('host'=>$parsed['host'],'scheme'=>$parsed['scheme']));
            if($site===false)
                $site_id = $api->save_site(array('host'=>$parsed['host'],'scheme'=>$parsed['scheme'],'group'=>0));
            else
                $site_id = $site['id'];
        }
        else
            $site_id = $_POST['existing_site'];
        $_POST['site'] = $site_id;
    }
    require_once SEARCH_ENGINE_DIR.'/wp-admin-ui/Admin.class.php';
    $columns = array('site'=>array('custom_display'=>'search_engine_display_site','custom_relate'=>array('table'=>SEARCH_ENGINE_TBL.'sites','what'=>array('scheme','host'),'is'=>'site')),'indexed'=>array('label'=>'Last Indexed','type'=>'date'),'updated'=>array('label'=>'Last Modified','type'=>'date'),'id'=>array('label'=>'Template ID'));
    $admin = new WP_Admin_UI(array('item'=>'Index Template','items'=>'Index Templates','table'=>SEARCH_ENGINE_TBL.'templates','columns'=>$columns,'icon'=>SEARCH_ENGINE_URL.'/assets/icons/search_32.png','custom'=>array('action_end_view'=>'search_engine_index_run','header'=>'search_engine_index_header','index'=>array('label'=>'Index Again','link'=>'admin.php?page=search-engine&action=run&template_id={@id}'),'reindex'=>array('label'=>'Reindex','link'=>'admin.php?page=search-engine&action=run&template_id={@id}&reindex=1')),'edit'=>false,'save'=>false,'add'=>false));
    $admin->go();
?>
<?php
}
function search_engine_index_header ($obj)
{
?>
<p>Index Templates are created when you index using the Easy Indexing Wizard. These templates are here so you don't have to re-enter your information, and can run separate indexes with completely independant options. You will also reference your Template ID when setting up Cronjobs on your Webhost to automatically re-index.</p>
<?php
}
function search_engine_index_run ($obj,$row)
{
?>
<span class='reindex'><a href="<?php echo $obj->var_update(array('page'=>'search-engine','action'=>'run','template_id'=>$row['id'])); ?>" title="Reindex using this template">Reindex</a> | </span>
<?php
}
function search_engine_index_form ($obj,$create=0)
{
    $submit = 'Add '.$obj->item;
    $id = '';
    if($create==0)
    {
        if(empty($obj->row))
            $obj->get_row();
        if(empty($obj->row))
            return $obj->message("<strong>Error:</strong> $obj->item not found.");
        $submit = 'Save Changes';
        $id = $obj->row['id'];
    }
    else
    {
        $obj->row = array('max_depth'=>'','scheme'=>'','blacklist_words'=>'','blacklist_uri_words'=>'','whitelist_uri_words'=>'','disable_robots'=>'','htaccess_username'=>'','htaccess_password'=>'');
    }
 ?>
    <div style="height:20px;"></div>
<form method="post" action="<?php echo $obj->var_update(array('action'=>$obj->action,'do'=>'save','id'=>$id)); ?>">
    <table class="form-table">
<?php
    global $wpdb;
    $data = $wpdb->get_results('SELECT id,host,scheme FROM '.SEARCH_ENGINE_TBL.'sites ORDER BY CONCAT(scheme,"://",host)',ARRAY_A);
    $data_index = $wpdb->get_results('SELECT t.id,t.site,s.scheme,s.host,t.directory FROM '.SEARCH_ENGINE_TBL.'templates AS t LEFT JOIN '.SEARCH_ENGINE_TBL.'sites AS s ON s.id=t.site ORDER BY CONCAT(s.scheme,"://",s.host,t.directory)',ARRAY_A);
    $existing = $existing_indexes = array();
    $selected = $selected_index = false;
    $default_site = get_option('search_engine_default_site');
    if(0<$default_site)
        $selected = $default_site;
    if(!empty($data)) foreach($data as $row)
    {
        $existing[$row['id']] = $row['scheme'].'://'.$row['host'];
        if(false===$selected)
        {
            if($existing[$row['id']]==get_bloginfo('wpurl')&&$create==1)
                $selected = $row['id'];
            elseif($row['id']==$obj->row['site']&&$create==0)
                $selected = $row['id'];
        }
    }
    if(!empty($data_index)) foreach($data_index as $row)
    {
        $existing_indexes[$row['id']] = $row['scheme'].'://'.$row['host'].$row['directory'];
        if(($existing_indexes[$row['id']]==get_bloginfo('wpurl')||strpos($existing_indexes[$row['id']],get_bloginfo('wpurl'))!==false)&&$create==1)
            $selected_index = $row['id'];
        elseif($row['site']==$obj->row['site']&&$create==0)
            $selected_index = $row['id'];
    }
    if($selected_index!==false)
        $selected = false;
    if($selected===false&&$selected_index===false)
    {
?>
        <tr valign="top">
            <th scope="row"><label for="site_url">Index a New Site</label></th>
            <td>
                <input name="site_url" type="text" id="site_url" value="<?php bloginfo('wpurl'); ?>" class="regular-text"<?php if(!empty($existing)) { ?> onchange="jQuery('#existing_site').val(0);jQuery('#existing_template').val(0);" onblur="jQuery('#existing_site').val((jQuery('#site_url').val()!=''?0:jQuery('#existing_site').val()));jQuery('#existing_template').val((jQuery('#site_url').val()!=''?0:jQuery('#existing_template').val()));"<?php } ?> />
                <span class="description">(Ex. http://www.yoursite.com or www.yoursite.com)</span>
            </td>
        </tr>
<?php
        if(!empty($existing_indexes))
        {
?>
        <tr valign="top">
            <th scope="row"><label for="existing_template">or Reindex a Site</label></th>
            <td>
                <select name="existing_template" id="existing_template" onchange="jQuery('#site_url').val((jQuery('#existing_template').val()==0?jQuery('#site_url').val():''));jQuery('#existing_site').val((jQuery('#existing_template').val()==0?jQuery('#existing_site').val():0));">
                    <option value="0" SELECTED>-- Select One --</option>
<?php
            foreach($existing_indexes as $id => $site)
            {
?>
                    <option value="<?php echo $id; ?>"><?php echo $site; ?>&nbsp;&nbsp;</option>
<?php
            }
?>
                </select>
            </td>
        </tr>
<?php
        }
        if(!empty($existing))
        {
?>
        <tr valign="top">
            <th scope="row"><label for="existing_site">or Configure a New Index of an Existing Site</label></th>
            <td>
                <select name="existing_site" id="existing_site" onchange="jQuery('#site_url').val((jQuery('#existing_site').val()==0?jQuery('#site_url').val():''));jQuery('#existing_template').val((jQuery('#existing_site').val()==0?jQuery('#existing_template').val():0));">
                    <option value="0" SELECTED>-- Select One --</option>
<?php
            foreach($existing as $id => $site)
            {
?>
                    <option value="<?php echo $id; ?>"><?php echo $site; ?>&nbsp;&nbsp;</option>
<?php
            }
?>
                </select>
            </td>
        </tr>
<?php
        }
    }
    else
    {
        if(!empty($existing_indexes))
        {
?>
        <tr valign="top">
            <th scope="row"><label for="existing_template">Reindex a Site</label></th>
            <td>
                <select name="existing_template" id="existing_template" onchange="jQuery('#site_url').val((jQuery('#existing_template').val()==0?jQuery('#site_url').val():''));jQuery('#existing_site').val((jQuery('#existing_template').val()==0?jQuery('#existing_site').val():0));">
                    <option value="0">-- Select One --</option>
<?php
            foreach($existing_indexes as $id => $site)
            {
?>
                    <option value="<?php echo $id; ?>"<?php echo ($selected_index==$id?' SELECTED':''); ?>><?php echo $site; ?>&nbsp;&nbsp;</option>
<?php
            }
?>
                </select>
            </td>
        </tr>
<?php
        }
?>
        <tr valign="top">
            <th scope="row"><label for="existing_site"><?php echo (!empty($existing_indexes))?'or ':''; ?>Configure a New Index of an Existing Site</label></th>
            <td>
                <select name="existing_site" id="existing_site" onchange="jQuery('#site_url').val((jQuery('#existing_site').val()==0?jQuery('#site_url').val():''));jQuery('#existing_template').val((jQuery('#existing_site').val()==0?jQuery('#existing_template').val():0));">
                    <option value="0">-- Select One --</option>
<?php
        foreach($existing as $id => $site)
        {
?>
                    <option value="<?php echo $id; ?>"<?php echo ($selected==$id?' SELECTED':''); ?>><?php echo $site; ?>&nbsp;&nbsp;</option>
<?php
        }
?>
                </select>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="site_url">or Index a New Site</label></th>
            <td>
                <input name="site_url" type="text" id="site_url" value="" class="regular-text"<?php if(!empty($existing)) { ?> onchange="jQuery('#existing_site').val(0);jQuery('#existing_template').val(0);" onblur="jQuery('#existing_site').val((jQuery('#site_url').val()!=''?0:jQuery('#existing_site').val()));jQuery('#existing_template').val((jQuery('#site_url').val()!=''?0:jQuery('#existing_template').val()));"<?php } ?> />
                <span class="description">Enter the URL of the site<br />(Ex. http://www.yoursite.com or www.yoursite.com)</span>
            </td>
        </tr>
<?php
    }
?>
    </table>
    <input type="hidden" name="show_advanced" id="show_advanced" value="0" />
    <p class="submit">
        <input type="button" name="Advanced" class="button-secondary" value="Show Advanced Options" onclick="jQuery('#search_engine_advanced').toggle();this.value=(this.value=='Show Advanced Options'?'Hide Advanced Options':'Show Advanced Options');jQuery('#show_advanced').val((jQuery('#show_advanced').val()==1?0:1));" />
        <input type="submit" name="Submit" class="button-primary" value="<?php echo $submit; ?>" />
    </p>
    <div id="search_engine_advanced" style="display:none;">
        <h3>Advanced Options</h3>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="max_depth">Maximum Link Depth</label></th>
                <td>
                    <input name="max_depth" type="text" id="max_depth" value="<?php echo $obj->row['max_depth']; ?>" class="small-text" />
                    <span class="description">This is the max number of  levels the search engine will crawl into your site. Enter 0 for no limit. This is not the same as the URL levels (www.mysite.com/level-1/level-2/level-3/). Levels are when the Spider crawls</span>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="cross_scheme">Index Across Schemes</label></th>
                <td>
                    <input name="cross_scheme" type="checkbox" id="cross_scheme" value="1" class="small-text"<?php echo ($obj->row['cross_scheme']==1?' CHECKED':''); ?> />
                    <span class="description">By default when spidering, only links that are within the same 'http://' or 'https://' scheme as the site above will be indexed. Check this box to allow indexing of both 'http://' and 'https://' for the site</span>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="blacklist_words">Blacklisted Keywords</label></th>
                <td>
                    <p>Blacklisted words will not be indexed by the search engine. Each word should be separated with a comma.</p>
                    <textarea name="blacklist_words" rows="10" cols="50" id="blacklist_words" class="large-text code"><?php echo $obj->row['blacklist_words']; ?></textarea>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="blacklist_uri_words">Blacklisted URLs</label></th>
                <td>
                    <p>Blacklisted URLs will not be indexed by the search engine. You may include URLs, URIs, or Keywords to be excluded. These should be separated with a comma.</p>
                    <textarea name="blacklist_uri_words" rows="10" cols="50" id="blacklist_uri_words" class="large-text code"><?php echo $obj->row['blacklist_uri_words']; ?></textarea>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="whitelist_uri_words">Whitelisted URLs</label></th>
                <td>
                    <p>Whitelisted URLs will be the only ones that the search engine will scan. You may include URLs, URIs, or Keywords to be included. These should be separated with a comma.</p>
                    <textarea name="whitelist_uri_words" rows="10" cols="50" id="whitelist_uri_words" class="large-text code"><?php echo $obj->row['whitelist_uri_words']; ?></textarea>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="disable_robots">Disregard "Robots"-specific Rules</label></th>
                <td>
                    <input name="disable_robots" type="checkbox" id="disable_robots" value="1" class="small-text"<?php echo ($obj->row['disable_robots']==1?' CHECKED':''); ?> />
                    <span class="description">Don't follow robots.txt and other meta/link robot-related rules</span>
                </td>
            </tr>
        </table>
        <h3>Password Protected Content (htaccess-only)</h3>
        <p>If parts / all of your site is  password protected content through an htaccess file and you want to scan those pages, please enter your username and password below.</p>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="htaccess_username">Username</label></th>
                <td><input name="htaccess_username" type="text" id="htaccess_username" value="<?php echo $obj->row['htaccess_username']; ?>" class="regular-text" autocomplete="off" /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="htaccess_password">Password</label></th>
                <td><input name="htaccess_password" type="password" id="htaccess_password" value="<?php echo $obj->row['htaccess_password']; ?>" class="regular-text" autocomplete="off" /></td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="Submit" class="button-primary" value="<?php echo $submit; ?>" />
        </p>
    </div>
</form>
<?php
}
function search_engine_xml_sitemaps ()
{
    require_once SEARCH_ENGINE_DIR.'/wp-admin-ui/Admin.class.php';
    $columns = array('host'=>array('label'=>'Site'),'indexed'=>array('label'=>'Last Indexed','type'=>'date'),'updated'=>array('label'=>'Last Modified','type'=>'date'));
    $admin = new WP_Admin_UI(array('item'=>'XML Sitemap','items'=>'XML Sitemaps','table'=>SEARCH_ENGINE_TBL.'sites','columns'=>$columns,'icon'=>SEARCH_ENGINE_URL.'/assets/icons/search_32.png','readonly'=>true,'custom'=>array('download_xml'=>array('label'=>'Download XML','link'=>SEARCH_ENGINE_URL.'/xml-sitemap.php?site_id={@id}&token='.get_option('search_engine_token')))));
    $admin->go();
}
function search_engine_groups ()
{
    require_once SEARCH_ENGINE_DIR.'/wp-admin-ui/Admin.class.php';
    $columns = array('name','created'=>array('label'=>'Date Created','type'=>'date'),'updated'=>array('label'=>'Last Modified','type'=>'date'));
    $form_columns = $columns;
    $form_columns['created']['date_touch_on_create'] = true;
    $form_columns['created']['display'] = false;
    $form_columns['updated']['date_touch'] = true;
    $form_columns['updated']['display'] = false;
    $admin = new WP_Admin_UI(array('item'=>'Group','items'=>'Groups','table'=>SEARCH_ENGINE_TBL.'groups','columns'=>$columns,'form_columns'=>$form_columns,'icon'=>SEARCH_ENGINE_URL.'/assets/icons/search_32.png'));
    $admin->go();
}
function search_engine_groups_form ($obj)
{

}
function search_engine_view_index ()
{
    require_once SEARCH_ENGINE_DIR.'/wp-admin-ui/Admin.class.php';
    $columns = array('host'=>array('label'=>'Site URL','custom_display'=>'search_engine_view_index_display'),'indexed'=>array('label'=>'Date Indexed','type'=>'date'),'updated'=>array('label'=>'Last Modified','type'=>'date'));
    $form_columns = $columns;
    unset($form_columns['indexed']);
    $form_columns[] = 'scheme';
    $form_columns['updated']['date_touch'] = true;
    $form_columns['updated']['display'] = false;
    $admin = new WP_Admin_UI(array('item'=>'Index','items'=>'Index','table'=>SEARCH_ENGINE_TBL.'sites','columns'=>$columns,'form_columns'=>$form_columns,'icon'=>SEARCH_ENGINE_URL.'/assets/icons/search_32.png','custom'=>array('action_end_view'=>'search_engine_view_index_run','view'=>'search_engine_view_index_details'),'edit'=>false,'add'=>false,'view'=>true));
    $admin->go();
}
function search_engine_view_index_details ($obj)
{
    //
}
function search_engine_view_index_display ($column,$row,$obj)
{
    return $row['scheme'].'://'.$row['host'].'/';
}
function search_engine_view_index_run ($obj,$row)
{
?>
<span class='reindex'><a href="<?php echo $obj->var_update(array('page'=>'search-engine','action'=>'run','site_id'=>$row['id'])); ?>" title="Reindex this site">Reindex</a> | </span>
<?php
}
function search_engine_logs ()
{
    require_once SEARCH_ENGINE_DIR.'/wp-admin-ui/Admin.class.php';
    $admin = new WP_Admin_UI(array('export'=>true,'item'=>'Search Log','items'=>'Search Logs','table'=>SEARCH_ENGINE_TBL.'log','columns'=>array('query','time'=>array('type'=>'date','label'=>'Date of Search','filter'=>true),'elapsed'=>array('label'=>'Processing Time (seconds)'),'results'=>array('label'=>'Total Results Found')),'add'=>false,'edit'=>false,'delete'=>false,'icon'=>SEARCH_ENGINE_URL.'/assets/icons/search_32.png'));
    $admin->go();
}
function search_engine_settings ()
{
    global $wpdb;
    if(!empty($_POST))
    {
        delete_option('search_engine_default_site');
        if(!empty($_POST['default_site']))
            add_option('search_engine_default_site',$_POST['default_site']);
        if(!empty($_POST['cronjob_token']))
            update_option('search_engine_token',$_POST['cronjob_token']);
        if(!empty($_POST['reset']))
            search_engine_reset();
    }
?>
<div class="wrap">
    <div id="icon-edit-pages" class="icon32" style="background-position:0 0;background-image:url(<?php echo SEARCH_ENGINE_URL; ?>/assets/icons/search_32.png);"><br /></div>
    <h2>Search Engine - Settings</h2>
    <div style="height:20px;"></div>
    <form method="post" action="">
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="default_site">Default Site for Search</label></th>
                <td>
                    <select name="default_site" id="default_site">
                        <option value="0">-- Select One --</option>
<?php
    $selected = get_option('search_engine_default_site');
    if(empty($selected))
        $selected = false;
    $data = $wpdb->get_results('SELECT id,host,scheme FROM '.SEARCH_ENGINE_TBL.'sites ORDER BY CONCAT(scheme,"://",host)',ARRAY_A);
    if(!empty($data)) foreach($data as $row)
    {
        $url = $row['scheme'].'://'.$row['host'];
        if(false===$selected&&$url==get_bloginfo('wpurl'))
            $selected = $row['id'];
        elseif($row['id']==$selected)
            $selected = $row['id'];
?>
                        <option value="<?php echo $row['id']; ?>"<?php echo ($selected==$row['id']?' SELECTED':''); ?>><?php echo $url; ?>&nbsp;&nbsp;</option>
<?php
    }
?>
                    </select><br />
                    <span class="description">
                        By default, we use the current site as the default, if you'd like this set to something different, please select the site above.<br />
                        <em>The default site is also used for Object Monitoring as the Host / Scheme instead of the WP default.</em>
                    </span>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="cronjob_token">Cronjob Token</label></th>
                <td>
                    <input name="cronjob_token" type="text" id="cronjob_token" size="50" value="<?php echo get_option('search_engine_token'); ?>" /><br />
                    <span class="description">By default, this is generated based on your secure keys from wp-config.php - but you can change it here. Make sure it's secure and that no one else gets this -- this key allows you to Index your site from a Cronjob on your server or by accessing the URL.<br />
                        <label for="cronjob_url" style="font-style:normal;"><strong>URL to Cronjob:</strong></label><br /><input type="text" id="cronjob_url" style="width:100%;" value="<?php echo SEARCH_ENGINE_URL; ?>/cronjob.php?template_id=YOUR_TEMPLATE_ID&token=<?php echo get_option('search_engine_token'); ?>" /></span>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="reset">Reset ALL Data</label></th>
                <td>
                    <input name="reset" type="checkbox" id="reset" value="1" /> - <strong><em>Be sure you want to do this!</em></strong>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="Submit" class="button-primary" value="Save Settings" />
        </p>
    </form>
</div>
<?php
}
function search_engine_search_settings ()
{
    global $wpdb;
    require_once SEARCH_ENGINE_DIR.'/wp-admin-ui/Admin.class.php';
    $columns = array('name','searched'=>array('label'=>'Last Searched','type'=>'date'),'updated'=>array('label'=>'Last Modified','type'=>'date'));
    $form_columns = $columns;
    unset($form_columns['searched']);
    $form_columns['sites'] = array('type'=>'desc');
    $form_columns['query_filters'] = array('type'=>'desc');
    $form_columns['uri_filters'] = array('type'=>'desc');
    $form_columns['updated']['date_touch'] = true;
    $form_columns['updated']['display'] = false;
    $wpdb->query('SELECT SQL_CALC_FOUND_ROWS id FROM '.SEARCH_ENGINE_TBL.'search LIMIT 1');
    $count = @current($wpdb->get_results("SELECT FOUND_ROWS()"));
    $found = 'FOUND_ROWS()';
    $count = $count->$found;
    $delete = true;
    if($count<2||(isset($_GET['action'])&&$_GET['delete']&&$count<3))
        $delete = false;
    $admin = new WP_Admin_UI(array('item'=>'Search Setting','items'=>'Search Settings','table'=>SEARCH_ENGINE_TBL.'search','columns'=>$columns,'form_columns'=>$form_columns,'delete'=>$delete,'icon'=>SEARCH_ENGINE_URL.'/assets/icons/search_32.png'));
    $admin->go();
}
function search_engine_about ()
{
?>
<div class="wrap">
    <div id="icon-edit-pages" class="icon32" style="background-position:0 0;background-image:url(<?php echo SEARCH_ENGINE_URL; ?>/assets/icons/search_32.png);"><br /></div>
    <h2>About the Search Engine plugin</h2>
    <div style="height:20px;"></div>
    <link  type="text/css" rel="stylesheet" href="<?php echo SEARCH_ENGINE_URL; ?>/assets/admin.css" />
    <table class="form-table about">
        <tr valign="top">
            <th scope="row">About the Plugin Author</th>
            <td><a href="http://www.scottkclark.com/">Scott Kingsley Clark</a> from <a href="http://skcdev.com/">SKC Development</a>
                <span class="description">Scott specializes in WordPress and Pods CMS Framework development using PHP, MySQL, and AJAX. Scott is also a developer on the <a href="http://podscms.org/">Pods CMS Framework</a> plugin and has a creative outlet in music with his <a href="http://www.softcharisma.com/">Soft Charisma</a></span></td>
        </tr>
        <tr valign="top">
            <th scope="row">Official Support</th>
            <td><a href="http://www.scottkclark.com/forums/search-engine/">Search Engine - Support Forums</a></td>
        </tr>
        <tr valign="top">
            <th scope="row">Plugin Contributors</th>
            <td>
                <ul>
                    <li><a href="http://utdallas.edu/~derekbeaton/">Derek Beaton</a> - PHP Development for Search.class.php on AND / OR / "Exact Phrase" searching</li>
                    <li><a href="http://studioslice.com/">Greg Dean</a> - Initial PHP Development help for Search.class.php</li>
                    <li><a href="http://gaarai.com/">Chris Jean</a> from <a href="http://ithemes.com/">iThemes</a> - PHP Development for Displaying Results on WP Search Pages</li>
                    <li><a href="http://randyjensenonline.com/">Randy Jansen</a> - HTML / CSS Development for Wizard UI and Search Results Template</li>
                    <li><a href="http://cdharrison.com/">cdharrison</a> - Icon Consulting</li>
                    <li><a href="http://andyweigel.com/">Andy Weigel</a> - PHP Consulting</li>
                </ul>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">Awesome Plugin Testers</th>
            <td>
                <ul>
                    <li><a href="http://danielkoskinen.com/">Daniel Koskinen</a></li>
                    <li><a href="http://www.webdesign29.net/">Benjamin Favre</a></li>
                </ul>
                <p>These testers are helping make this Beta plugin get to 1.0! If you are interested in being listed here, test the heck out of this plugin and let me know if you've found anything that doesn't work right!</p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">Features</th>
            <td>
                <ul>
                    <li><strong>Administration</strong>
                        <ul>
                            <li>Easy Indexing Wizard - Create Templates and Index Existing / New Sites</li>
                            <li>Index Templates - Reindex Sites via the Wizard or via cronjob.php</li>
                            <li>View Search Logs - View queries recently typed, how long it took them to process, and how many results were returned</li>
                            <li>XML Sitemaps - Download an XML Sitemap based on your site's full index</li>
                            <li>Admin.Class.php - A class for plugins to manage data using the WordPress UI appearance</li>
                        </ul>
                    </li>
                    <li><strong>Spider</strong>
                        <ul>
                            <li>Link Extraction - Get links from pages from &lt;a&gt;,  &lt;area&gt;, &lt;iframe&gt;, and &lt;frame&gt; tags</li>
                            <li>Link Redirection - Follows all 301 / 302 Redirects</li>
                            <li>Link Validation - Check for Invalid URLs / Content-Types</li>
                            <li>robots.txt Protocol Support - Follows rules from robots.txt at root of domain</li>
                            <li>nofollow / noindex Support - Follows specific rules from Robots Meta and &lt;a&gt; tags</li>
                            <li>Depth Restricting - Restrict how deep spidering goes</li>
                            <li>URL Words Whitelist / Blacklist - Include or Exclude URLs from being spidered based on words</li>
                            <li>.htaccess Password Protection Support - Optional Username / Password can be passed to Spider to access restricted areas</li>
                            <li>cronjob.php - Script to use when setting up Server Cronjob settings - Can Spider based on Site or Template - URL found in Search Engine Settings panel</li>
                        </ul>
                    </li
                    <li><strong>Index</strong>
                        <ul>
                            <li>Object Monitoring - When you Add / Edit / Delete and WP Post + Page + CPT item, Search Engine will automatically make the adjustment in the Index (by Adding, Updating, or Removing the link + content in the Index)</li>
                            <li>Keyword Extraction - Get keywords from pages from &lt;title&gt;,  &lt;meta name="description"&gt;, &lt;meta name="keywords"&gt;, and &lt;body&gt; tags</li>
                            <li>Keyword Blacklist - Exclude Keywords from being Indexed</li>
                            <li>Index Weight Assignment - Based on how often keywords are used, and in which places they are found, a weight is given to a URL to create relevancy</li>
                        </ul>
                    </li>
                    <li><strong>Search</strong>
                        <ul>
                            <li>AND / OR / "Exact Phrase" multi-combination Support</li>
                            <li>OR Support</li>
                            <li>Shortcode can be used in WP Pages to display form and search specific site(s) (by site ID) - [search_engine sites="1,4,5"]</li>
                            <li>search_engine_form() to display the Search Engine form anywhere on your site - Note, form points to the root of your site for search like normal WP search</li>
                            <li>search_engine_content() can be placed in a template to call the Search Engine form / results</li>
                        </ul>
                    </li>
                </ul>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">Upcoming Features - Roadmap</th>
            <td>
                <dl>
                    <dt>0.6.0</dt>
                    <dd>
                        <ul>
                            <li>Search Settings - Setup / control multiple searches on your site</li>
                            <li>Negative Keyword Matching using -word Format</li>
                            <li>Cronjob Groups - Ability to run multiple templates at a time based on Cronjob Group in cronjob.php</li>
                            <li>Integration with wp_cron - Option to enable a template to rerun on it's own - like magic!</li>
                            <li>View Index Logs - View statistics from indexing like Links Not Found, Links Redirected, etc</li>
                            <li>Inlinks Tracking - Weight should reflect more heavily for pages with more internal links pointing to them</li>
                        </ul>
                    </dd>
                </dl>
            </td>
        </tr>
    </table>
    <div style="height:50px;"></div>
</div>
<?php
}
function search_engine_display_site ($value,$row)
{
    $value = explode(' ',$value);
    $directory = ltrim($row['directory'],'/');
    return $value[0].'://'.$value[1].'/'.$directory;
}

global $search_engine;
if(!is_admin())
{
    add_filter('the_posts','search_engine_get_posts');
    add_shortcode('search_engine','search_engine_content');
}

function search_engine_get_posts ($posts)
{
    // Don't do anything for views that aren't search results
    if (!is_search())
        return $posts;

    // Since we know that this request is for search results, setup loop replacement hooks
    add_action('loop_start','search_engine_loop_start');
    add_action('loop_end','search_engine_loop_end');

    // Send out the CSS team to clean up everything and make it oh so pretty
    if(!defined('SEARCH_ENGINE_CUSTOM_CSS'))
        wp_enqueue_style('search-engine',SEARCH_ENGINE_URL.'/assets/style.css');

    // We let the user optionally choose a different search template to use
    add_filter('template_redirect','search_engine_template');

    // Prevent have_posts from returning false if there aren't any posts
    // This can easily be used to set the number of actual search results
    $posts = get_posts(array('numberposts'=>1,'post_type'=>'any'));

    // If a Search page is found, use it instead (for the added benefit of using a custom template)
    global $wpdb;
    $search = $wpdb->get_results("SELECT * from {$wpdb->prefix}posts WHERE post_type = 'page' and post_name = 'search'");
    if(!empty($search))
        $posts = array($search);

    // Create a virtual page if NO posts are found
    if(empty($posts))
    {
        $object = new stdClass();
        $object->post_title = __("Search");
        $object->post_name = "search";
        $object->post_content = "Searching all your nets!";
        $object->post_type = "page";
        $posts = array($object);
    }

    // This is a filter, so return the posts
    return $posts;
}
function search_engine_loop_start ()
{
    global $search_engine;
    if(is_search()&&!isset($search_engine['started']))
    {
        ob_start();
        $search_engine['started'] = true;
    }
}
function search_engine_loop_end ()
{
    global $search_engine;
    if(is_search()&&!isset($search_engine['ended']))
    {
        ob_end_clean();
        search_engine_content();
        $search_engine['ended'] = true;
    }
}
function search_engine_template()
{
    $template = get_search_template();
    if(empty($template))
        $template = get_page_template();
    if(empty($template))
        $template = TEMPLATEPATH . "/index.php";
    include($template);
    exit;
}
function search_engine_form ()
{
    $query = '';
    if(!wp_style_is('search-engine')&&isset($_GET['q']))
        $query = stripslashes($_GET['q']);
    elseif(isset($_GET['s']))
        $query = stripslashes($_GET['s']);
?>
<form action="<?php bloginfo('wpurl'); ?>" method="get">
    <input name="s" type="text" size="16" value="<?php echo htmlentities($query,ENT_COMPAT,get_bloginfo('charset')); ?>" />
    <input type="submit" value="Search" />
</form>
<?php
}
function search_engine_content ($atts=false)
{
    $time = date('Y-m-d H:i:s');
    $css = 1;
    $site_id = $site_ids = $template_ids = $site = false;
    global $search_engine;
    include_once SEARCH_ENGINE_DIR.'/classes/API.class.php';
    $api = new Search_Engine_API();
    $default_site = get_option('search_engine_default_site');
    if(0<$default_site)
        $site = $api->get_site(array('id'=>$default_site));
    if($site===false)
    {
        $site = parse_url(get_bloginfo('wpurl'));
        $site = $api->get_site(array('host'=>$site['host'],'scheme'=>$site['scheme']));
    }
    if($site!==false)
    {
        $site_id = $site['id'];
        $site_ids = array($site_id);
    }
    if($atts!==false)
    {
        $atts = array_merge(array('sites'=>$site_id,'templates'=>false,'css'=>1),$atts);
        $site_ids = array_filter(explode(',',$atts['sites']));
        $template_ids = array_filter(explode(',',$atts['templates']));
        if(!empty($template_ids))
            $site_ids = false;
        else
            $template_ids = false;
        if(empty($site_ids))
            $site_ids = false;
        $css = $atts['css'];
        if($css!=1)
            $css = 0;
    }
    if(empty($site_ids)&&empty($template_ids))
        return;
    include_once SEARCH_ENGINE_DIR.'/classes/Search.class.php';
    $query = '';
    if(!wp_style_is('search-engine')&&isset($_GET['q']))
        $query = stripslashes($_GET['q']);
    elseif(isset($_GET['s']))
        $query = stripslashes($_GET['s']);
    timer_start();
    $search = new Search_Engine_Search($site_ids,$template_ids);
    if(isset($_GET['pg'])&&ctype_digit($_GET['pg'])&&0<$_GET['pg'])
        $search->page = $_GET['pg'];
    $search->results_per_page = 10;
    $results = $search->search_build_query($query);
    if($search->page==1)
    {
        $elapsed = timer_stop(0,0);
        $api = new Search_Engine_API();
        $params = array('query'=>$query,'time'=>$time,'elapsed'=>$elapsed,'results'=>$search->total_results);
        $api->log_query($params);
    }
    if(!wp_style_is('search-engine')&&!isset($search_engine['css_output'])&&$css==1&&!defined('SEARCH_ENGINE_CUSTOM_CSS'))
    {
        $search_engine['css_output'] =1;
?>
<link rel="stylesheet" type="text/css" href="<?php echo SEARCH_ENGINE_URL.'/assets/style.css'; ?>" />
<?php
    }
?>
<div id="search_engine_Area">
<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="get">
    <input name="<?php echo (!wp_style_is('search-engine')?'q':'s'); ?>" type="text" size="41" class="search_engine_Box" value="<?php echo htmlentities($query,ENT_COMPAT,get_bloginfo('charset')); ?>" />
    <input type="submit" value="Search" class="search_engine_Button" /><?php if(defined('SEARCH_ENGINE_ADVANCED_URL')){ ?><br /><br /><?php if(defined('SEARCH_ENGINE_ADVANCED_HTML')){ echo SEARCH_ENGINE_ADVANCED_HTML; }else{ ?>
    <a href="<?php echo SEARCH_ENGINE_ADVANCED_URL; ?>" class="search_engine_Advanced"><?php if(define('SEARCH_ENGINE_ADVANCED_TEXT')){ echo SEARCH_ENGINE_ADVANCED_TEXT; }else{ ?>Go to Advanced Search<?php } ?></a><?php }} ?>
</form>
<?php
    if(0<strlen($query))
    {
?>
<div class="search_engine_InfoBar">
<?php
        $total_pages = ceil($search->total_results / $search->results_per_page);
        $begin = ($search->results_per_page*$search->page)-($search->results_per_page-1);
        $end = ($total_pages==$search->page?$search->total_results:($search->results_per_page*$search->page));
        $page = $search->page;
        $rows_per_page = $search->results_per_page;
        $request_uri = $_SERVER['REQUEST_URI'];
        $explode = explode('?',$request_uri);
        $explode = @end($explode);
        parse_str($explode,$replace);
        if(isset($replace['pg']))
            unset($replace['pg']);
        if(isset($replace['submit']))
            unset($replace['submit']);
        $replace = http_build_query($replace);
        $request_uri = str_replace($explode,$replace,$request_uri).'&';
?>
	<p>Result<?php echo ($search->total_results==1&&!empty($results))?'':'s'; ?> <strong><?php if($search->total_results<1||empty($results)){ echo 0; } else { echo $begin; ?> - <?php echo $end; } ?></strong> of <strong><?php if($search->total_results<1||empty($results)){ echo 0; } else { echo $search->total_results; } ?></strong> for <strong><?php echo htmlentities($query,ENT_COMPAT,get_bloginfo('charset')); ?></strong></p>
</div>
<?php
        if(!empty($results))
        {
?>
<ul class="search_engine_results">
<?php
            foreach($results as $result)
            {
                if(empty($result->description))
                    $result->description = $result->fulltxt;
?>
    <li>
        <h3 class="search_engine_Title"><a href="<?php echo $result->url; ?>"><?php echo $search->search_do_excerpt($result->title,68,false); ?></a></h3>
        <div class="search_engine_Description"><?php echo $search->search_do_excerpt($result->description); ?></div>
        <cite class="search_engine_URL"><a href="<?php echo $result->url; ?>"><?php echo $search->search_do_excerpt($result->url); ?></a></cite>
    </li>
<?php
        }
?>
</ul>
<?php
        }
        else
        {
?>
    <p class="search_engine_normal">Your search - <strong><?php echo $query; ?></strong> - did not match any documents.</p>
    <p class="search_engine_normal">Suggestions:</p>
    <ul class="search_engine_real">
        <li>Make sure all words are spelled correctly.</li>
        <li>Try different keywords.</li>
        <li>Try more general keywords.</li>
    </ul>
<?php
        }
        if(1<$total_pages)
        {
?>
<div class="search_engine_Pagination">
<?php
            if (1 < $page)
            {
?>
            <a href="<?php echo $request_uri; ?>pg=<?php echo $page-1; ?>" class="prev page-numbers search_engine_PrevLink">Previous</a>
            <a href="<?php echo $request_uri; ?>pg=1" class="page-numbers">1</a>
<?php
            }
            if (1 < ($page - 100))
            {
?>
            <a href="<?php echo $request_uri; ?>pg=<?php echo ($page - 100); ?>" class="page-numbers"><?php echo ($page - 100); ?></a>
<?php
            }
            if (1 < ($page - 10))
            {
?>
            <a href="<?php echo $request_uri; ?>pg=<?php echo ($page - 10); ?>" class="page-numbers"><?php echo ($page - 10); ?></a>
<?php
            }
            for ($i = 2; $i > 0; $i--)
            {
                if (1 < ($page - $i))
                {
?>
            <a href="<?php echo $request_uri; ?>pg=<?php echo ($page - $i); ?>" class="page-numbers"><?php echo ($page - $i); ?></a>
<?php
               }
        }
?>
            <span class="page-numbers current"><?php echo $page; ?></span>
<?php
            for ($i = 1; $i < 3; $i++)
            {
                if ($total_pages > ($page + $i))
                {
?>
            <a href="<?php echo $request_uri; ?>pg=<?php echo ($page + $i); ?>" class="page-numbers"><?php echo ($page + $i); ?></a>
<?php
                }
            }
            if ($total_pages > ($page + 10))
            {
?>
            <a href="<?php echo $request_uri; ?>pg=<?php echo ($page + 10); ?>" class="page-numbers"><?php echo ($page + 10); ?></a>
<?php
            }
            if ($total_pages > ($page + 100))
            {
?>
            <a href="<?php echo $request_uri; ?>pg=<?php echo ($page + 100); ?>" class="page-numbers"><?php echo ($page + 100); ?></a>
<?php
            }
            if ($page < $total_pages)
            {
?>
            <a href="<?php echo $request_uri; ?>pg=<?php echo $total_pages; ?>" class="page-numbers"><?php echo $total_pages; ?></a>
            <a href="<?php echo $request_uri; ?>pg=<?php echo $page+1; ?>" class="next page-numbers search_engine_NextLink">Next</a>
<?php
            }
?>
</div>
<?php
        }
    }
?>
</div>
<?php
}