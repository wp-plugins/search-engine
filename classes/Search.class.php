<?php
require_once "Stemmer.class.php";

class Search_Engine_Search
{
    var $site_ids = null;
    var $stemmer = false;
    var $search_string = '';
    var $query_string = '';
    var $site_string = '';
    var $type = '';
    var $params = array();

    var $results_per_page = 5;
    var $page = 1;

    function __construct($site_ids=null)
    {
        global $wpdb;
        $this->query_string = 'SELECT
                                SQL_CALC_FOUND_ROWS
                                '.SEARCH_ENGINE_TBL.'links.*,
                                '.SEARCH_ENGINE_TBL.'keywords.name,
                                '.SEARCH_ENGINE_TBL.'index.weight
                             FROM
                                '.SEARCH_ENGINE_TBL.'links,'.SEARCH_ENGINE_TBL.'keywords,'.SEARCH_ENGINE_TBL.'index WHERE ';
        if(!empty($site_ids)&&is_array($site_ids))
        {
            $this->site_ids = $site_ids;
            $sql = array();
            foreach($site_ids as $site_id)
            {
                $sql[] = $wpdb->prepare(SEARCH_ENGINE_TBL.'links.site=%d',array($site_id));
            }
            $sql = '('.implode(' OR ',$sql).')';
            $this->site_string = $sql;
            $this->query_string .= "$sql AND ";
        }
    }

    function stem($word)
    {
        if($this->stemmer===false)
            $this->stemmer = new Stemmer();
        return $this->stemmer->stem($word);
    }

    function search_exact_query($query)
    {
        $query_string = preg_match_all("/'([^']+)'/", $this->search_string, $matches, PREG_SET_ORDER);
        $i = 0;
        foreach($matches as $match)
        {
            $sql .= ' '.SEARCH_ENGINE_TBL.'keywords.name LIKE "%s"';
            if($i < (count($matches) - 1))
            {
                $sql .= ' AND';
            }
            $i++;
            $match[0] = str_replace("'", '', $match[0]);
            array_push($this->params, $match[0]);
        }
        $this->search_query_string .= $sql;
        //$this->search_get_results();
    }

    function search_and_query($query)
    {
        $regex = strtoupper($this->type);
        $str = explode($regex, $query);
        if($str[0] == $query)
        {
            $str = explode($this->type, $query);
        }
        //var_dump($this->type);
        $i = 0;

        foreach($str as $param)
        {
            $param = trim($param);
            $sql .= ' '.SEARCH_ENGINE_TBL.'keywords.name LIKE %s';
            if($i < (count($str) - 1))
            {
                $sql .= ' AND';
            }
            $i++;
            array_push($this->params, '%'.$param.'%');
        }
        $this->query_string .= $sql;
        //$this->search_get_results();
    }

