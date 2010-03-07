var t;
function tothetop()
{
    jQuery('#scroller').stop().scrollTo('100%','0%', { axis:'y' });
    t = setTimeout("tothetop()",1000);
}
function iframedone()
{
    jQuery('#scroller').stop().scrollTo('100%','0%', { axis:'y' });
    clearTimeout(t);
    jQuery('.loader').addClass('complete');
    jQuery('.loader').html('<p><strong>Indexing is complete!</strong> - <a href="admin.php?page=search-engine">Index another site &raquo;</a></p>');
    jQuery('#startstop').remove();
}