<?php
require_once "Simple.HTML.DOM.Parser.php";

class Search_Engine_Index
{
    var $robots_ignore = false;

    var $content = false;
    var $keywords = false;
    var $keyword_counts = false;
    var $keyword_weights = false;

    var $blacklist_words = false;
    var $common_words = array(' a ',
                                                            ' all ',
                                                            ' am ',
                                                            ' an ',
                                                            ' and ',
                                                            ' any ',
                                                            ' are ',
                                                            ' as ',
                                                            ' at ',
                                                            ' be ',
                                                            ' but ',
                                                            ' can ',
                                                            ' did ',
                                                            ' do ',
                                                            ' does ',
                                                            ' for ',
                                                            ' from ',
                                                            ' had ',
                                                            ' has ',
                                                            ' have ',
                                                            ' here ',
                                                            ' how ',
                                                            ' i ',
                                                            ' i\'m ',
                                                            ' if ',
                                                            ' in ',
                                                            ' is ',
                                                            ' it ',
                                                            ' mine ',
                                                            ' my ',
                                                            ' no ',
                                                            ' not ',
                                                            ' of ',
                                                            ' on ',
                                                            ' or ',
                                                            ' our ',
                                                            ' ours ',
                                                            ' so ',
                                                            ' that ',
                                                            ' that\'s ',
                                                            ' the ',
                                                            ' their ',
                                                            ' their\'s ',
                                                            ' then ',
                                                            ' there ',
                                                            ' they\'re ',
                                                            ' this ',
                                                            ' to ',
                                                            ' too ',
                                                            ' up ',
                                                            ' use ',
                                                            ' uses ',
                                                            ' what ',
                                                            ' when ',
                                                            ' where ',
                                                            ' who ',
                                                            ' who\'s ',
                                                            ' why ',
                                                            ' you ',
                                                            ' your ',
                                                            ' your\'s ');

    function get_keyword_weights ($url, $meta, $keywords=false)
    {
        if(is_array($keywords))
        {
            $this->keyword_counts = $this->get_keyword_counts($keywords);
        }
        $this->keyword_weights = array();
        $meta_url = $this->get_keyword_counts($this->get_keywords(str_replace('http://','',$meta['url'])),true);
        $meta_title = $meta_description = $meta_keywords = $meta_h1 = $meta_h2 = array();
        if(isset($meta['title'])&&!empty($meta['title']))
            $meta_title = $this->get_keyword_counts($this->get_keywords($meta['title']),true);
        if(isset($meta['description'])&&!empty($meta['description']))
            $meta_description = $this->get_keyword_counts($this->get_keywords($meta['description']),true);
        if(isset($meta['keywords'])&&!empty($meta['keywords']))
            $meta_keywords = $this->get_keyword_counts($this->get_keywords($meta['keywords']),true);
        if(isset($meta['h1'])&&!empty($meta['h1']))
            $meta_h1 = $this->get_keyword_counts($this->get_keywords($meta['h1']),true);
        if(isset($meta['h2'])&&!empty($meta['h2']))
            $meta_h2 = $this->get_keyword_counts($this->get_keywords($meta['h2']),true);
        $parsed = parse_url($url);
        $parsed = explode('/',$parsed['path']);
        $parsed = array_filter($parsed);
        $parsed = count($parsed);
        foreach($this->keyword_counts as $keyword => $count)
        {
            $score = $count * 5;
            if($parsed==0)
                $score += 10;
            else
            {
                if($parsed<5)
                {
                    $parse_calc = $parsed * (4-$parsed);
                    $score += $parse_calc - 5;
                }
            }
            if(isset($meta_url[$keyword]))
                $score += $meta_url[$keyword] * 40;
            if(isset($meta_title[$keyword]))
                $score += $meta_title[$keyword] * 30;
            if(isset($meta_h1[$keyword]))
                $score += $meta_h1[$keyword] * 30;
            if(isset($meta_h2[$keyword]))
                $score += $meta_h2[$keyword] * 20;
            if(isset($meta_description[$keyword]))
                $score += $meta_description[$keyword];
            if(isset($meta_keywords[$keyword]))
                $score += $meta_keywords[$keyword];
            $this->keyword_weights[$keyword] = $score;
        }
    }
    function remove_blacklisted_words ($keywords)
    {
        if(!is_array($keywords))
            $keywords = explode(' ',$keywords);
        if(!empty($this->blacklist_words))
        {
            $keywords = str_ireplace($this->blacklist_words,'',$keywords);
        }
        return array_filter($keywords);
    }
    function get_keyword_counts ($keywords=false, $return=false)
    {
        if($keywords==false)
            $keywords = $this->keywords;
        if(!is_array($keywords))
            $keywords = explode(' ',$keywords);
        $keywords = array_count_values($keywords);
        arsort($keywords);
        if($return)
            return $keywords;
        else
            $this->keyword_counts = $keywords;
    }
    function remove_insignificant (&$value, $index)
    {
        $value = trim($value);
        if(strlen($value)<2)
        {
            $value = '';
        }
    }
    function get_keywords ($content)
    {
        $content = preg_replace("[^A-Za-z_\'-]", " ", $content);
        $content = preg_replace( '/&.{0,}?;/', '', $content ); // remove entities
        $content = str_replace(array('-',' _ ',' \' '),' ',' '.$content.' ');
        $content = str_ireplace($this->common_words,' ',' '.$content.' ');
        $keywords = explode(' ',$content);
        array_walk($keywords,'Search_Engine_Index::remove_insignificant');
        return array_filter($keywords);
    }
    function get_content ($html,$url)
    {
        $filters = false;
        if(false!==$this->robots_ignore)
            $filters = array('a'=>array('rel'=>array('searchengine_nofollow','searchengine_noindex')));
        $tags = get_tag_data($html,array('img','a'),false,$filters);
        $parsed = parse_url($url);
        $additional_content = array();
        if(!empty($tags)) foreach($tags as $tag=>$items)
        {
            if($tag=='img')
            {
                foreach($items as $attributes)
                {
                    if(!empty($attributes['title']))
                        $additional_content[] = trim($attributes['title']);
                    if(!empty($attributes['alt']))
                        $additional_content[] = trim($attributes['alt']);
                }
            }
            if($tag=='a')
            {
                foreach($items as $attributes)
                {
                    if(!empty($attributes['title']))
                        $additional_content[] = trim($attributes['title']);
                }
            }
        }
        //$additional_content = array_merge($additional_content,explode(' ',str_replace('/',' ',$parsed['path'])));
        $additional_content = implode(' ',$additional_content);
        $additional_content = $this->get_keywords($additional_content);
        $replace = array('<br />','<br/>','<br>',"\n","\r","\t");
        $html = str_ireplace($replace,' ',$html);
        $html = preg_replace('/&.{0,}?;/','',$html); // remove entities
        $real_content = strip_tags(get_indexable_html($html));
        $content = $this->get_keywords($real_content);
        $content = array_merge($additional_content,$content);
        if(!empty($this->blacklist_words))
            $content = $this->remove_blacklisted_words($content);
        $content = str_replace('/',' ',$parsed['path']).' '.implode(' ',$content);
        $content = preg_replace('/\s\s+/',' ',$content);
        $this->keywords = explode(' ',trim($content));
        $real_html = preg_replace('/\s\s+/',' ',$real_content);
        $this->content = trim($real_html);
    }
}