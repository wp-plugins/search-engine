<?php
global $wpdb;
if(!is_object($wpdb))
{
    ob_start();
    require_once(realpath('../../../wp-load.php'));
    ob_end_clean();
}

class Search_Engine_API
{
    var $tables = array('index','keywords','links','log','sites','groups','templates','queue');

    function __construct ()
    {
        global $wpdb;
        foreach($this->tables as $key=>$table)
        {
            $the_table = 'table_'.$table;
            $this->$the_table = $wpdb->prefix.'searchengine_'.$table;
        }
    }
    function message ($msg)
    {
        echo date('m/d/Y h:i:sa').' - '.$msg."<br />\r\n";
    }
    function sanitize ($var)
    {
        return $this->wpdb->_real_escape($var);
    }
    function validate ($params,$required)
    {
        foreach($required as $index)
        {
            if(!isset($params[$index]))
            {
                $this->message("<strong>The '$index' option is required for this function</strong>");
                return false;
            }
        }
        return true;
    }
    function get_page ($url,$params)
    {
        if(false===$this->validate($params,array('url','site')))
            return false;
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $this->table_links WHERE `url`=%s AND `site`=%d",array($params['url'],$params['site'])),ARRAY_A);
        if(!empty($row))
            return $row;
        else
            return false;
    }
    function md5_page ($params)
    {
        if(false===$this->validate($params,array('url','site','md5_checksum')))
            return false;
        if(empty($params['md5_checksum']))
            return false;
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $this->table_links WHERE `url`!=%s AND `site`=%d AND `md5_checksum`=%s",array($params['url'],$params['site'],$params['md5_checksum'])),ARRAY_A);
        if(!empty($row))
            return $row;
        else
            return false;
    }
    function index_page ($params)
    {
        if(false===$this->validate($params,array('url','site','title','description','fulltxt','lastmod','size','md5_checksum','level','keywords')))
        {
            return false;
        }
        global $wpdb;
        $link_id = @current($wpdb->get_col($wpdb->prepare("SELECT `id` FROM $this->table_links WHERE `url`=%s AND `site`=%d",array($params['url'], $params['site']))));
        if(is_numeric($link_id))
        {
            $wpdb->query($wpdb->prepare("UPDATE $this->table_links SET `title`=%s,`description`=%s,`fulltxt`=%s,`lastmod`=%s,`updated`=FROM_UNIXTIME(UNIX_TIMESTAMP()),`size`=%d,`md5_checksum`=%s,`level`=%d WHERE id=$link_id",
                    array($params['title'], $params['description'], $params['fulltxt'], $params['lastmod'], $params['size'], $params['md5_checksum'], $params['level'])));
            $wpdb->query($wpdb->prepare("DELETE FROM $this->table_index WHERE `link`=%d AND `keyword`=%d AND `site`=%d",array($link_id, $keyword_id, $params['site'])));
            foreach($params['keywords'] as $keyword_id=>$weight)
            {
                $wpdb->query($wpdb->prepare("INSERT INTO $this->table_index (`link`,`keyword`,`weight`,`site`) VALUES ( %d, %d, %d, %d )",array($link_id, $keyword_id, $weight, $params['site'])));
            }
            return true;
        }
        else
        {
            $wpdb->query($wpdb->prepare("INSERT INTO $this->table_links (`site`,`url`,`title`,`description`,`fulltxt`,`lastmod`, `indexed`,`updated`,`size`,`md5_checksum`,`level`) VALUES ( %d, %s, %s, %s, %s, %s, FROM_UNIXTIME(UNIX_TIMESTAMP()), FROM_UNIXTIME(UNIX_TIMESTAMP()), %d, %s, %d )",
                    array($params['site'], $params['url'], $params['title'], $params['description'], $params['fulltxt'], $params['lastmod'], $params['size'], $params['md5_checksum'], $params['level'])));
            $link_id = $wpdb->insert_id;
            foreach($params['keywords'] as $keyword_id=>$weight)
            {
                $index_id = @current($wpdb->get_col($wpdb->prepare("SELECT `id` FROM $this->table_index WHERE `link`=%d AND `keyword`=%d AND `site`=%d",array($link_id, $keyword_id, $params['site']))));
                if(is_numeric($index_id))
                {
                    $wpdb->query($wpdb->prepare("UPDATE $this->table_index SET `weight`=%d WHERE id=$index_id",
                            array($weight)));
                }
                else
                {
                    $wpdb->query($wpdb->prepare("INSERT INTO $this->table_index (`link`,`keyword`,`weight`,`site`) VALUES ( %d, %d, %d, %d )",
                            array($link_id, $keyword_id, $weight, $params['site'])));
                }
            }
            return $link_id;
        }
    }
    function delete_page ($params)
    {
        if(false===$this->validate($params,array('url','site')))
        {
            return false;
        }
        global $wpdb;
        $link_id = @current($wpdb->get_col($wpdb->prepare("SELECT `id` FROM $this->table_links WHERE `url`=%s AND `site`=%d",array($params['url'], $params['site']))));
        if(is_numeric($link_id))
        {
            $wpdb->query("DELETE FROM $this->table_links WHERE id=$link_id");
            $wpdb->query("DELETE FROM $this->table_index WHERE link=$link_id");
            return true;
        }
        return false;
    }
    function get_queue ($params)
    {
        if(false===$this->validate($params,array('site','template')))
        {
            return false;
        }
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $this->table_queue WHERE `site`=%d AND `template`=%d ORDER BY id DESC",array($params['site'],$params['template'])),ARRAY_A);
        if(!empty($row))
        {
            return $row;
        }
        else
            return false;
    }
    function update_queue ($params)
    {
        if(false===$this->validate($params,array('site','template','queue')))
        {
            return false;
        }
        global $wpdb;
        $queue = @current($wpdb->get_results($wpdb->prepare("SELECT `id`,`shutdown` FROM $this->table_queue WHERE `site`=%d AND `template`=%d ORDER BY id DESC",array($params['site'], $params['template']))));
        if(false!==$queue)
        {
            if($queue->shutdown==1)
                die("<br />\n<br />\n".date('m/d/Y h:i:sa').' - <strong>Shutdown initiated.</strong>');
            $wpdb->query($wpdb->prepare("UPDATE $this->table_queue SET `queue`=%s,`updated`=FROM_UNIXTIME(UNIX_TIMESTAMP()) WHERE `id`={$queue->id}",array($params['queue'])));
            return true;
        }
        else
        {
            $wpdb->query($wpdb->prepare("INSERT INTO $this->table_queue (`site`,`template`,`added`,`updated`,`queue`) VALUES ( %d, %d, FROM_UNIXTIME(UNIX_TIMESTAMP()), FROM_UNIXTIME(UNIX_TIMESTAMP()), %s )",
                    array($params['site'], $params['template'], $params['queue'])));
            $link_id = $wpdb->insert_id;
            return $link_id;
        }
    }
    function delete_queue ($params)
    {
        if(false===$this->validate($params,array('site','template')))
        {
            return false;
        }
        global $wpdb;
        $wpdb->query($wpdb->prepare("DELETE FROM $this->table_queue WHERE `site`=%d AND `template`=%d",array($params['site'], $params['template'])));
        return true;
    }
    function get_template ($params)
    {
        if(false===$this->validate($params,array('id')))
        {
            return false;
        }
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $this->table_templates WHERE `id`=%d",array($params['id'])),ARRAY_A);
        if(!empty($row))
            return $row;
        else
            return false;
    }
    function save_template ($params)
    {
        // NOT FUNCTIONAL
        if(false===$this->validate($params,array('host','scheme')))
        {
            return false;
        }
        global $wpdb;
        if(is_numeric($params['id']))
        {
            $wpdb->query($wpdb->prepare("UPDATE $this->table_templates SET `group`=%d,`protocol`=%s,`updated`=FROM_UNIXTIME(UNIX_TIMESTAMP()) WHERE id=$site_id",
                    array($params['group'], $params['protocol'])));
            return true;
        }
        else
        {
            $wpdb->query($wpdb->prepare("INSERT INTO $this->table_templates (`group`,`site`,`scheme`,`directory`,`indexed`,`updated`) VALUES ( %d, %s, %s, %s, FROM_UNIXTIME(UNIX_TIMESTAMP()), FROM_UNIXTIME(UNIX_TIMESTAMP()) )",
                    array($params['group'], $params['host'], $params['scheme'], $params['directory'])));
            return $wpdb->insert_id;
        }
    }
    function bump_template ($params)
    {
        if(false===$this->validate($params,array('id')))
        {
            return false;
        }
        global $wpdb;
        $check = $wpdb->query($wpdb->prepare("UPDATE $this->table_templates SET `indexed`=FROM_UNIXTIME(UNIX_TIMESTAMP()) WHERE id=%d",array($params['id'])));
        return $check;
    }
    function delete_template ($params)
    {
        if(false===$this->validate($params,array('id')))
        {
            return false;
        }
        global $wpdb;
        if(is_numeric($params['id']))
        {
            $wpdb->query("DELETE FROM $this->table_templates WHERE id=".$params['id']);
            return true;
        }
        return false;
    }
    function get_site ($params)
    {
        if(!isset($params['id']))
        {
            if(false===$this->validate($params,array('host','scheme')))
            {
                return false;
            }
        }
        global $wpdb;
        if(isset($params['id']))
        {
            $sql = "`id`=%d";
            $arr = array($params['id']);
        }
        else
        {
            $sql = "`host`=%s AND `scheme`=%s";
            $arr = array($params['host'],$params['scheme']);
        }
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $this->table_sites WHERE $sql",$arr),ARRAY_A);
        if(!empty($row))
            return $row;
        else
            return false;
    }
    function save_site ($params)
    {
        if(false===$this->validate($params,array('host','scheme')))
        {
            return false;
        }
        global $wpdb;
        $site_id = @current($wpdb->get_col($wpdb->prepare("SELECT `id` FROM $this->table_sites WHERE `host`=%s AND ",array($params['host'],$params['scheme']))));
        if(is_numeric($site_id))
        {
            $wpdb->query($wpdb->prepare("UPDATE $this->table_sites SET `group`=%d,`protocol`=%s,`updated`=FROM_UNIXTIME(UNIX_TIMESTAMP()) WHERE id=$site_id",
                    array($params['group'], $params['protocol'])));
            return true;
        }
        else
        {
            $wpdb->query($wpdb->prepare("INSERT INTO $this->table_sites (`group`,`host`,`scheme`,`updated`) VALUES ( %d, %s, %s, FROM_UNIXTIME(UNIX_TIMESTAMP()) )",
                    array($params['group'], $params['host'], $params['scheme'])));
            return $wpdb->insert_id;
        }
    }
    function bump_site ($params)
    {
        if(false===$this->validate($params,array('id')))
        {
            return false;
        }
        global $wpdb;
        $check = $wpdb->query($wpdb->prepare("UPDATE $this->table_sites SET `indexed`=FROM_UNIXTIME(UNIX_TIMESTAMP()) WHERE id=%d",array($params['id'])));
        return $check;
    }
    function delete_site ($params)
    {
        if(false===$this->validate($params,array('host')))
        {
            return false;
        }
        global $wpdb;
        $site_id = @current($wpdb->get_col($wpdb->prepare("SELECT `id` FROM $this->table_sites WHERE `host`=%s",array($params['host']))));
        if(is_numeric($site_id))
        {
            $wpdb->query("DELETE FROM $this->table_sites WHERE id=$site_id");
            return true;
        }
        return false;
    }
    function get_group ($params)
    {
        if(false===$this->validate($params,array('id')))
        {
            return false;
        }
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $this->table_groups WHERE `id`=%d",array($params['id'])),ARRAY_A);
        if(!empty($row))
            return $row;
        else
            return false;
    }
    function save_group ($params)
    {
        if(false===$this->validate($params,array('name')))
        {
            return false;
        }
        global $wpdb;
        if(isset($params['id']))
        {
            $group_id = @current($wpdb->get_col($wpdb->prepare("SELECT `id` FROM $this->table_groups WHERE `id`=%d",array($params['id']))));
            if(is_numeric($group_id))
            {
                $wpdb->query($wpdb->prepare("UPDATE $this->table_groups SET `name`=%s WHERE id=$group_id",
                        array($params['name'])));
                return true;
            }
            else
                return false;
        }
        else
        {
            $wpdb->query($wpdb->prepare("INSERT INTO $this->table_groups (`name`) VALUES ( %s )",
                    array($params['name'])));
            return $wpdb->insert_id;
        }
    }
    function delete_group ($params)
    {
        if(false===$this->validate($params,array('host')))
        {
            return false;
        }
        global $wpdb;
        $site_id = @current($wpdb->get_col($wpdb->prepare("SELECT `id` FROM $this->table_sites WHERE `host`=%s",array($params['host']))));
        if(is_numeric($site_id))
        {
            $wpdb->query("DELETE FROM $this->table_sites WHERE id=$site_id");
            return true;
        }
        return false;
    }
    function get_keyword ($params)
    {
        if(false===$this->validate($params,array('name')))
        {
            return false;
        }
        $params['name'] = strtolower($params['name']);
        global $wpdb;
        $keyword_id = @current($wpdb->get_col($wpdb->prepare("SELECT `id` FROM $this->table_keywords WHERE `name`=%s",array($params['name']))));
        if(is_numeric($keyword_id))
            return $keyword_id;
        else
            return $this->save_keyword($params);
    }
    function save_keyword ($params)
    {
        if(false===$this->validate($params,array('name')))
        {
            return false;
        }
        global $wpdb;
        if(isset($params['id']))
        {
            $group_id = @current($wpdb->get_col($wpdb->prepare("SELECT `id` FROM $this->table_keywords WHERE `id`=%d",array($params['id']))));
            if(is_numeric($group_id))
            {
                $wpdb->query($wpdb->prepare("UPDATE $this->table_keywords SET `name`=%s WHERE id=$group_id",
                        array($params['name'])));
                return true;
            }
            else
                return false;
        }
        else
        {
            $wpdb->query($wpdb->prepare("INSERT INTO $this->table_keywords (`name`) VALUES ( %s )",
                    array($params['name'])));
            return $wpdb->insert_id;
        }
    }
    function delete_keyword ($params)
    {
        if(false===$this->validate($params,array('name')))
        {
            return false;
        }
        global $wpdb;
        $keyword_id = @current($wpdb->get_col($wpdb->prepare("SELECT `id` FROM $this->table_keywords WHERE `name`=%s",array($params['name']))));
        if(is_numeric($keyword_id))
        {
            $wpdb->query("DELETE FROM $this->table_keywords WHERE id=$keyword_id");
            return true;
        }
        return false;
    }
    function get_results ($params)
    {

    }
    function log_query ($params)
    {
        if(false===$this->validate($params,array('query','time','elapsed','results')))
        {
            return false;
        }
        global $wpdb;
        $wpdb->query($wpdb->prepare("INSERT INTO $this->table_log (`query`,`time`,`elapsed`,`results`) VALUES ( %s, %s, %d, %d )",
                array($params['query'],date('Y-m-d H:i:s',strtotime($params['time'])),$params['elapsed'],$params['results'])));
        return $wpdb->insert_id;
    }
    function cronjob ($params)
    {

    }
}