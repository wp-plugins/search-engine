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
    jQuery('#startstop').remove();
    if(jQuery('#scroller').html().search("Spidering Complete")>-1)
    {
        jQuery('.loader').addClass('complete');
        jQuery('.loader').html('<p><strong>Indexing is complete!</strong> - <a href="admin.php?page=search-engine">Index another site &raquo;</a></p>');
        return false;
    }
    else
    {
        jQuery('.loader').addClass('error');
        jQuery('.loader').html('<p><strong>Indexing is not complete, your server may have timed out</strong> - <a href="'+document.location+'">Continue the Indexing &raquo;</a></p>');
    }
}