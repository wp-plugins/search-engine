<?php
global $wpdb;
if(!is_object($wpdb))
{
    ob_start();
    require_once('../../../wp-load.php');
    ob_end_clean();
}
$check = get_option('search_engine_token');
if(!isset($_GET['token'])||$_GET['token']!=$check)
    die('Invalid Token');

require_once "classes/Sitemap.class.php";

if(isset($_GET['site_id'])&&0<$_GET['site_id'])
{
    // required for IE, otherwise Content-disposition is ignored
    if(ini_get('zlib.output_compression'))
        ini_set('zlib.output_compression','Off');
    header("Pragma: public"); // required
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: private",false); // required for certain browsers
    header("Content-Type: application/force-download");
    $sitemap = new Search_Engine_Sitemap();
    $contents = $sitemap->build_xml_sitemap($_GET['site_id'],true);
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    if(false===($credentials=request_filesystem_credentials($_SERVER['REQUEST_URI'],'',false,ABSPATH)))
    {
        die("<strong>Error:</strong> Your hosting configuration does not allow access to add files to your site.");
    }
    if(!WP_Filesystem($credentials,ABSPATH))
    {
        request_filesystem_credentials($_SERVER['REQUEST_URI'],'',true,ABSPATH); //Failed to connect, Error and request again
        die("<strong>Error:</strong> Your hosting configuration does not allow access to add files to your site.");
    }
    global $wp_filesystem;
    $dir = dirname(SEARCH_ENGINE_XML_SITEMAPS_DIR);
    if(!file_exists(SEARCH_ENGINE_XML_SITEMAPS_DIR))
    {
        if(!$wp_filesystem->is_writable($dir)||!($dir = $wp_filesystem->mkdir(SEARCH_ENGINE_XML_SITEMAPS_DIR)))
        {
            die("<strong>Error:</strong> Your export directory (<strong>".SEARCH_ENGINE_XML_SITEMAPS_DIR."</strong>) did not exist and couldn&#8217;t be created by the web server. Check the directory permissions and try again.");
        }
    }
    if(!$wp_filesystem->is_writable(SEARCH_ENGINE_XML_SITEMAPS_DIR))
    {
        die("<strong>Error:</strong> Your export directory (<strong>".SEARCH_ENGINE_XML_SITEMAPS_DIR."</strong>) needs to be writable for this plugin to work. Double-check it and try again.");
    }
    $xml_file = SEARCH_ENGINE_XML_SITEMAPS_DIR.'/xml_sitemap_'.$sitemap->site.'.xml';
    $fp = fopen($xml_file,'a+');
    fwrite($fp,$contents);
    fclose($fp);
    if(!file_exists($xml_file))
        die('File not found.');
    header("Content-Disposition: attachment; filename=\"".basename($xml_file)."\";" );
    header("Content-Transfer-Encoding: binary");
    header("Content-Length: ".filesize($xml_file));
    flush();
    readfile("$xml_file");
    die();
}
else
{
    die('Error, please contact plugin developer at http://scottkclark.com/');
}