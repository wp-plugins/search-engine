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

    function __construct($site_ids=false,$template_ids=false)
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
        if(!empty($template_ids)&&is_array($template_ids))
        {
            $this->template_ids = $template_ids;
            $sql = array();
            foreach($template_ids as $template_id)
            {
                $sql[] = $wpdb->prepare('('.SEARCH_ENGINE_TBL.'links.site='.SEARCH_ENGINE_TBL.'templates.site AND '.SEARCH_ENGINE_TBL.'templates.id=%d)',array($template_id));
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
        $search_flag = 0;//OR
		$search_string_array = explode(" or ", strtolower($query_string));
		if(sizeof($search_string_array) == 1){ //this presumes at least 1 OR exists in the string as an independent word		
			$search_flag = 1;//AND
			$search_string_array = explode(" and ", strtolower($query_string));
			if(sizeof($search_string_array) == 1){
				$this->search_string = $query_string;
				$search_string_array = explode(" ", strtolower($query_string));
				$search_flag = 0;//JUST MOVE ON
			}
		}
		
		if($search_flag == 1){
			return $this->combine_and_results($search_string_array);
		}elseif($search_flag == 0){
			return $this->combine_or_results($search_string_array);
		}else{ //OR should be the catch - sort of
			//We should never actually get here...
			return $this->combine_or_results($search_string_array);
    	}
		
		//For this search engine to handle complex queries, like "THIS AND THAT OR THEM", then regexp needs to be used.
		//For now, the queries are assumed to be one or the other type of search (OR or AND).
		/*
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
        }*/
        //return $this->search_get_results();
    }
    function search_get_results($query='')
    {
        global $wpdb;
        $limit = '';
		// We need a local string for the query.
		$local_query_string = "";

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
            $terms = explode(' ',$query);
			//$this->query_string .= ' (';
            /*
			$local_query_string = ' (';
            $sql = array();
            foreach($terms as $term)
            {
                $sql[] .=  SEARCH_ENGINE_TBL.'keywords.name LIKE %s';
                //array_push($this->params, '%'.$term.'%');
            }
			//$this->query_string .= implode(' OR ',$sql).') ';
			$local_query_string .= implode(' AND ',$sql).') OR ';
            $terms = explode(' ',$query);
			//$this->query_string .= ' (';
            */
			$local_query_string = ' (';
            $sql = array();
            foreach($terms as $term)
            {
                $sql[] .=  SEARCH_ENGINE_TBL.'keywords.name LIKE %s';
                //array_push($this->params, '%'.$term.'%');
            }
			//$this->query_string .= implode(' OR ',$sql).') ';
			$local_query_string .= implode(' OR ',$sql).') ';
        }
        if($this->type != 'or')
        {
            //$this->query_string
            $local_query_string .= ' AND
                                        '.SEARCH_ENGINE_TBL.'keywords.id = '.SEARCH_ENGINE_TBL.'index.keyword
                                     AND
                                        '.SEARCH_ENGINE_TBL.'index.link = '.SEARCH_ENGINE_TBL.'links.id';

        }

        //$this->query_string .= ' GROUP BY '.SEARCH_ENGINE_TBL.'links.url ORDER BY '.SEARCH_ENGINE_TBL.'index.weight DESC '.$limit;
        $local_query_string .= ' GROUP BY '.SEARCH_ENGINE_TBL.'links.url ORDER BY '.SEARCH_ENGINE_TBL.'index.weight DESC '.$limit;
		//$this->results = $wpdb->get_results($wpdb->prepare($this->query_string.$local_query_string, $this->params));	
		$term = '%' . $term . '%';
        $this->results = $wpdb->get_results($wpdb->prepare($this->query_string.$local_query_string, $term));
        $total = @current($wpdb->get_col('SELECT FOUND_ROWS()'));
        if(is_array($total))
            $this->total_results = $total['FOUND_ROWS()'];
        else
            $this->total_results = $total;
        return $this->results;
    }
        
    function combine_or_results($input_array){
    	$OR_results_array = array();
    	$OR_counter_arary = array();
    	foreach($input_array as $element){
			$search_result_set = $this->search_get_results($element);
			if(empty($OR_results_array)){
				//These are already sorted by weight.
				$OR_results_array = $search_result_set;
				if(sizeof($OR_results_array) > 0){
					$OR_counter_array = array_fill(0,sizeof($OR_results_array),1);
				}
				//I can use the same results array, to save space and speed, by adding a ['frequency'] dimension
			}else{
				foreach($search_result_set as $search_item){	
			    	$found=0;					
					for($i=0; $i<sizeof($OR_results_array); $i++){
						if($search_item->id == $OR_results_array[$i]->id){
							$OR_results_array[$i]->weight = $OR_results_array[$i]->weight + $search_item->weight;
							$OR_counter_array[$i] = $OR_counter_array[$i] + 1;
							//If we have a match, break out of the for loop above.
							$found=1;
							break;
						}
					}
					if($found == 0){
						array_push($OR_results_array,$search_item);
						array_push($OR_counter_array,1);
					}
				}
			}
    	}
    	//return $this->sort_by_weight($OR_results_array);
    	return $this->sort_by_weight_and_frequency($OR_results_array,$OR_counter_array);
    }
    function combine_and_results($input_array){
    	$AND_results_array = array();
		for($i=0; $i<(sizeof($input_array)-1); $i++){
			$first_word_results = $this->search_get_results($input_array[$i]);
			for($j=$i+1; $j<sizeof($input_array); $j++){			
				$second_word_results = $this->search_get_results($input_array[$j]);
				for($k=0; $k<sizeof($first_word_results); $k++){
					for($m=0; $m<sizeof($second_word_results); $m++){
						if($first_word_results[$k]->id == $second_word_results[$m]->id){
							$first_word_results[$k]->weight = $first_word_results[$k]->weight + $second_word_results[$m]->weight;
							array_push($AND_results_array,$first_word_results[$k]);
							break;
						}
					}					
				}
			}
		}	
    	return $this->sort_by_weight($AND_results_array);    	
    }

	//DO NOT GET RID OF THIS YET.
    /*function sort_by_weight($results_array){
    	//Pull out the indices of the weights...
    	$hollow_array = array();
    	$results_return_array = array();
    	for($i=0; $i<sizeof($results_array); $i++){
    		$hollow_array[$i] = $results_array[$i]->weight;
    	}
    	arsort($hollow_array);
    	$i = 0;
		foreach($hollow_array as $key => $val){
			//$key is the location (i) in the original results array.
			$results_return_array[$i++] = $results_array[$key];
		}	
		return $results_return_array;
    }*/
    
    function sort_by_weight($results_array){
    	$frequency_array = array();
    	if(sizeof($results_array)>0){
	    	$frequency_array = array_fill(0,sizeof($results_array),1);    	
    	}
    	return $this->sort_by_weight_and_frequency($results_array,$frequency_array);
    }
    
    function sort_by_weight_and_frequency($results_array,$frequency_array){
    	$id_array = array();
    	$weight_array = array();
    	for($i=0; $i<sizeof($results_array); $i++){
    		$weight_array[$i] = $results_array[$i]->weight;
			$id_array[$i] = $results_array[$i]->id;	
    	}
    	/* //UNCOMMENT THIS BLOCK TO SEE ORIGINAL WEIGHTS, FREQUENCIES AND IDS
    	print_r($id_array);
    	print('<br><br>');
    	print_r($weight_array);
    	print('<br><br>');
    	print_r($frequency_array);
    	print('<br><br>');*/
    	if(sizeof($frequency_array) > 0){
	    	array_multisort($frequency_array, SORT_DESC, $weight_array, SORT_DESC, $results_array);    	    	
    	}else{
    		array_multisort($weight_array, SORT_DESC, $results_array);    	    		
    	}
		/* //UNCOMMENT THIS BLOCK TO SEE THE NEW ID ORDER WITH CORRESPONDING WEIGHTS
		print('ids:<br>');
    	for($i=0; $i<sizeof($results_array); $i++){
    		print($results_array[$i]->id . ' ');
    	}
		print('<br>weights:<br>');
    	for($i=0; $i<sizeof($results_array); $i++){
    		print($results_array[$i]->weight . ' ');
    	}    	
    	exit;*/
    	return $results_array;
    }
    
    function search_do_excerpt ($content,$limit=300,$encode=true)
    {
        mb_internal_encoding(get_bloginfo('charset'));
        $terms = explode(' ',html_entity_decode($this->search_string,ENT_COMPAT,get_bloginfo('charset')));
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
        if(false!==$encode)
            $excerpt = htmlentities($excerpt,ENT_COMPAT,get_bloginfo('charset'));
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
        mb_internal_encoding(get_bloginfo('charset'));
        $start_emp = "<strong>";
        $end_emp = "</strong>";
        $start_emp_token = "*[/";
        $end_emp_token = "\]*";
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