<?php
function get_tag_data( $data, $tags = false, $filters = false ) {
    $data = preg_replace( '/<!--.*?-->/ms', '', $data );
	$data_parser = new GetTagData();

	if ( is_file( $data ) || preg_match( '{^(?:http|ftp)://}', $data ) )
		@$data_parser->loadHTMLFile( $data );
	else
		@$data_parser->loadHTML( $data );

	$data = $data_parser->get_filtered_tags( $tags, $filters );
	return $data;
}

class GetTagData extends DOMDocument {
	function get_filtered_tags( $tags = false, $filters = false ) {
		// Filters remove tags from the results based on specific matching criteria
		$default_filters = array(
			'a'		=> array(
				'rel'	=> 'nofollow',
//				'fake'	=> true, // Using true causes the tag to be removed from the set if the attribute is present
			),
		);

		if ( false === $filters )
			$filters = $default_filters;


		$results = $this->get_tags( $tags );

		foreach ( (array) $filters as $tag => $attributes ) {
			foreach ( (array) $results[$tag] as $index => $result ) {
				foreach ( (array) $attributes as $attribute => $value ) {
					if ( true === $value )
						unset( $results[$tag][$index] );
					else if ( $value === $result[$attribute] )
						unset( $results[$tag][$index] );
				}
			}

			if ( empty( $results[$tag] ) )
				unset( $results[$tag] );
		}

		return $results;
	}

	function get_tags( $tags = false ) {
		// Only these tags will have their results returned
		$default_tags = array( 'a', 'area', 'frame', 'iframe' );

		if ( false === $tags )
			$tags = $default_tags;


		return $this->_get_tags_recursive( $tags );
	}

	function _get_tags_recursive( $tags, $results = array(), $node = null ) {
		if ( ! $this->hasChildNodes() )
			return $results;

		$node = ( is_null( $node ) ) ? $this->documentElement : $node;

		if ( $node->hasChildNodes() )
			foreach ( $node->childNodes as $child_node )
				$results = $this->_get_tags_recursive( $tags, $results, $child_node );

		if ( in_array( $node->nodeName, $tags ) ) {
			$attributes = array();

			if ( $node->hasAttributes() )
				foreach ( $node->attributes as $sAttrName => $oAttrNode ) {
                    $value = trim($oAttrNode->nodeValue);
                    if(!empty($value))
                        $attributes["{$oAttrNode->nodeName}"] = $value;
                }

            $doc = new DOMDocument();
              foreach ($node->childNodes as $child)
                  $doc->appendChild($doc->importNode($child, true));

            $html = trim($doc->saveHTML());
            if(!empty($html))
                $attributes['html'] = $html;

            $text = trim($node->nodeValue);
            if(!empty($text))
                $attributes['text'] = $text;

			$results[$node->nodeName][] = $attributes;
		}


		return $results;
	}
}