<?php
require_once "GetTagData.class.php";
require_once "Index.class.php";
require_once "API.class.php";

set_time_limit(0);
ini_set('memory_limit','64M');
ignore_user_abort(true);

class Search_Engine_Spider
{
    var $init = true;

    var $url = false;
    var $site_id = false;
    var $template_id = false;
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

    var $current_depth = -1;
    var $max_depth = false;

    var $lastmod_exclude = 0;

    var $allowed_hosts = false;
    var $domain_scope = false;
    var $current_host = false;
    var $current_scheme = false;

    var $robotstxt_check = false;
    var $robotstxt_rules = false;
    var $included_uri = false;
    var $included_uri_words = false;
    var $excluded_uri = false;
    var $excluded_uri_words = false;

    var $included_words = false;
    var $excluded_words = false;

    var $user_agent = "Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/532.5 (KHTML, like Gecko) Chrome/4.0.249.78 Safari/532.5";
    var $username = false;
    var $password = false;

    var $index = false;
    var $api = false;

    function set_site ($site_id)
    {
        if($this->api===false)
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
        if($this->api===false)
            $this->api = new Search_Engine_API();
        $template = $this->api->get_template(array('id'=>$template_id));
        $site = $this->api->get_site(array('id'=>$template['site']));
        $this->url = $site['scheme'].'://'.$site['host'].'/';
        $this->template_id = $template['id'];
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
    }

