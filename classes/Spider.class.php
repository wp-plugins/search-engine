<?php
require_once "Simple.HTML.DOM.Parser.php";
require_once "Index.class.php";
require_once "API.class.php";

set_time_limit(6000);
@ini_set('zlib.output_compression',0);
@ini_set('output_buffering','off');
@ini_set('memory_limit','64M');
ignore_user_abort(true);

global $se_message_counter;
$se_message_counter = 0;

class Search_Engine_Spider
{
    var $init = true;
    var $silent = false;

    var $url = false;
    var $site_id = false;
    var $template_id = false;
    var $template_path = false;
    var $group_id = false;

    var $current_url = false;
    var $current_parsed = false;
    var $current_data = false;
    var $current_last_modified = null;
    var $current_size = 0;
    var $current_md5 = '';

    var $meta = false;

    var $links = array();
    var $links_queued = array();
    var $links_processed = array();
    var $links_spidered = array();
    var $links_excluded = array();
    var $links_redirected = array();
    var $links_notfound = array();
    var $links_servererror = array();
    var $links_other = array();
    var $links_duplicate = array();
    var $links_current = array();

    var $current_depth = -1;
    var $max_depth = false;

    var $lastmod_exclude = 0;

    var $allowed_hosts = false;
    var $domain_scope = false;
    var $current_host = false;
    var $cross_scheme = false;
    var $current_scheme = false;

    var $robots_ignore = false;
    var $robotstxt_check = false;
    var $robotstxt_rules = false;
    var $included_uri = false;
    var $included_uri_words = false;
    var $excluded_uri = false;
    var $excluded_uri_words = false;

    var $included_words = false;
    var $excluded_words = false;

    var $user_agent = "Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/532.5 (KHTML, like Gecko) Chrome/4.0.249.78 Safari/532.5 Search-Engine-WP-Plugin-Bot/0.5.5";
    var $username = false;
    var $password = false;

    var $index = false;
    var $api = false;
    var $queue_counter = 0;

    function flush ($seconds=5)
    {
        if(false!==$this->silent)
            return;
        ob_start();
        ob_end_clean();
        flush();
        @ob_end_flush();
        if($seconds>0)
            sleep($seconds);
    }
    function output ($msg)
    {
        if(false===$this->silent)
            echo $msg;
        return $msg;
    }
    function set_site ($site_id)
    {
        if(!isset($this->api)||$this->api===false||!is_object($this->api))
            $this->api = new Search_Engine_API();
        $site = $this->api->get_site(array('id'=>$site_id));
        $this->url = $site['scheme'].'://'.$site['host'].'/';
        $this->site_id = $site['id'];
        $this->domain_scope = $site['host'];
        $this->current_host = $site['host'];
        $this->current_scheme = $site['scheme'];
    }
    function set_template ($template_id)
    {
        if(!isset($this->api)||$this->api===false||!is_object($this->api))
            $this->api = new Search_Engine_API();
        $template = $this->api->get_template(array('id'=>$template_id));
        $site = $this->api->get_site(array('id'=>$template['site']));
        $this->url = $site['scheme'].'://'.$site['host'].'/';
        $this->template_id = $template['id'];
        $this->template_path = $template['directory'];
        if(!empty($this->template_path))
            $this->url = rtrim($this->url,'/').$this->template_path;
        else
            $this->template_path = false;
        $this->included_uri_words = array_unique(array_filter(array_map('trim',explode(',',$template['whitelist_uri_words']))));
        if(empty($this->included_uri_words))
            $this->included_uri_words = false;
        $this->excluded_uri_words = array_unique(array_filter(array_map('trim',explode(',',$template['blacklist_uri_words']))));
        if(empty($this->excluded_uri_words))
            $this->excluded_uri_words = false;
        $this->excluded_words = array_unique(array_filter(array_map('trim',explode(',',$template['blacklist_words']))));
        if(empty($this->excluded_words))
            $this->excluded_words = false;
        $this->max_depth = $template['max_depth'];
        if(empty($this->max_depth))
            $this->max_depth = false;
        if(1==$template['disable_robots'])
            $this->robots_ignore = true;
        $this->username = $template['htaccess_username'];
        if(empty($this->username))
            $this->username = false;
        $this->password = $template['htaccess_password'];
        if(empty($this->password))
            $this->password = false;
        $this->site_id = $site['id'];
        $this->domain_scope = $site['host'];
        $this->current_host = $site['host'];
        $this->current_scheme = $site['scheme'];
        $this->cross_scheme = $template['cross_scheme'];
        if($this->cross_scheme==1)
            $this->cross_scheme = true;
        else
            $this->cross_scheme = false;
    }

