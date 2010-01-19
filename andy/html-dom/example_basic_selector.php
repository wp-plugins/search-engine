<?php
// example of how to use basic selector to retrieve HTML contents
include('simple_html_dom.php');

$base_url = 'http://andyweigel.com/blog/';
 
// get DOM from URL or file
$html = file_get_html('http://andyweigel.com/blog/wordpress-plugins/6-must-have-plugins-for-your-wordpress-installs/30');

?>
<h2>Only Internal Links</h2>
<h3>Base URL is <?php echo $base_url; ?></h3> 
<?php
// find internal link
foreach($html->find('a') as $e)  {
	$link = $e->href; 
	$link = urldecode($link);
	//echo "$link <br/>";
	$pos = strpos($link,$base_url);

    if ($pos === 0) {
		echo "$link<br/>";
	}

}



?>
<!--
<h2>All Links</h2>
<?php
// find all link
foreach($html->find('a') as $e) 
    echo $e->href . '<br>';

// find all image
?>
<hr />
<h2>All Images</h2>
<?php 
foreach($html->find('img') as $e)
    echo $e->src . '<br>';
/*
// find all image with full tag
foreach($html->find('img') as $e)
    echo $e->outertext . '<br>';

// find all div tags with id=gbar
foreach($html->find('div#gbar') as $e)
    echo $e->innertext . '<br>';

// find all span tags with class=gb1
foreach($html->find('span.gb1') as $e)
    echo $e->outertext . '<br>';

// find all td tags with attribite align=center
foreach($html->find('td[align=center]') as $e)
    echo $e->innertext . '<br>';*/
    
/*// extract text from table
echo $html->find('td[align="center"]', 1)->plaintext.'<br><hr>';*/

// extract text from HTML
?>
<hr />
<h2>Full HTML Scrape</h2> 
<?php 
echo $html->plaintext;
?>
-->