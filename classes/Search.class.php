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
        $this->search_string = $query;
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
        {
            return $this->search_get_results($query_string);
        }
        return $this->search_get_results();
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
        if(!empty($query))
        {
            $this->query_string .= ' '.SEARCH_ENGINE_TBL.'keywords.name LIKE %s ';
            array_push($this->params, '%'.$query.'%');
        }
        if($this->type != 'or')
        {
            $this->query_string .= ' AND
                                        '.SEARCH_ENGINE_TBL.'keywords.id = '.SEARCH_ENGINE_TBL.'index.keyword
                                     AND
                                        '.SEARCH_ENGINE_TBL.'index.link = '.SEARCH_ENGINE_TBL.'links.id';

        }

        $this->query_string .= ' GROUP BY '.SEARCH_ENGINE_TBL.'links.url ORDER BY '.SEARCH_ENGINE_TBL.'index.weight DESC '.$limit;
        $this->results = $wpdb->get_results($wpdb->prepare($this->query_string, $this->params));
        $total = @current($wpdb->get_col('SELECT FOUND_ROWS()'));
        if(is_array($total))
            $this->total_results = $total['FOUND_ROWS()'];
        else
            $this->total_results = $total;
        return $this->results;
    }
}