    function spider ($url=false,$manual_depth=false)
    {
        for($i=0;$i<40000;$i++)
        {
            $this->output("\t \n"); // extra space for output in browser
        }
        $this->flush(4);
        if(false===$url)
        {
            $url = $this->url;
        }
        $url = (string) $url;
        if(!isset($this->api)||$this->api===false||!is_object($this->api))
            $this->api = new Search_Engine_API();
        if(false===$this->domain_scope)
        {
            if(ctype_digit($url))
                $site = $this->api->get_site(array('id'=>$url,'force'=>true));
            else
            {
                $parsed = @parse_url($url);
                $site = $this->api->get_site(array('host'=>$parsed['host'],'scheme'=>$parsed['scheme']));
            }
            if($site===false)
                return false;
            $url = $site['scheme'].'://'.$site['host'].'/';
            $this->site_id = $site['id'];
            $this->domain_scope = $site['host'];
            $this->current_host = $site['host'];
            $this->current_scheme = $site['scheme'];
        }
        $url = $this->validate_urls($url);
        $this->url = $url;
        $this->message('Spidering URL: '.$url);
        if($manual_depth===false)
        {
            $this->current_depth++;
            if($this->max_depth!==false&&$this->current_depth==$this->max_depth)
                return false;
        }
        else
            $this->current_depth = $manual_depth+1;
        $depth = $this->current_depth;
        $this->links[] = $url;
        $this->links_processed[] = $url;
        $check = $this->crunch($url);
        return $this->munch($this->links_queued,$depth);
    }
    function munch ($urls,$depth)
    {
        if(!isset($this->api)||$this->api===false||!is_object($this->api))
            $this->api = new Search_Engine_API();
        if(!isset($this->index)||$this->index===false||!is_object($this->index))
        {
            $this->index = new Search_Engine_Index();
            $this->index->robots_ignore = $this->robots_ignore;
            if(false!==$this->excluded_words)
                $this->index->blacklist_words = $this->excluded_words;
        }
        if(!empty($urls))
        {
            if($this->max_depth!==false&&($depth+1)==$this->max_depth)
            {
                if(false!==$this->site_id)
                    $this->api->bump_site(array('id'=>$this->site_id));
                if(false!==$this->template_id)
                    $this->api->bump_template(array('id'=>$this->template_id));
                $this->message('<strong>Final Report</strong><ul><li><strong>Links Found:</strong> '.count($this->links).'</li><li><strong>Links Processed:</strong> '.count($this->links_processed).'</li><li><strong>Links Spidered:</strong> '.count($this->links_spidered).'</li><li><strong>Links Excluded:</strong> '.count($this->links_excluded).'</li><li><strong>Links Redirected:</strong> '.count($this->links_redirected).'</li><li><strong>Links Not Found:</strong> '.count($this->links_notfound).'</li><li><strong>Links With Server Errors:</strong> '.count($this->links_servererror).'</li><li><strong>Links Non-HTML Content Types:</strong> '.count($this->links_other).'</li></ul>');
                $this->message('<strong>Spidering Completed - Max Depth Reached</strong>');
                return true;
            }
            $this->brunch($urls,$depth+1);
        }
        if(false!==$this->site_id)
            $this->api->bump_site(array('id'=>$this->site_id));
        if(false!==$this->template_id)
            $this->api->bump_template(array('id'=>$this->template_id));
        $this->api->delete_queue(array('site'=>$this->site_id,'template'=>$this->template_id));
        $this->message('<strong>Final Report</strong><ul><li><strong>Links Found:</strong> '.count($this->links).'</li><li><strong>Links Processed:</strong> '.count($this->links_processed).'</li><li><strong>Links Spidered:</strong> '.count($this->links_spidered).'</li><li><strong>Links Excluded:</strong> '.count($this->links_excluded).'</li><li><strong>Links Redirected:</strong> '.count($this->links_redirected).'</li><li><strong>Links Not Found:</strong> '.count($this->links_notfound).'</li><li><strong>Links With Server Errors:</strong> '.count($this->links_servererror).'</li><li><strong>Links Non-HTML Content Types:</strong> '.count($this->links_other).'</li></ul>');
        $this->message('<strong>Spidering Completed</strong>');
        return true;
    }
    function brunch ($urls,$depth)
    {
        $this->queue_counter = 0;
        $api = $this->api;
        $this->api = false;
        $index = $this->index;
        $this->index = false;
        $this->links_current = $urls;
        $this->flush(6); // alleviate the load, output some html on certain servers/browsers that don't output as it goes
        $api->update_queue(array('site'=>$this->site_id,'template'=>$this->template_id,'queue'=>json_encode((array)$this)));
        $this->api = $api;
        $this->index = $index;
        if(empty($urls))
            return false;
        $this->message('<strong>Status Update</strong><ul><li><strong>Links To Be Crunched:</strong> '.count($urls).'</li><li><strong>Links Queued:</strong> '.count($this->links_queued).'</li><li><strong>Links Processed:</strong> '.count($this->links_processed).'</li><li><strong>Links Spidered:</strong> '.count($this->links_spidered).'</li><li><strong>Links Excluded:</strong> '.count($this->links_excluded).'</li><li><strong>Links Redirected:</strong> '.count($this->links_redirected).'</li><li><strong>Links Not Found:</strong> '.count($this->links_notfound).'</li><li><strong>Links With Server Errors:</strong> '.count($this->links_servererror).'</li><li><strong>Links Non-HTML Content Types:</strong> '.count($this->links_other).'</li></ul>');
        foreach($urls as $link)
        {
            $this->links_processed[] = $link;
            $this->current_depth = $depth;
            $this->crunch($link);
        }
        $this->queue_counter = 0;
        if(!empty($this->links_queued))
        {
            if($this->max_depth!==false&&($depth+1)==$this->max_depth)
                return false;
            $this->brunch($this->links_queued,$depth+1);
        }
    }
    function crunch ($url)
    {
        $this->queue_counter++;
        if($this->queue_counter==15)
        {
            $api = $this->api;
            $this->api = false;
            $index = $this->index;
            $this->index = false;
            $this->links_current = array_diff($this->links_current,$this->links_processed);
            $this->flush(2); // alleviate the load, output some html on certain servers/browsers that don't output as it goes
            $api->update_queue(array('site'=>$this->site_id,'template'=>$this->template_id,'queue'=>json_encode((array)$this)));
            $this->api = $api;
            $this->index = $index;
            $this->queue_counter = 0;
        }
        $this->message('Crunching Next URL: '.$url);
        $this->current_url = $url;
        $parsed = @parse_url($url);
        $parsed['path'] = '/'.ltrim($parsed['path'],'/');
        $this->current_parsed = $parsed;
        if(false===$this->robotstxt_check&&false===$this->robots_ignore)
            $this->get_robotstxt($url);
        if($this->url_exclusion($parsed['path'],$url)===false)
        {
            $key = array_search($url,$this->links_queued);
            if($key!==false)
                unset($this->links_queued[$key]);
            $this->links_excluded[] = $url;
            $this->message('<strong>URL Excluded</strong>');
            return false;
        }
        $url_found = $this->get_url($url);
        if($url_found===false||ctype_digit((string)$url_found))
        {
            $this->message('<strong>Invalid URL</strong>');
            if($url_found===404)
            {
                $this->message('<strong>404 Page Not Found:</strong> '.$url);
                $this->links_notfound[] = $url;
            }
            elseif(strpos((string)$url_found,'5')!==false&&strpos((string)$url_found,'5')==0)
            {
                $this->message('<strong>Server Error '.$url_found.':</strong> '.$url);
                $this->links_servererror[] = $url;
            }
            else
            {
                $this->message('<strong>Error '.$url_found.':</strong> '.$url);
                $this->links_other[] = $url;
            }
            $key = array_search($url,$this->links_queued);
            if($key!==false)
                unset($this->links_queued[$key]);
            else
            {
                $this->output('BREAKY! '.$key);
            }
            return false;
        }
        elseif($url_found!=$url)
        {
            $parsed = @parse_url($url_found);
            if(empty($parsed['path'])||!isset($parsed['path']))
                $url_found .='/';
            $this->message('URL Redirected to: '.$url_found);
            $this->links_redirected[] = $url;
            $key = array_search($url,$this->links_queued);
            if($key!==false)
                unset($this->links_queued[$key]);
            $this->links_processed[] = $url;
            if(!in_array($url_found,$this->links)&&$parsed['host']==$this->domain_scope)
            {
                $this->links_queued[] = $url_found;
                $this->links[] = $url_found;
                return $this->crunch($url_found);
            }
            return false;
        }
        $this->meta = $this->get_meta_data($url);
        if(false===strpos($this->meta['robots'],'nofollow')||false!==$this->robots_ignore)
        {
            $this->message('Getting all links from page..');
            $links_found = $this->get_all_links($url);
            $this->message('New Links Found: '.count($links_found));
            $this->links_queued = array_unique(array_merge($this->links_queued,$links_found));
        }
        else
        {
            $this->message('<strong>URL meta nofollow - No links will be crawled from this URL</strong>');
        }
        if(false===strpos($this->meta['robots'],'noindex')||false!==$this->robots_ignore)
        {
            $this->message('Indexing page..');
            $indexed = $this->index($url);
            if($index!==false)
                $this->links_spidered[] = $url;
        }
        else
        {
            $this->message('<strong>URL meta noindex - Will not Index</strong>');
            $this->links_excluded[] = $url;
        }
        $key = array_search($url,$this->links_queued);
        if($key!==false)
            unset($this->links_queued[$key]);
        return true;
    }
    function index ($url)
    {
        if(!isset($this->index)||$this->index===false||!is_object($this->index))
        {
            $this->index = new Search_Engine_Index();
            $this->index->robots_ignore = $this->robots_ignore;
            if(false!==$this->excluded_words)
                $this->index->blacklist_words = $this->excluded_words;
        }
        if(!isset($this->api)||$this->api===false||!is_object($this->api))
            $this->api = new Search_Engine_API();
        $this->index->get_content($this->current_data,$url);
        $this->index->get_keyword_counts();
        $this->index->get_keyword_weights($url,$this->meta);
        $keywords = array();
        if(!empty($this->index->keyword_weights)) foreach($this->index->keyword_weights as $keyword=>$weight)
        {
            $keyword_id = $this->api->get_keyword(array('name'=>$keyword));
            $keywords[$keyword_id] = $weight;
        }
        $check = array('url'=>$this->current_url,'site'=>$this->site_id,'md5_checksum'=>$this->current_md5);
        $check_md5 = $this->api->md5_page($check);
        if($check_md5===false||$check_md5['url']!=$this->current_url)
        {
            $index = array('url'=>$this->current_url,'site'=>$this->site_id,'title'=>$this->meta['title'],'description'=>$this->meta['description'],'fulltxt'=>$this->index->content,'lastmod'=>$this->current_last_modified,'size'=>$this->current_size,'md5_checksum'=>$this->current_md5,'level'=>$this->current_depth,'keywords'=>$keywords);
            $this->api->index_page($index);
            return true;
        }
        else
        {
            $this->message('<strong>Duplicate Detected!</strong> Another URL has already been indexed with the same content: '.$check_md5['url']);
            $this->message('<strong>Content is Duplicate - Will not Index</strong>');
            $this->links_duplicate[] = $url;
            return false;
        }
    }
    function message ($msg)
    {
        global $se_message_counter;
        $msg = $this->output(date('m/d/Y h:i:sa').' - '.$msg."<br />\r\n");
        if($se_message_counter==11)
        {
            $se_message_counter==0;
            $this->flush(0); // alleviate the load, output some html on certain servers/browsers that don't output as it goes
        }
        $se_message_counter++;
        return $msg;

    }
    function get_url ($url,$retry=0)
    {
        $parsed = @parse_url($url);
        // User Agent for Firefox found at: http://whatsmyuseragent.com/, Chrome found in Chrome at about:version
        ini_set('user_agent',$this->user_agent);
        $options = array('user-agent'=>$this->user_agent,'redirection'=>5);
        if($this->username!==false)
        {
            if($this->password===false)
                $this->password = '';
            $options['headers']['authorization'] = 'Basic '.base64_encode($this->username.':'.$this->password);
        }
        $remote = wp_remote_request($url,$options);
        if(!is_array($remote))
        {
            if(is_wp_error($remote))
            {
                return $this->message('<strong style="color:red;">ERROR:</strong> '.$remote->get_error_message());
            }
            return $this->message('<strong style="color:red;">ERROR:</strong> Page not available.');
        }
        if(!isset($remote['body']))
        {
            $ret = $remote = false;
        }
        else
        {
            $ret = $remote['body'];
        }
        if($remote!==false)
        {
            if($remote['response']['code']==406)
            {
                $this->message('<strong>Unsupported Content-Type</strong> - HTTP Code: '.$remote['response']['code'].' / Message: '.$remote['response']['message']);
                return 406;
            }
            if(strpos($remote['headers']['content-type'],'text/html')===false)
            {
                $this->message('<strong>Unsupported Content-Type</strong>: '.$remote['headers']['content-type']);
                return 406;
            }
            if(in_array($remote['response']['code'],array(301,302)))
            {
                if(substr($remote['headers']['location'],0,1)=="/")
                    return $parsed['scheme']."://".$parsed['host'].trim($remote['headers']['location']);
                else
                    return trim($remote['headers']['location']);
            }
            elseif($remote['response']['code']!=200)
            {
                $this->message('<strong>Error Found</strong> - HTTP Code: '.$remote['response']['code'].' / Message: '.$remote['response']['message']);
                return $remote['response']['code'];
            }
        }
        if(strlen($ret)<1)
        {
            $this->message('<strong>No Data Returned</strong> - HTTP Code: '.$remote['response']['code'].' / Message: '.$remote['response']['message']);
            if($retry<2)
            {
                $this->message('<strong>Retrying</strong> - Try #'.($retry+1));
                return $this->get_url($url,($retry+1));
            }
            else
                return false;
        }
        $this->current_last_modified = date('Y-m-d H:i:s');
        if(isset($remote['headers']['date'])&&$remote['headers']['date']!=-1)
            $this->current_last_modified = date('Y-m-d H:i:s',strtotime($remote['headers']['date']));
        else
            $this->lastmod_exclude++;
        $this->current_size = strlen($ret);
        if(isset($remote['headers']['content-length'])&&$remote['headers']['content-length']>0)
            $this->current_size = $remote['headers']['content-length'];
        elseif(isset($remote['headers']['download-content-length'])&&$remote['headers']['download-content-length']>0)
            $this->current_size = $remote['headers']['download-content-length'];
        $this->current_md5 = md5($ret);
        $this->current_data = $ret;
        return $url;
    }
    function exclude_url ($url)
    {
        if(!in_array($url,$this->links_excluded))
            $this->links_excluded[] = $url;
        $key = array_search($url,$this->links_queued);
        if($key!==false)
            unset($this->links_queued[$key]);
    }
    function url_exclusion ($path,$url)
    {
        if($this->robotstxt_rules!==false&&$this->url_exclusion_robotstxt($path)===false)
        {
            $this->exclude_url($url);
            $this->message('<strong>URL excluded by robots.txt</strong>');
            return false;
        }
        if($this->excluded_uri!==false&&$this->url_exclusion_uri($path)===false)
        {
            $this->exclude_url($url);
            $this->message('<strong>URL excluded by URI blacklist</strong>');
            return false;
        }
        if($this->excluded_uri_words!==false&&$this->url_exclusion_uri_words($path)===false)
        {
            $this->exclude_url($url);
            $this->message('<strong>URL excluded by URI blacklist</strong>');
            return false;
        }
        if($this->included_uri!==false&&$this->url_inclusion_uri($path)===false)
        {
            $this->exclude_url($url);
            $this->message('<strong>URL excluded by not being in URI whitelist</strong>');
            return false;
        }
        if($this->included_uri_words!==false&&$this->url_inclusion_uri_words($path)===false)
        {
            $this->exclude_url($url);
            $this->message('<strong>URL excluded by not being in URI whitelist</strong>');
            return false;
        }
        return true;
    }
    function url_exclusion_robotstxt ($path)
    {
        foreach($this->robotstxt_rules as $rule)
        {
            if(preg_match("/^$rule/", $path))
                return false;
        }
        return true;
    }
    function url_exclusion_uri ($path)
    {
        foreach($this->excluded_uri as $rule)
        {
            if(strpos($path,$rule)!==false)
                return false;
        }
        return true;
    }
    function url_exclusion_uri_words ($path)
    {
        foreach($this->excluded_uri_words as $rule)
        {
            if(strpos($path,$rule)!==false)
                return false;
        }
        return true;
    }
    function url_inclusion_uri ($path)
    {
        foreach($this->included_uri as $rule)
        {
            if(strpos($path,$rule)!==false)
                return true;
        }
        return false;
    }
    function url_inclusion_uri_words ($path)
    {
        foreach($this->included_uri_words as $rule)
        {
            if(strpos($path,$rule)!==false)
                return true;
        }
        return false;
    }
    function get_robotstxt ($url)
    {
        if(false===$this->robotstxt_check&&false===$this->robots_ignore)
        {
            # Modified from the original PHP code by Chirp Internet: www.chirp.com.au
            $parsed = @parse_url($url);
            $agents = preg_quote('*');
            $robotstxt = @file("http://{$parsed['host']}/robots.txt");
            if(!$robotstxt)
                return true;
            $rules = array();
            $ruleapplies = false;
            foreach($robotstxt as $line)
            {
                if(!$line = trim($line))
                    continue;
                if(preg_match('/User-agent: (.*)/i', $line, $match))
                    $ruleapplies = preg_match("/($agents)/i", $match[1]);
                if($ruleapplies && preg_match('/Disallow:(.*)/i', $line, $regs))
                {
                    if(!$regs[1])
                        return true;
                    $rules[] = preg_quote(trim($regs[1]), '/');
                }
            }
            if(!empty($rules))
                $this->robotstxt_rules = $rules;
        }
    }
    function check_url_structure ($url,$parsed)
    {
        $invalid_common_extensions = array('3dm','3g2','3gp','7z','8bi','aac','accdb','ai','aif','app','asf','asx','avi','bak','bat','bin','blg','bmp','bup','c','cab','cfg','cgi','com','cpl','cpp','csv','cur','dat','db','dbx','deb','dll','dmg','dmp','doc','docx','drv','drw','dwg','dxf','efx','eps','exe','flv','fnt','fon','gam','gho','gif','gz','hqx','iff','indd','ini','iso','java','jpg','key','keychain','lnk','log','m3u','m4a','m4p','mdb','mid','mim','mov','mp3','mp4','mpa','mpg','mpeg','msg','msi','nes','ori','otf','pages','part','pct','pdb','pdf','pif','pkg','pl','pln','plugin','png','pps','ppt','pptx','prf','ps','psd','psp','qxd','qxp','ra','ram','rar','rels','rm','rom','rtf','sav','sdb','sdf','sit','sitx','sql','svg','swf','sys','tar.gz','thm','tif','tmp','toast','torrent','ttf','txt','uccapilog','uue','vb','vcd','vcf','vob','wav','wks','wma','wmv','wpd','wps','ws','xll','xls','xlsx','xml','yps','zip','zipx');
        $ext = @end(explode('.',$url));
        if($ext!==false&&0<strlen($ext)&&in_array($ext,$invalid_common_extensions))
        {
            $this->message('<strong>Non-HTML Content Type</strong>: '.$ext);
            $this->links_other[] = $url;
            return false;
        }
        if(strpos($url,'#')!==false)
        {
            if(strpos($url,'#')<1)
            {
                $this->message('<strong>URL starts with "#"</strong>: '.$url);
                $this->links_other[] = $url;
                return false;
            }
            else
            {
                $test = explode('#',$url);
                $test = array_reverse($test);
                unset($test[0]);
                $test = array_reverse($test);
                $test = implode('#',$test);
                if(in_array($test,$this->links)||in_array($test,$this->links_queued))
                {
                    $this->links_other[] = $url;
                    return false;
                }
                elseif(in_array($test,$this->links_other)||in_array($test,$this->links_processed)||in_array($test,$this->links_spidered)||in_array($test,$this->links_excluded)||in_array($test,$this->links_redirected)||in_array($test,$this->links_notfound)||in_array($test,$this->links_servererror)||in_array($test,$this->links_duplicate))
                    return false;
            }
        }
        if(strpos($url,':')!==false&&strpos($url,'http://')===false&&strpos($url,'https://')===false)
        {
            $this->message('<strong>Non-Standard URL Scheme</strong>: '.$parsed['scheme']);
            $this->links_other[] = $url;
            return false;
        }
        return true;
    }
    function validate_urls ($links)
    {
        $ret = array();
        $single = false;
        if(!empty($links))
        {
            $k = 0;
            if(!is_array($links))
            {
                $links = array($links);
                $single = true;
            }
            foreach($links as $link)
            {
                if(empty($link))
                    continue;
                if(in_array($link,$this->links)||in_array($link,$this->links_queued))
                    continue;
                if(in_array($link,$this->links_other)||in_array($link,$this->links_processed)||in_array($link,$this->links_spidered)||in_array($link,$this->links_excluded)||in_array($link,$this->links_redirected)||in_array($link,$this->links_notfound)||in_array($link,$this->links_servererror)||in_array($link,$this->links_duplicate))
                    continue;
                $check = @parse_url($link);
                if(!$this->check_url_structure($link,$check))
                {
                    $key = array_search($link,$this->links_queued);
                    if($key!==false)
                        unset($this->links_queued[$key]);
                    $this->message('<strong>Skipping URL</strong>: '.$link);
                    $this->message('<strong>Status Update</strong><ul><li><strong>Links To Be Crunched:</strong> '.count($urls).'</li><li><strong>Links Queued:</strong> '.count($this->links_queued).'</li><li><strong>Links Processed:</strong> '.count($this->links_processed).'</li><li><strong>Links Spidered:</strong> '.count($this->links_spidered).'</li><li><strong>Links Excluded:</strong> '.count($this->links_excluded).'</li><li><strong>Links Redirected:</strong> '.count($this->links_redirected).'</li><li><strong>Links Not Found:</strong> '.count($this->links_notfound).'</li><li><strong>Links With Server Errors:</strong> '.count($this->links_servererror).'</li><li><strong>Links Non-HTML Content Types:</strong> '.count($this->links_other).'</li></ul>');
                    continue;
                }
                if(!isset($check['host'])||empty($check['host']))
                {
                    $check['scheme'] = $this->current_scheme;
                    $check['host'] = $this->current_host;
                    $check['path'] = '/'.ltrim($check['path'],'/');
                }
                if($this->current_scheme!=$check['scheme']&&false===$this->cross_scheme&&$this->current_host==$check['host'])
                {
                    $this->message('Fixing HTTP/HTTPS scheme to match current site: '.$ret[$k]);
                    $check['scheme'] = $this->current_scheme;
                }
                $link = $check['scheme'].'://'.$check['host'].$check['path'].(isset($check['query'])&&!empty($check['query'])?'?'.$check['query']:'');
                if($check!==false&&isset($check['path']))
                {
                    if(strpos($check['path'],'../')!==false)
                    {
                        $exp = explode('/',$this->current_parsed['path']);
                        $pathexp = explode('../',$check['path']);
                        $pathcount = count($pathexp);
                        $exp = array_reverse($exp);
                        for($x=0;$x<$pathcount;$x++)
                        {
                            unset($exp[0]);
                            $exp = array_reverse($exp);
                            $exp = array_reverse($exp);
                        }
                        $exp = array_filter($exp);
                        $exp = array_reverse($exp);
                        $check['path'] = str_replace('//','/','/'.implode('/',$exp).'/'.str_replace('../','',$check['path']));
                        $link = $check['scheme'].'://'.$check['host'].$check['path'].(isset($check['query'])&&!empty($check['query'])?'?'.$check['query']:'');
                    }
                    $check['path'] = '/'.ltrim($check['path'],'/');
                    if($this->url_exclusion($check['path'],$link)!==false||$this->url_exclusion($check['path'].'?'.$check['query'],$link)!==false)
                    {
                        if($this->current_host!==false&&$this->current_host!=$check['host'])
                            continue;
                        if(in_array($link,$this->links))
                            continue;
                        $ret[$k] = $link;
                    }
                    else
                        continue;
                }
                else
                    continue;
                $this->message('Found URL: '.$ret[$k]);
                $this->links[] = $link;
                $k++;
            }
        }
        if(false!==$single)
        {
            return @current($ret);
        }
        return array_unique($ret);
    }

