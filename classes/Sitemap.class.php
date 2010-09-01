<?php
class Search_Engine_Sitemap
{
    var $changefreq = 'weekly'; //always/hourly/daily/weekly/monthly/yearly/never
    var $changefreq_sub = 'monthly'; //always/hourly/daily/weekly/monthly/yearly/never
    var $priority = true;
    var $links = array();
    var $site = 0;

    function get_links ()
    {
        global $wpdb;
        $max_min = $wpdb->get_row("SELECT MAX(level) as the_max,MIN(level) as the_min FROM ".SEARCH_ENGINE_TBL."links WHERE site=$this->site",ARRAY_A);
        $min = $max_min['the_min'];
        $max = $max_min['the_max'];
        $results = $wpdb->get_results("SELECT * FROM ".SEARCH_ENGINE_TBL."links WHERE site=$this->site ORDER BY level,url",ARRAY_A);
        $minimum = ($max<10)?'0.1':'0.0';
        foreach($results as $result)
        {
            $priority = false;
            if($this->priority)
            {
                if($result['level']==0)
                    $priority = '1.0';
                elseif($result['level']==$max)
                    $priority = $minimum;
                else
                    $priority = number_format(1-($result['level']/$max),1,'.','');
            }
            if(0<$result['level']&&$priority=='1.0')
                $priority = '0.9';
            $this->links[] = array('url'=>$result['url'],'lastmod'=>strtotime($result['lastmod']),'changefreq'=>($result['level']==0?$this->changefreq:$this->changefreq_sub),'priority'=>$priority);
        }
    }
    function build_xml_sitemap ($site=0,$output=false)
    {
        if($site<1)
            return false;
        $this->site = $site;
        $this->get_links();
        if($output===false)
            header('Content-Type: text/xml');
        else
            ob_start();
        echo '<'.'?xml version="1.0" encoding="'.get_bloginfo('charset').'"?'.'>'."\n";
?>
    <urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
             xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd"
             xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php
        if(!empty($this->links)) foreach($this->links as $link)
            $this->build_xml_sitemap_item($link);
?>
    </urlset>
<?php
        if($output!==false)
            return ob_get_clean();
    }
    function build_xml_sitemap_item ($link)
    {
        $link['url'] = str_replace('&','&amp;',$link['url']);
        $link['url'] = str_replace("'",'&apos;',$link['url']);
        $link['url'] = str_replace('"','&quot;',$link['url']);
        $link['url'] = str_replace('>','&gt;',$link['url']);
        $link['url'] = str_replace('<','&lt;',$link['url']);
?>
        <url>
            <loc><?php echo $link['url']; ?></loc>
            <lastmod><?php echo date('Y-m-d',$link['lastmod']); ?></lastmod>
<?php if($link['changefreq']!==false){ ?>
            <changefreq><?php echo $link['changefreq']; ?></changefreq>
<?php } if($link['priority']!==false){ ?>
            <priority><?php echo $link['priority']; ?></priority>
<?php } ?>
        </url>
<?php
    }
}