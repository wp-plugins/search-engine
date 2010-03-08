<?php
/*
Plugin Name: Search Engine
Plugin URI: http://www.scottkclark.com/wordpress/search-engine/
Description: THIS IS A BETA VERSION - Currently in development - A search engine for WordPress that indexes ALL of your site and provides comprehensive search.
Version: 0.4.5
Author: Scott Kingsley Clark
Author URI: http://www.scottkclark.com/

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
define('SEARCH_ENGINE_VERSION','045');
define('SEARCH_ENGINE_URL', WP_PLUGIN_URL . '/search-engine');
define('SEARCH_ENGINE_DIR', WP_PLUGIN_DIR . '/search-engine');

function search_engine_init ()
{
    global $current_user,$wpdb;
    $capabilities = search_engine_capabilities();

    // check version
    $version = get_option('search_engine_version');
    if(empty($version))
    {
        // thx pods ;)
        $sql = file_get_contents(SEARCH_ENGINE_DIR.'/assets/dump.sql');
        $sql_explode = preg_split("/;\n/", str_replace('wp_', $wpdb->prefix, $sql));
        if(count($sql_explode)==1)
            $sql_explode = preg_split("/;\r/", str_replace('wp_', $wpdb->prefix, $sql));
        for ($i = 0, $z = count($sql_explode); $i < $z; $i++)
        {
            $wpdb->query($sql_explode[$i]);
        }
        delete_option('search_engine_version');
        add_option('search_engine_version',SEARCH_ENGINE_VERSION);
    }
    elseif($version!=SEARCH_ENGINE_VERSION)
    {
        delete_option('search_engine_version');
        add_option('search_engine_version',SEARCH_ENGINE_VERSION);
    }

    // thx gravity forms, great way of integration with members!
    if ( function_exists( 'members_get_capabilities' ) ){
        add_filter('members_get_capabilities', 'search_engine_get_capabilities');

        if(current_user_can("search_engine_full_access"))
            $current_user->remove_cap("search_engine_full_access");

        $is_admin_with_no_permissions = current_user_can("administrator") && !search_engine_current_user_can_any(search_engine_capabilities());

        if($is_admin_with_no_permissions)
        {
            $role = get_role("administrator");
            foreach($capabilities as $cap)
            {
                $role->add_cap($cap);
            }
        }
    }
    else
    {
        $search_engine_full_access = current_user_can("administrator") ? "search_engine_full_access" : "";
        $search_engine_full_access = apply_filters("search_engine_full_access", $search_engine_full_access);

        if(!empty($search_engine_full_access))
            $current_user->add_cap($search_engine_full_access);
    }
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
    $has_full_access = current_user_can("search_engine_full_access");
    if(!$has_full_access&&current_user_can("administrator"))
        $has_full_access = true;

    $min_cap = search_engine_current_user_can_which(search_engine_capabilities());
    if(empty($min_cap))
        $min_cap = "search_engine_full_access";

    $templates = @count($wpdb->get_results('SELECT id FROM '.SEARCH_ENGINE_TBL.'templates LIMIT 1'));

    add_menu_page('Search Engine', 'Search Engine', $has_full_access ? "read" : $min_cap, 'search-engine', null, WP_PLUGIN_URL.'/search-engine/assets/icons/search_16.png');
    add_submenu_page('search-engine', 'Wizard', 'Wizard', $has_full_access ? "read" : "search_engine_index_templates", 'search-engine', 'search_engine_wizard');
    if(0<$templates)
        add_submenu_page('search-engine', 'Index Templates', 'Index Templates', $has_full_access ? "read" : "search_engine_index", 'search-engine-index', 'search_engine_index');
    /* COMING SOON! :-)
    add_submenu_page('search-engine', 'XML Sitemaps', 'XML Sitemaps', $has_full_access ? "read" : "search_engine_view_indexmaps", 'search-engine-xml-sitemaps', 'search_engine_xml_sitemaps');
    add_submenu_page('search-engine', 'Groups', 'Groups', $has_full_access ? "read" : "search_engine_groups", 'search-engine-groups', 'search_engine_groups');
    add_submenu_page('search-engine', 'View Index', 'View Index', $has_full_access ? "read" : "search_engine_view_index", 'search-engine-view-index', 'search_engine_view_index');
    add_submenu_page('search-engine', 'Search Settings', 'Search Settings', $has_full_access ? "read" : "search_engine_search_settings", 'search-engine-search-settings', 'search_engine_search_settings');
    add_submenu_page('search-engine', 'View  Search Logs', 'View Search Logs', $has_full_access ? "read" : "search_engine_logs", 'search-engine-logs', 'search_engine_logs');
    */
    add_submenu_page('search-engine', 'About', 'About', 'read', 'search-engine-about', 'search_engine_about');
}
function search_engine_wizard ()
{
    if(isset($_GET['action'])&&$_GET['action']=='run')
    {
        search_engine_wizard_run();
    }
    else
    {
?>
<div class="wrap">
    <div id="icon-edit-pages" class="icon32" style="background-position:0 0;background-image:url(<?php echo WP_CONTENT_URL; ?>/plugins/search-engine/assets/icons/search_32.png);"><br /></div>
    <h2>Search Engine - Easy Indexing Wizard</h2>
    <div style="height:20px;"></div>
<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>&action=run">
    <table class="form-table">
<?php
    global $wpdb;
    $data = $wpdb->get_results('SELECT id,host,scheme FROM '.SEARCH_ENGINE_TBL.'sites',ARRAY_A);
    $existing = array();
    $selected = false;
    if(!empty($data)) foreach($data as $row)
    {
        $existing[$row['id']] = $row['scheme'].'://'.$row['host'];
        if($existing[$row['id']]==get_bloginfo('wpurl')&&!isset($_GET['site_id']))
            $selected = $row['id'];
        elseif(isset($_GET['site_id'])&&$row['id']==$_GET['site_id'])
            $selected = $row['id'];
    }
    if($selected===false)
    {
?>
        <tr valign="top">
            <th scope="row"><label for="site_url">Index a New Site</label></th>
            <td>
                <input name="site_url" type="text" id="site_url" value="<?php bloginfo('wpurl'); ?>" class="regular-text"<?php if(!empty($existing)) { ?> onchange="jQuery('#existing_site').val(0);" onblur="jQuery('#existing_site').val((jQuery('#site_url').val()!=''?0:jQuery('#existing_site').val()));"<?php } ?> />
                <span class="description">(Ex. http://www.yoursite.com or www.yoursite.com)</span>
            </td>
        </tr>
<?php
        if(!empty($existing))
        {
?>
        <tr valign="top">
            <th scope="row"><label for="existing_site">or Index an Existing Site</label></th>
            <td>
                <select name="existing_site" id="existing_site" onchange="jQuery('#site_url').val((jQuery('#existing_site').val()==0?jQuery('#site_url').val():''));">
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
?>
        <tr valign="top">
            <th scope="row"><label for="existing_site">Index an Existing Site</label></th>
            <td>
                <select name="existing_site" id="existing_site" onchange="jQuery('#site_url').val((jQuery('#existing_site').val()==0?jQuery('#site_url').val():''));">
                    <option value="0">Index a New Site</option>
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
                <input name="site_url" type="text" id="site_url" value="" class="regular-text" onchange="jQuery('#existing_site').val(0);" onblur="jQuery('#existing_site').val((jQuery('#site_url').val()!=''?0:jQuery('#existing_site').val()));" />
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
                    <span class="description">This is the max number of  levels the search engine will crawl into your site. Enter 0 for no limit. This is not the same as the URL levels (www.mysite.com/level-1/level-2/level-3/). Levels are when the Spider crawls</span>
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
    if(!empty($_POST))
    {
        if(!empty($_POST['site_url']))
        {
            $api = new Search_Engine_API();
            $parsed = parse_url($_POST['site_url']);
            if(!isset($parsed['host']))
            {
                $parsed['scheme'] = 'http';
                $parsed['host'] = $_POST['site_url'];
            }
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
        else
        {
            $site_id = $_POST['existing_site'];
        }
        $_POST['site'] = $site_id;
        require_once SEARCH_ENGINE_DIR.'/classes/Admin.class.php';
        $columns = array('site'=>array('custom_relate'=>array('table'=>SEARCH_ENGINE_TBL.'sites','what'=>'host')),'indexed'=>array('label'=>'Date Indexed','type'=>'date'),'updated'=>array('label'=>'Last Modified','type'=>'date'),'id'=>array('label'=>'Template ID'));
        $form_columns = $columns;
        unset($form_columns['id']);
        unset($form_columns['indexed']);
        $form_columns['updated']['update'] = true;
        $form_columns['updated']['display'] = false;
        //$form_columns[] = 'group';
        $form_columns[] = 'directory';
        $form_columns[] = 'max_depth';
        $form_columns[] = 'blacklist_words';
        $form_columns[] = 'blacklist_uri_words';
        $form_columns[] = 'whitelist_uri_words';
        $form_columns[] = 'htaccess_username';
        $form_columns[] = 'htaccess_password';
        $admin = new Search_Engine_Admin(array('do'=>'create','item'=>'Index Template','items'=>'Index Templates','table'=>SEARCH_ENGINE_TBL.'templates','columns'=>$columns,'form_columns'=>$form_columns,'icon'=>WP_PLUGIN_URL.'/search-engine/assets/icons/search_32.png','custom'=>array('form'=>'search_engine_index_form','action_end_view'=>'search_engine_index_run','header'=>'search_engine_index_header')));
        $admin->go();
        $template_id = $admin->insert_id;
    }
    global $wpdb;
    if(isset($_GET['site_id']))
        $site_id = $_GET['site_id'];
    if(isset($_GET['template_id']))
        $template_id = $_GET['template_id'];
?>
<div class="wrap">
    <div id="icon-edit-pages" class="icon32" style="background-position:0 0;background-image:url(<?php echo WP_CONTENT_URL; ?>/plugins/search-engine/assets/icons/search_32.png);"><br /></div>
    <h2>Search Engine - Easy Indexing Wizard</h2>
    <div style="height:20px;"></div>
    <p>Please wait while your site is spidered and indexed.</p>
    <p class="submit">
        <input type="button" name="Advanced" class="button-secondary" value="Show Detailed Progress" onclick="jQuery('#search_engine_advanced').toggle();this.value=(this.value=='Show Detailed Progress'?'Hide Detailed Progress':'Show Detailed Progress');jQuery('#scroller').stop().scrollTo('100%','0%', { axis:'y' });" />
    </p>
    <link  type="text/css" rel="stylesheet" href="<?php echo WP_CONTENT_URL; ?>/plugins/search-engine/assets/admin.css" />
    <script type="text/javascript" src="<?php echo WP_CONTENT_URL; ?>/plugins/search-engine/assets/admin.js"></script>
    <script type="text/javascript" src="<?php echo WP_CONTENT_URL; ?>/plugins/search-engine/assets/jquery.scrollto.js"></script>
    <script type="text/javascript">
        jQuery(function()
        {
            tothetop();
        });
    </script>
    <div class="search_engine_wizard">
        <div class="loader"><img src="<?php echo WP_CONTENT_URL; ?>/plugins/search-engine/assets/images/ajax-loader.gif" alt="AJAX Loader" /></div>
        <div id="search_engine_advanced">
            <iframe src ="<?php echo WP_CONTENT_URL; ?>/plugins/search-engine/cronjob.php?site_id=<?php echo $site_id; ?>&template_id=<?php echo $template_id; ?>&token=<?php echo $token; ?>" width="100%" height="500px" id="scroller" onload="iframedone('#scroller');">
                <p>Your browser does not support iframes.</p>
            </iframe>
            <p id="startstop"><span class="startstop stop">[<a href="#" onclick="clearTimeout(t);jQuery('.startstop').toggle();return false;">pause autoscrolling</a>]</span><span class="startstop">[<a href="#" onclick="t = setTimeout('tothetop()',1000);jQuery('.startstop').toggle();return false;">resume autoscrolling</a>]</span></p>
            <div class="loader"></div>
        </div>
    </div>
</div>
<?php
}
function search_engine_index ()
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
            {
                $site_id = $api->save_site(array('host'=>$parsed['host'],'scheme'=>$parsed['scheme'],'group'=>0));
            }
            else
            {
                $site_id = $site['id'];
            }
        }
        else
        {
            $site_id = $_POST['existing_site'];
        }
        $_POST['site'] = $site_id;
    }
    require_once SEARCH_ENGINE_DIR.'/classes/Admin.class.php';
    $columns = array('site'=>array('custom_relate'=>array('table'=>SEARCH_ENGINE_TBL.'sites','what'=>'host','is'=>'site')),'indexed'=>array('label'=>'Date Indexed','type'=>'date'),'updated'=>array('label'=>'Last Modified','type'=>'date'),'id'=>array('label'=>'Template ID'));
    $form_columns = $columns;
    unset($form_columns['id']);
    unset($form_columns['indexed']);
    $form_columns['updated']['update'] = true;
    $form_columns['updated']['display'] = false;
    //$form_columns[] = 'group';
    $form_columns[] = 'directory';
    $form_columns[] = 'max_depth';
    $form_columns[] = 'blacklist_words';
    $form_columns[] = 'blacklist_uri_words';
    $form_columns[] = 'whitelist_uri_words';
    $form_columns[] = 'htaccess_username';
    $form_columns[] = 'htaccess_password';
    $admin = new Search_Engine_Admin(array('item'=>'Index Template','items'=>'Index Templates','table'=>SEARCH_ENGINE_TBL.'templates','columns'=>$columns,'form_columns'=>$form_columns,'icon'=>WP_PLUGIN_URL.'/search-engine/assets/icons/search_32.png','custom'=>array('form'=>'search_engine_index_form','action_end_view'=>'search_engine_index_run','header'=>'search_engine_index_header')));
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
        $obj->row = array('max_depth'=>'','blacklist_words'=>'','blacklist_uri_words'=>'','whitelist_uri_words'=>'','htaccess_username'=>'','htaccess_password'=>'');
    }
 ?>
    <div style="height:20px;"></div>
<form method="post" action="<?php echo $obj->var_update(array('action'=>$obj->action,'do'=>'save','id'=>$id)); ?>">
    <table class="form-table">
<?php
    global $wpdb;
    $data = $wpdb->get_results('SELECT id,host,scheme FROM '.SEARCH_ENGINE_TBL.'sites',ARRAY_A);
    $existing = array();
    $selected = false;
    if(!empty($data)) foreach($data as $row)
    {
        $existing[$row['id']] = $row['scheme'].'://'.$row['host'];
        if($existing[$row['id']]==get_bloginfo('wpurl')&&$create==1)
            $selected = $row['id'];
        elseif($row['id']==$obj->row['site']&&$create==0)
            $selected = $row['id'];
    }
    if($selected===false)
    {
?>
        <tr valign="top">
            <th scope="row"><label for="site_url">Index a New Site</label></th>
            <td>
                <input name="site_url" type="text" id="site_url" value="<?php bloginfo('wpurl'); ?>" class="regular-text"<?php if(!empty($existing)) { ?> onchange="jQuery('#existing_site').val(0);" onblur="jQuery('#existing_site').val((jQuery('#site_url').val()!=''?0:jQuery('#existing_site').val()));"<?php } ?> />
                <span class="description">(Ex. http://www.yoursite.com or www.yoursite.com)</span>
            </td>
        </tr>
<?php
        if(!empty($existing))
        {
?>
        <tr valign="top">
            <th scope="row"><label for="existing_site">or Index an Existing Site</label></th>
            <td>
                <select name="existing_site" id="existing_site" onchange="jQuery('#site_url').val((jQuery('#existing_site').val()==0?jQuery('#site_url').val():''));">
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
?>
        <tr valign="top">
            <th scope="row"><label for="existing_site">Index an Existing Site</label></th>
            <td>
                <select name="existing_site" id="existing_site" onchange="jQuery('#site_url').val((jQuery('#existing_site').val()==0?jQuery('#site_url').val():''));">
                    <option value="0">Index a New Site</option>
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
                <input name="site_url" type="text" id="site_url" value="" class="regular-text" onchange="jQuery('#existing_site').val(0);" onblur="jQuery('#existing_site').val((jQuery('#site_url').val()!=''?0:jQuery('#existing_site').val()));" />
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
function search_engine_groups ()
{
    require_once SEARCH_ENGINE_DIR.'/classes/Admin.class.php';
    $columns = array('name','created'=>array('label'=>'Date Created','type'=>'date'),'updated'=>array('label'=>'Last Modified','type'=>'date'));
    $form_columns = $columns;
    $form_columns['created']['updated'] = false;
    $form_columns['created']['display'] = false;
    $form_columns['updated']['update'] = true;
    $form_columns['updated']['display'] = false;
    $admin = new Search_Engine_Admin(array('item'=>'Group','items'=>'Groups','table'=>SEARCH_ENGINE_TBL.'groups','columns'=>$columns,'form_columns'=>$form_columns,'icon'=>WP_PLUGIN_URL.'/search-engine/assets/icons/search_32.png'));
    $admin->go();
}
function search_engine_groups_form ($obj)
{

}
function search_engine_view_index ()
{
    require_once SEARCH_ENGINE_DIR.'/classes/Admin.class.php';
    $columns = array('host'=>array('label'=>'Site URL','custom_display'=>'search_engine_view_index_display'),'indexed'=>array('label'=>'Date Indexed','type'=>'date'),'updated'=>array('label'=>'Last Modified','type'=>'date'));
    $form_columns = $columns;
    unset($form_columns['indexed']);
    $form_columns[] = 'scheme';
    $form_columns['updated']['update'] = true;
    $form_columns['updated']['display'] = false;
    $admin = new Search_Engine_Admin(array('item'=>'Index','items'=>'Index','table'=>SEARCH_ENGINE_TBL.'sites','columns'=>$columns,'form_columns'=>$form_columns,'icon'=>WP_PLUGIN_URL.'/search-engine/assets/icons/search_32.png','custom'=>array('action_end_view'=>'search_engine_view_index_run','view'=>'search_engine_view_index_details'),'edit'=>false,'add'=>false,'view'=>true));
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
    require_once SEARCH_ENGINE_DIR.'/classes/Admin.class.php';
    $admin = new Search_Engine_Admin(array('item'=>'Search Log','items'=>'Search Logs','table'=>SEARCH_ENGINE_TBL.'log','columns'=>array('query','time'=>array('type'=>'date','label'=>'Date of Search'),'elapsed'=>array('label'=>'Processing Time'),'results'=>array('label'=>'Total Results Found')),'add'=>false,'edit'=>false,'delete'=>false,'icon'=>WP_PLUGIN_URL.'/search-engine/assets/icons/search_32.png'));
    $admin->go();
}
function search_engine_search_settings ()
{
    global $wpdb;
    require_once SEARCH_ENGINE_DIR.'/classes/Admin.class.php';
    $columns = array('name','searched'=>array('label'=>'Last Searched','type'=>'date'),'updated'=>array('label'=>'Last Modified','type'=>'date'));
    $form_columns = $columns;
    unset($form_columns['searched']);
    $form_columns['sites'] = array('type'=>'desc');
    $form_columns['query_filters'] = array('type'=>'desc');
    $form_columns['uri_filters'] = array('type'=>'desc');
    $form_columns['updated']['update'] = true;
    $form_columns['updated']['display'] = false;
    $wpdb->query('SELECT SQL_CALC_FOUND_ROWS id FROM '.SEARCH_ENGINE_TBL.'search LIMIT 1');
    $count = @current($wpdb->get_results("SELECT FOUND_ROWS()"));
    $found = 'FOUND_ROWS()';
    $count = $count->$found;
    $delete = true;
    if($count<2||(isset($_GET['action'])&&$_GET['delete']&&$count<3))
    {
        $delete = false;
    }
    $admin = new Search_Engine_Admin(array('item'=>'Search Setting','items'=>'Search Settings','table'=>SEARCH_ENGINE_TBL.'search','columns'=>$columns,'form_columns'=>$form_columns,'delete'=>$delete,'icon'=>WP_PLUGIN_URL.'/search-engine/assets/icons/search_32.png'));
    $admin->go();
}
function search_engine_about ()
{
?>
<div class="wrap">
    <div id="icon-edit-pages" class="icon32" style="background-position:0 0;background-image:url(<?php echo WP_CONTENT_URL; ?>/plugins/search-engine/assets/icons/search_32.png);"><br /></div>
    <h2>About the Search Engine plugin</h2>
    <div style="height:20px;"></div>
    <link  type="text/css" rel="stylesheet" href="<?php echo WP_CONTENT_URL; ?>/plugins/search-engine/assets/admin.css" />
    <table class="form-table about">
        <tr valign="top">
            <th scope="row">Plugin Author</th>
            <td><a href="http://www.scottkclark.com/">Scott Kingsley Clark</a></td>
        </tr>
        <tr valign="top">
            <th scope="row">Official Support</th>
            <td><a href="http://www.scottkclark.com/forums/search-engine/">Search Engine - Support Forums</a></td>
        </tr>
        <tr valign="top">
            <th scope="row">Plugin Contributors</th>
            <td>
                <ul>
                    <li><a href="http://studioslice.com/">Greg Dean</a> - PHP Development for Search.class.php</li>
                    <li><a href="http://gaarai.com/">Chris Jean</a> from <a href="http://ithemes.com/">iThemes</a> - PHP Development for Displaying Results on Search Pages</li>
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
                            <li>cronjob.php - Script to use when setting up Server Cronjob settings - Can Spider based on Site or Template</li>
                        </ul>
                    </li
                    <li><strong>Index</strong>
                        <ul>
                            <li>Keyword Extraction - Get keywords from pages from &lt;title&gt;,  &lt;meta name="description"&gt;, &lt;meta name="keywords"&gt;, and &lt;body&gt; tags</li>
                            <li>Keyword Blacklist - Exclude Keywords from being Indexed</li>
                            <li>Index Weight Assignment - Based on how often keywords are used, and in which places they are found, a weight is given to a URL to create relevancy</li>
                        </ul>
                    </li>
                    <li><strong>Search</strong>
                        <ul>
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
                    <dt>0.5.0</dt>
                    <dd>
                        <ul>
                            <li>Search Settings - Setup / control multiple searches on your site</li>
                            <li>AND / OR / "Exact Phrase" multi-combination Support</li>
                            <li>Negative Keyword Matching using -word Format</li>
                            <li>View Search Logs - View queries recently typed, how long it took them to process, and how many results were returned</li>
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
add_action('admin_init','search_engine_init');
add_action('admin_menu','search_engine_menu');

global $search_engine;
add_filter( 'the_posts', 'search_engine_get_posts' );
add_shortcode('search_engine','search_engine_content');

function search_engine_get_posts ( $posts ) {
    // Don't do anything for views that aren't search results
    if ( ! is_search() )
        return $posts;

    // Since we know that this request is for search results, setup loop replacement hooks
    add_action( 'loop_start', 'search_engine_loop_start' );
    add_action( 'loop_end', 'search_engine_loop_end' );

    // Send out the CSS team to clean up everything and make it oh so pretty
    if(!defined('SEARCH_ENGINE_CUSTOM_CSS'))
        wp_enqueue_style('search-engine',WP_PLUGIN_URL.'/search-engine/assets/style.css');

    // We let the user optionally choose a different search template to use
    add_filter('template_redirect','search_engine_template');

    // Prevent have_posts from returning false if there aren't any posts
    // This can easily be used to set the number of actual search results
    $posts = get_posts( array( 'numberposts' => 1, 'post_type' => 'any' ) );

    // If a Search page is found, use it instead (for the added benefit of using a custom template)
    global $wpdb;
    $search = $wpdb->get_results( "SELECT * from {$wpdb->prefix}posts WHERE post_type = 'page' and post_name = 'search'" );
    if( !empty( $search ))
        $posts = array( $search );

    // Create a virtual page if NO posts are found
    if( empty( $posts ) ) {
        $object = new stdClass();
        $object->post_title = __( "Search" );
        $object->post_name = "search";
        $object->post_content = "Searching all your nets!";
        $object->post_type = "page";
        $posts = array( $object );
    }

    // This is a filter, so return the posts
    return $posts;
}
function search_engine_loop_start ()
{
    ob_start();
}
function search_engine_loop_end ()
{
    ob_end_clean();
    search_engine_content();
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
    <input name="s" type="text" size="16" value="<?php echo htmlentities($query); ?>" />
    <input name="submit" type="submit" value="Search" />
</form>
<?php
}
function search_engine_content ($atts=false)
{
    global $search_engine;
    include_once SEARCH_ENGINE_DIR.'/classes/API.class.php';
    $api = new Search_Engine_API();
    $site = parse_url(get_bloginfo('wpurl'));
    $site = $api->get_site(array('host'=>$site['host'],'scheme'=>$site['scheme']));
    if($site===false)
        return;
    $site_id = $site['id'];
    $site_ids = array($site_id);
    $css = 1;
    if($atts!==false)
    {
        $atts = shortcode_atts(array('sites'=>$site_id,'css'=>1),$atts);
        $site_ids = explode(',',$atts['sites']);
        $css = $atts['css'];
        if($css!=1)
            $css = 0;
    }
    include_once SEARCH_ENGINE_DIR.'/classes/Search.class.php';
    $query = '';
    if(!wp_style_is('search-engine')&&isset($_GET['q']))
        $query = stripslashes($_GET['q']);
    elseif(isset($_GET['s']))
        $query = stripslashes($_GET['s']);
    $search = new Search_Engine_Search($site_ids);
    if(isset($_GET['pg'])&&ctype_digit($_GET['pg'])&&0<$_GET['pg'])
    {
        $search->page = $_GET['pg'];
    }
    $search->results_per_page = 10;
    $results = $search->search_build_query($query);
    if(!wp_style_is('search-engine')&&!isset($search_engine['css_output'])&&$css==1&&!defined('SEARCH_ENGINE_CUSTOM_CSS'))
    {
        $search_engine['css_output'] =1;
?>
<link rel="stylesheet" type="text/css" href="<?php echo WP_PLUGIN_URL.'/search-engine/assets/style.css'; ?>" />
<?php
    }
?>
<div id="search_engine_Area">
<?php
    if(wp_style_is('search-engine'))
    {
?>
    <h1>Search</h1>
<?php
    }
?>
<form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="get">
    <input name="<?php echo (!wp_style_is('search-engine')?'q':'s'); ?>" type="text" size="41" class="search_engine_Box" value="<?php echo htmlentities($query); ?>" />
    <input name="submit" type="submit" value="Search" class="search_engine_Button" />
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
	<p>Results <strong><?php if($search->total_results<1){ echo 0; } else { echo $begin; ?> - <?php echo $end; } ?></strong> of <strong><?php echo $search->total_results; ?></strong> for <strong><?php echo htmlentities($query); ?></strong></p>
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
            $text = $result->description;
            $text = apply_filters('the_content', $text);
            $text = str_replace(']]>', ']]&gt;', $text);
            $text = strip_tags($text);
            $excerpt_length = apply_filters('excerpt_length', 55);
            $excerpt_more = apply_filters('excerpt_more', ' ' . '...');
            $words = explode(' ', $text, $excerpt_length + 1);
            if (count($words) > $excerpt_length) {
                array_pop($words);
                $text = implode(' ', $words);
                $text = $text . $excerpt_more;
            }
            $result->description = $text;
?>
    <li>
        <h3 class="search_engine_Title"><a href="<?php echo $result->url; ?>"><?php echo $result->title; ?></a></h3>
        <div class="search_engine_Description"><?php echo $result->description; ?></div>
        <cite class="search_engine_URL"><?php echo $result->url; ?></cite>
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