    function spider ($url=false,$manual_depth=false)
    {
        if($url===false)
        {
            $url = $this->url;
        }
        $url = (string) $url;
        if($this->api===false)
            $this->api = new Search_Engine_API();
        if($this->domain_scope===false)
        {
            if(ctype_digit($url))
                $site = $this->api->get_site(array('id'=>$url));
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
        }
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
        if($check&&!empty($this->links_queued))
        {
            if($this->max_depth!==false&&($depth+1)==$this->max_depth)
            {
                if(false!==$this->site_id)
                    $this->api->bump_site(array('id'=>$this->site_id));
                if(false!==$this->template_id)
                    $this->api->bump_template(array('id'=>$this->template_id));
                $this->message('<strong>Final Report</strong><ul><li><strong>Links Found:</strong> '.count($this->links).'</li><li><strong>Links Processed:</strong> '.count($this->links_processed).'</li><li><strong>Links Spidered:</strong> '.count($this->links_spidered).'</li><li><strong>Links Excluded:</strong> '.count($this->links_excluded).'</li><li><strong>Links Redirected:</strong> '.count($this->links_redirected).'</li><li><strong>Links Not Found:</strong> '.count($this->links_notfound).'</li><li><strong>Links With Server Errors:</strong> '.count($this->links_servererror).'</li><li><strong>Links Non-HTML Content Types:</strong> '.count($this->links_other).'</li></ul>');
                $this->message('<strong>Spidering Completed - Max Depth Reached</strong>');
                return false;
            }
            $this->brunch($this->links_queued,$depth+1);
        }
        if(false!==$this->site_id)
            $this->api->bump_site(array('id'=>$this->site_id));
        if(false!==$this->template_id)
            $this->api->bump_template(array('id'=>$this->template_id));
        $this->message('<strong>Final Report</strong><ul><li><strong>Links Found:</strong> '.count($this->links).'</li><li><strong>Links Processed:</strong> '.count($this->links_processed).'</li><li><strong>Links Spidered:</strong> '.count($this->links_spidered).'</li><li><strong>Links Excluded:</strong> '.count($this->links_excluded).'</li><li><strong>Links Redirected:</strong> '.count($this->links_redirected).'</li><li><strong>Links Not Found:</strong> '.count($this->links_notfound).'</li><li><strong>Links With Server Errors:</strong> '.count($this->links_servererror).'</li><li><strong>Links Non-HTML Content Types:</strong> '.count($this->links_other).'</li></ul>');
        $this->message('<strong>Spidering Completed</strong>');
    }
    function brunch ($urls,$depth)
    {
        $links = $urls;
        if(empty($links))
            return false;
        $this->message('<strong>Status Update</strong><ul><li><strong>Links To Be Crunched:</strong> '.count($urls).'</li><li><strong>Links Queued:</strong> '.count($this->links_queued).'</li><li><strong>Links Processed:</strong> '.count($this->links_processed).'</li><li><strong>Links Spidered:</strong> '.count($this->links_spidered).'</li><li><strong>Links Excluded:</strong> '.count($this->links_excluded).'</li><li><strong>Links Redirected:</strong> '.count($this->links_redirected).'</li><li><strong>Links Not Found:</strong> '.count($this->links_notfound).'</li><li><strong>Links With Server Errors:</strong> '.count($this->links_servererror).'</li><li><strong>Links Non-HTML Content Types:</strong> '.count($this->links_other).'</li></ul>');
        foreach($links as $link)
        {
            $this->links_processed[] = $link;
            $this->current_depth = $depth;
            $this->crunch($link);
        }
        if(!empty($this->links_queued))
        {
            if($this->max_depth!==false&&($depth+1)==$this->max_depth)
                return false;
            $this->brunch($this->links_queued,$depth+1);
        }
    }
    function crunch ($url)
    {
        $this->message('Crunching URL: '.$url);
        $this->current_url = $url;
        $parsed = @parse_url($url);
        $this->current_parsed = $parsed;
        if($this->robotstxt_check===false)
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
            $this->message('<strong>URL Not Valid</strong>');
            if($url_found===404)
                $this->links_notfound[] = $url;
            elseif(strpos((string)$url_found,'5')!==false&&strpos((string)$url_found,'5')==0)
                $this->links_servererror[] = $url;
            else
                $this->links_other[] = $url;
            $key = array_search($url,$this->links_queued);
            if($key!==false)
                unset($this->links_queued[$key]);
            else
            {
                echo 'BREAKY!';
                var_dump($key);
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
        if(strpos($this->meta['robots'],'nofollow')===false)
        {
            $this->message('Getting all links from page..');
            $links_found = $this->get_all_links($url);
            $this->message('Links Found: '.count($links_found));
            $this->links_queued = array_unique(array_merge($this->links_queued,$links_found));
        }
        else
        {
            $this->message('<strong>URL meta nofollow - No links will be crawled from this URL</strong>');
        }
        if(strpos($this->meta['robots'],'noindex')===false)
        {
            $this->message('Indexing page..');
            $this->index($url);
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
        if($this->index===false)
        {
            $this->index = new Search_Engine_Index();
            if(false!==$this->excluded_words)
                $this->index->blacklist_words = $this->excluded_words;
        }
        if($this->api===false)
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
        $index = array('url'=>$this->current_url,'site'=>$this->site_id,'title'=>$this->meta['title'],'description'=>$this->meta['description'],'fulltxt'=>$this->index->content,'lastmod'=>$this->current_last_modified,'size'=>$this->current_size,'md5_checksum'=>$this->current_md5,'level'=>$this->current_depth,'keywords'=>$keywords);
        $this->api->index_page($index);
        // update DB with data and progress
    }
    function message ($msg)
    {
        echo date('m/d/Y h:i:sa').' - '.$msg."<br />\r\n";
    }
    function get_url ($url,$method=1,$retry=0)
    {
        $parsed = @parse_url($url);
        // User Agent for Firefox found at: http://whatsmyuseragent.com/, Chrome found in Chrome at about:version
        ini_set('user_agent',$this->user_agent);
        $ret = '';
        $headers = array();
        $headers['http_code'] = 200;
        $headers['errno'] = 0;
        $headers['errmsg'] = '';
        $headers['size'] = 0;
        if($method==1)
        {
            $header = array();
            $header[0] = "Accept: application/xhtml+xml,text/html;q=0.9,text/plain";
            $header[] = "Connection: keep-alive";
            $header[] = "Keep-Alive: 300";
            $curl = curl_init($url);
            $options = array(
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_HEADER => 1,
                CURLOPT_HTTPHEADER => $header,
                CURLOPT_FOLLOWLOCATION => 0,
                CURLOPT_AUTOREFERER => 1,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_USERAGENT => $this->user_agent,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_FAILONERROR => 0,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_VERBOSE => 0
            );
            if($this->username!==false)
            {
                if($this->password===false)
                    $this->password = '';
                $options[CURLOPT_USERPWD] = $this->username.':'.$this->password;
            }
            curl_setopt_array($curl,$options);
            $ret = curl_exec($curl);
            $headers = curl_getinfo($curl);
            $headers['header'] = substr($ret,0,$headers['header_size']);
            $ret = substr($ret,$headers['header_size']);
            $headers['errno'] = curl_errno($curl);
            $headers['errmsg'] = curl_error($curl);
            curl_close($curl);
        }
        elseif($method==2)
        {
            $url = str_replace("https://","ssl://",$url);
            $file = @fopen($url,'r');
            if($file)
            {
                while(!feof($file)){$ret .= fgets($file,4096);}
                fclose($file);
            }
            else
            {
                if($retry<1)
                {
                    $this->message('<strong>fopen Failure: Retrying</strong>');
                    return $this->get_url($url,$method,($retry+1));
                }
                else
                {
                    $this->message('<strong>fopen Failure: Trying curl</strong>');
                    return $this->get_url($url,1,($retry+1));
                }
            }
        }
        elseif($method==3)
            $ret = file_get_contents($url);
        else
            return false;
        if(isset($headers['header']))
        {
            if($headers['http_code']==406)
            {
                $this->message('<strong>Invalid Content-Type</strong> - HTTP Code: '.$headers['http_code'].' / Errno: '.$headers['errno'].' / Errmsg: '.$headers['errmsg']);
                return 406;
            }
            if(preg_match('/^Content-Type: (.+?)$/m',$headers['header'],$matches))
            {
                $content_type = trim($matches[1]);
                if(strpos($content_type,'text/html')===false)
                {
                    $this->message('<strong>Invalid Content-Type</strong>: '.$content_type);
                    return 406;
                }
            }
        }
        if(in_array($headers['http_code'],array(301,302)))
        {
            if(preg_match('/^Location: (.+?)$/m',$headers['header'],$matches))
            {
                if(substr($matches[1],0,1)=="/")
                    return $parsed['scheme']."://".$parsed['host'].trim($matches[1]);
                else
                    return trim($matches[1]);
            }
        }
        elseif($headers['http_code']!=200||$headers['errno']!=0)
        {
            $this->message('<strong>Error Found</strong> - HTTP Code: '.$headers['http_code'].' / Errno: '.$headers['errno'].' / Errmsg: '.$headers['errmsg']);
            if($headers['errno']==28&&$retry<2)
            {
                $method = 2;
                $this->message('<strong>Retrying</strong> - Try #'.($retry+1));
                return $this->get_url($url,$method,($retry+1));
            }
            elseif($retry<1)
            {
                $this->message('<strong>Retrying</strong> - Try #'.($retry+1));
                return $this->get_url($url,$method,($retry+1));
            }
            else
                return $headers['http_code'];
        }
        if(empty($ret)&&$ret!=0)
        {
            $this->message('<strong>No Data Returned</strong> - HTTP Code: '.$headers['http_code'].' / Errno: '.$headers['errno'].' / Errmsg: '.$headers['errmsg']);
            if($retry<2)
            {
                $this->message('<strong>Retrying</strong> - Try #'.($retry+1));
                return $this->get_url($url,$method,($retry+1));
            }
            else
                return false;
        }
        if($ret!==false)
        {
            $this->current_last_modified = date('Y-m-d H:i:s');
            if($headers['filetime']!=-1)
                $this->current_last_modified = date('Y-m-d H:i:s',$headers['filetime']);
            else
                $this->lastmod_exclude++;
            $this->current_size = $headers['size_download'];
            if($headers['download_content_length']!=-1)
                $this->current_size = $headers['download_content_length'];
            $this->current_md5 = md5($ret);
            $this->current_data = $ret;
        }
        else
            return false;
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
        if($this->robotstxt_check===false)
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
    function check_url_structure ($url)
    {
        $invalid_common_extensions = array('3dm','3g2','3gp','7z','8bi','aac','accdb','ai','aif','app','asf','asx','avi','bak','bat','bin','blg','bmp','bup','c','cab','cfg','cgi','com','cpl','cpp','csv','cur','dat','db','dbx','deb','dll','dmg','dmp','doc','docx','drv','drw','dwg','dxf','efx','eps','exe','flv','fnt','fon','gam','gho','gif','gz','hqx','iff','indd','ini','iso','java','jpg','key','keychain','lnk','log','m3u','m4a','m4p','mdb','mid','mim','mov','mp3','mp4','mpa','mpg','mpeg','msg','msi','nes','ori','otf','pages','part','pct','pdb','pdf','pif','pkg','pl','pln','plugin','png','pps','ppt','pptx','prf','ps','psd','psp','qxd','qxp','ra','ram','rar','rels','rm','rom','rtf','sav','sdb','sdf','sit','sitx','sql','svg','swf','sys','tar.gz','thm','tif','tmp','toast','torrent','ttf','txt','uccapilog','uue','vb','vcd','vcf','vob','wav','wks','wma','wmv','wpd','wps','ws','xll','xls','xlsx','xml','yps','zip','zipx');
        $ext = @end(explode('.',$url));
        if($ext!==false&&0<strlen($ext)&&in_array($ext,$invalid_common_extensions))
            return false;
        if(strpos($url,'#')!==false&&strpos($url,'#')<1)
            return false;
        if(strpos($url,'mailto:')!==false&&strpos($url,'mailto:')<1)
            return false;
        if(strpos($url,'feed:')!==false&&strpos($url,'mailto:')<1)
            return false;
        if(strpos($url,'gtalk:')!==false&&strpos($url,'gtalk:')<1)
            return false;
        if(strpos($url,'aim:')!==false&&strpos($url,'aim:')<1)
            return false;
        if(strpos($url,'callto:')!==false&&strpos($url,'callto:')<1)
            return false;
        return true;
    }
    function validate_urls ($links)
    {
        $ret = array();
        if(!empty($links))
        {
            $k = 0;
            foreach($links as $link)
            {
                if(in_array($link,$this->links))
                    continue;
                $check = @parse_url($link);
                if(!$this->check_url_structure($link))
                {
                    $key = array_search($link,$this->links_queued);
                    if($key!==false)
                        unset($this->links_queued[$key]);
                    $this->message('<strong>Invalid URL</strong>: '.$link);
                    continue;
                }
                if(!isset($check['host'])||empty($check['host']))
                {
                    $check['scheme'] = $this->current_scheme;
                    $check['host'] = $this->current_host;
                    $link = $check['scheme'].'://'.$check['host'].$link;
                    if($check['path']=='')
                        $link .= '/';
                }
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
                        $check['path'] = implode('/',$exp).'/'.str_replace('../','',$check['path']);
                    }
                    if($this->url_exclusion($check['path'],$link)!==false)
                    {
                        if($this->current_host!==false&&$this->current_host!=$check['host'])
                        {
                            continue;
                        }
                        if(in_array($link,$this->links))
                        {
                            continue;
                        }
                        $ret[$k] = $link;
                        $this->links[] = $link;
                    }
                    else
                        continue;
                }
                else
                    continue;
                $this->message('Found URL: '.$ret[$k]);
                $k++;
            }
        }
        return array_unique($ret);
    }

    function get_all_links ($data)
    {
        $links = get_tag_data($this->current_data);
        $debug = false;
        $ret = array();
        if(!empty($links)) foreach($links as $tag=>$items)
        {
            foreach($items as $attributes)
            {
                $link = false;
                if(($tag=='a'||$tag=='area')&&isset($attributes['href'])&&strpos($attributes['href'],'#')!==0&&strpos($attributes['href'],'javascript:')!==0&&strpos($attributes['href'],'mailto:')!==0)
                    $link = $attributes['href'];
                if(($tag=='frame'||$tag=='iframe')&&isset($attributes['src']))
                    $link = $attributes['src'];
                                if($debug)var_dump($link);
                if($link!==false)
                {
                    $check = @parse_url($link);
                    if($check!==false&&isset($check['host']))
                    {
                        $poscheck = str_replace($this->domain_scope,'',$check['host']);
                        if(strpos($check['host'],$this->domain_scope)===(strlen($check['host'])-strlen($this->domain_scope)))
                            if(!in_array($link,$this->links))
                                $ret[] = $link;
                    }
                    else
                        if(!in_array($link,$this->links))
                            $ret[] = $link;
                }
            }
        }
        return $this->validate_urls(array_unique($ret));
    }
    function get_meta_data ($data)
    {
        $meta = get_tag_data($this->current_data,array('meta','title','h1','h2'));
        $ret = array('title'=>false,'robots'=>false,'description'=>false,'keywords'=>false,'h1'=>false,'h2'=>false);
        if(!empty($meta)) foreach($meta as $tag=>$items)
        {
            foreach($items as $attributes)
            {
                if($tag!='meta'&&isset($ret[$tag])&&$ret[$tag]===false&&isset($attributes['html']))
                    $ret[$tag] = strip_tags($attributes['html']);
                if($tag=='meta'&&isset($ret[$attributes['name']])&&$ret[$attributes['name']]===false&&isset($attributes['name'])&&isset($attributes['content']))
                    $ret[$attributes['name']] = $attributes['content'];
            }
        }
        return $ret;
    }
}