    function search_or_query($query)
    {
        $regex = strtoupper($this->type);
        $str = explode($regex, $query);
        $i = 0;
        $sql = 'SELECT
                                SQL_CALC_FOUND_ROWS
                    '.SEARCH_ENGINE_TBL.'links.*,
                    '.SEARCH_ENGINE_TBL.'index.weight
                FROM
                    '.SEARCH_ENGINE_TBL.'index
                LEFT JOIN
                    '.SEARCH_ENGINE_TBL.'links
                ON
                    '.SEARCH_ENGINE_TBL.'index.link = '.SEARCH_ENGINE_TBL.'links.id
                LEFT JOIN
                    '.SEARCH_ENGINE_TBL.'keywords
                ON
                    '.SEARCH_ENGINE_TBL.'index.keyword = '.SEARCH_ENGINE_TBL.'keywords.id
                WHERE (';
        foreach($str as $param)
        {
            $param = trim($param);
            $sql .= ' '.SEARCH_ENGINE_TBL.'keywords.name LIKE %s';
            if($i < (count($str) - 1))
            {
                $sql .= ' OR';
            }
            array_push($this->params, '%'.$param.'%');
            $i++;
        }

		$this->query_string = $sql.') AND '.$this->site_string;
		//DB Edit
        //$this->search_get_results();
    }
    function search_negation_query($query)
    {
        $negation = preg_match_all('/-[A-Za-z0-9]{1,}|-[A-Za-z0-9]{1.}[[:space:]]/', $query, $matches);
        $i = 0;
        foreach($matches as $match)
        {
            foreach($match as $k => $v)
            {
                $query = str_replace($v, '', $query);
                $v = str_replace('-', '', $v);

                $sql .= ' '.SEARCH_ENGINE_TBL.'keywords.name NOT LIKE %s';
                if($i < (count($match) - 1))
                {
                    $sql .= ' AND';
                }
                array_push($this->params, '%'.$v.'%');
                $i++;
            }
        }
        $query = trim($query);
        if(!empty($query))
        {
            $sql .= ' AND '.SEARCH_ENGINE_TBL.'keywords.name LIKE %s';
            array_push($this->params, '%'.$query.'%');
        }
        $this->query_string .= $sql;
        //$this->search_get_results();
    }
    function search_build_query($query_string)
    {
        $this->search_string = $query_string;/*
        if(preg_match('/"([^"]+)"/', $query_string) || preg_match("/'([^']+)'/", $query_string))
        {
            $this->type = 'exact';
            $this->search_exact_query($query_string);
        }
        elseif(preg_match('/and/i', $query_string, $match))
        {
            $this->type = $match[0];
            $this->search_and_query($query_string);
        }
        elseif(preg_match('/or/i', $query_string, $match))
        {
            $this->type = $match[0];
            $this->search_or_query($query_string);
        }
        elseif(preg_match('/-[A-Za-z0-9]{1,}|-[A-Za-z0-9]{1.}[[:space:]]/', $query_string))
        {
            $this->type = 'negation';
            $this->search_negation_query($query_string);
        }
        else
        {*/
            return $this->search_get_results($query_string);
        //}
        //return $this->search_get_results();
    }
    function search_get_results($query='')
    {
        global $wpdb;
        $limit = '';

        if (0 <= $this->results_per_page)
        {
            $limit = 'LIMIT ' . ($this->results_per_page * ($this->page - 1)) . ',' . $this->results_per_page;
        }
        elseif (false !== strpos($this->results_per_page, ','))
        {
            // Custom offset
            $limit = 'LIMIT ' . $this->results_per_page;
        }
        else{
        	//empty
        }
        if(!empty($query))
        {
            $terms = explode(' ',$query);
            $this->query_string .= ' (';
            $sql = array();
            foreach($terms as $term)
            {
                $sql[] .=  SEARCH_ENGINE_TBL.'keywords.name LIKE %s';
                array_push($this->params, '%'.$term.'%');
            }
            $this->query_string .= implode(' OR ',$sql).') ';
        }
        else{
        	//empty
        }
        if($this->type != 'or')
        {
            $this->query_string .= ' AND
                                        '.SEARCH_ENGINE_TBL.'keywords.id = '.SEARCH_ENGINE_TBL.'index.keyword
                                     AND
                                        '.SEARCH_ENGINE_TBL.'index.link = '.SEARCH_ENGINE_TBL.'links.id';

        }
        else{
        	//empty
        }

		//ALWAYS THE STRING - EVERYTHING BEFORE IRRELEVANT
        $this->query_string .= ' GROUP BY '.SEARCH_ENGINE_TBL.'links.url ORDER BY '.SEARCH_ENGINE_TBL.'index.weight DESC '.$limit;
        //DB EDIT
        //with "about" all weights are 55
        $this->results = $wpdb->get_results($wpdb->prepare($this->query_string, $this->params));
//        print_r($this->results);
//        exit;
        $total = @current($wpdb->get_col('SELECT FOUND_ROWS()'));
        if(is_array($total))
            $this->total_results = $total['FOUND_ROWS()'];
        else
            $this->total_results = $total;
        return $this->results;
    }
    function search_do_excerpt ($content,$limit=300)
    {
        $terms = explode(' ',$this->search_string);
        $terms = array_filter($terms);
        $excerpt_length = $limit;
        $excerpting = false;
        if($excerpt_length<strlen($content))
            $excerpting = true;
        $excerpt = "";

        $start = false;
        foreach ($terms as $term) {
            if (function_exists('mb_stripos')) {
                $pos = ("" == $content) ? false : mb_stripos($content, $term);
            }
            else {
                $pos = mb_strpos($content, $term);
                if (false === $pos) {
                    $titlecased = mb_strtoupper(mb_substr($term, 0, 1)) . mb_substr($term, 1);
                    $pos = mb_strpos($content, $titlecased);
                    if (false === $pos) {
                        $pos = mb_strpos($content, mb_strtoupper($term));
                    }
                }
            }

            if (false !== $pos) {
                if ($pos + strlen($term) < $excerpt_length) {
                    $excerpt = mb_substr($content, 0, $excerpt_length);
                    $start = true;
                    break;
                }
                else {
                    $half = floor($excerpt_length/2);
                    $pos = $pos - $half;
                    $excerpt = mb_substr($content, $pos, $excerpt_length);
                    break;
                }
            }
        }

        if ("" == $excerpt) {
            $excerpt = mb_substr($content, 0, $excerpt_length);
            $start = true;
        }
        $excerpt = $this->highlight_terms($excerpt, $terms);

        if (!$start&&$excerpting)
            $excerpt = "..." . $excerpt;

        if($excerpting)
            $excerpt = $excerpt . "...";

        return $excerpt;
    }
    function highlight_terms ($excerpt, $terms)
    {
        $start_emp = "<strong>";
        $end_emp = "</strong>";

        $start_emp_token = "*[/";
        $end_emp_token = "\]*";
        mb_internal_encoding("UTF-8");

        foreach ($terms as $term)
        {
            $pos = 0;
            $low_term = mb_strtolower($term);
            $low_excerpt = mb_strtolower($excerpt);
            while ($pos !== false)
            {
                $pos = mb_strpos($low_excerpt, $low_term, $pos);
                if ($pos !== false)
                {
                    $excerpt = mb_substr($excerpt, 0, $pos)
                             . $start_emp_token
                             . mb_substr($excerpt, $pos, mb_strlen($term))
                             . $end_emp_token
                             . mb_substr($excerpt, $pos + mb_strlen($term));
                    $low_excerpt = mb_strtolower($excerpt);
                    $pos = $pos + mb_strlen($start_emp_token) + mb_strlen($end_emp_token);
                }
            }
        }

        $excerpt = str_replace($start_emp_token, $start_emp, $excerpt);
        $excerpt = str_replace($end_emp_token, $end_emp, $excerpt);
        $excerpt = str_replace($end_emp . $start_emp, "", $excerpt);

        return $excerpt;
    }
}