<?php
include_once('simple_html_dom.php');


echo file_get_html('http://andyweigel.com/blog/wordpress-plugins/6-must-have-plugins-for-your-wordpress-installs/30')->plaintext;
?>