    function get_all_links ($data)
    {
        $filters = false;
        if(false!==$this->robots_ignore)
            $filters = array('a'=>array('rel'=>array('searchengine_nofollow','searchengine_noindex')));
        $links = get_tag_data($this->current_data,array('a','area','link','iframe','frame'),false,$filters);
        $debug = false;
        $ret = array();
        if(!empty($links)) foreach($links as $tag=>$items)
        {
            foreach($items as $item)
            {
                $link = false;
                if(($tag=='a'||$tag=='area'||$tag=='link')&&strpos($item,'#')!==0&&strpos($item,'javascript:')!==0&&strpos($item,'mailto:')!==0)
                    $link = $item;
                if($tag=='frame'||$tag=='iframe')
                    $link = $item;
                if($debug)
                    var_dump($link);
                if($link!==false)
                {
                    $check = @parse_url($link);
                    if($check!==false&&isset($check['host']))
                    {
                        $poscheck = str_replace($this->domain_scope,'',$check['host']);
                        if(strpos($check['host'],$this->domain_scope)===(strlen($check['host'])-strlen($this->domain_scope)))
                            if(!in_array($link,$this->links)&&!empty($check['path']))
                                $ret[] = $link;
                    }
                    else
                        if(!in_array($link,$this->links)&&!empty($check['path']))
                            $ret[] = $link;
                }
            }
        }
        return $this->validate_urls(array_unique($ret));
    }
    function get_meta_data ($data)
    {
        $filters = false;
        if(false!==$this->robots_ignore)
            $filters = array('a'=>array('rel'=>array('searchengine_nofollow','searchengine_noindex')));
        $meta = get_tag_data($this->current_data,array('meta','title'),true,$filters);
        $headings = get_tag_data($this->current_data,array('h1','h2','h3'),false,$filters);
        $ret = array('title'=>false,'robots'=>false,'description'=>false,'keywords'=>false,'h1'=>false,'h2'=>false,'h3'=>false);
        if(!empty($meta)) foreach($meta as $tag=>$items)
        {
            if(empty($tag))
                continue;
            if($ret[$tag]===false)
                $ret[$tag] = array();
            foreach($items as $attributes)
            {
                if(is_array($attributes))
                {
                    if($tag=='meta')
                    {
                        if(isset($ret[$attributes['name']])&&!empty($attributes['content']))
                        {
                            $tag = $attributes['name'];
                            if(!is_array($ret[$tag]))
                                $ret[$tag] = array();
                            $ret[$tag][] = $attributes['content'];
                        }
                    }
                    else
                    {
                        if(!is_array($ret[$tag]))
                            $ret[$tag] = array();
                        $ret[$tag][] = strip_tags($attributes['html']);
                    }
                }
                else
                    $ret[$tag][] = $attributes;
            }
            $ret[$tag] = array_filter((array)$ret[$tag]);
            if(!empty($ret[$tag]))
                $ret[$tag] = trim(implode(' ',$ret[$tag]));
            else
                $ret[$tag]= false;
        }
        if(!empty($headings)) foreach($headings as $tag=>$items)
        {
            if($ret[$tag]===false||!isset($ret[$tag]))
                $ret[$tag] = array();
            foreach($items as $attributes)
            {
                if(is_array($attributes))
                {
                    if(!empty($attributes['content']))
                        $ret[$tag][] = $attributes['content'];
                    elseif(!empty($attributes['html']))
                        $ret[$tag][] = strip_tags($attributes['html']);
                }
                else
                    $ret[$tag][] = $attributes;
            }
            $ret[$tag] = array_filter((array)$ret[$tag]);
            if(!empty($ret[$tag]))
                $ret[$tag] = trim(implode(' ',$ret[$tag]));
            else
                $ret[$tag]= false;
        }
        return $ret;
    }
}