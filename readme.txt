=== Search Engine ===
Contributors: sc0ttkclark
Donate link: http://scottkclark.com/
Tags: search engine, spider, index, search, google, buddypress, full search, wordpress search
Requires at least: 2.9
Tested up to: 3.1
Stable tag: 0.5.7.1

THIS IS A BETA VERSION - Currently in development - A search engine for WordPress that indexes ALL of your site and provides comprehensive search.

== Description ==

**THIS IS A BETA VERSION - Currently in development**

**OFFICIAL SUPPORT** - Search Engine - Support Forums: http://scottkclark.com/forums/search-engine/

This is a Search Engine for WordPress that indexes pages on a site and provides comprehensive search functionality.

All you do is install the plugin, and index your site. You can setup cronjobs to reindex hourly, daily, or weekly on your Server - or do it manually on an as needed basis. Indexing covers Full Pages as the visitor sees them.

So... that means Sidebars, Widgets, Pages, Posts, Custom Post Types, Pod Pages, Plugin Generated Content, Title tags, Meta tags, Template code, and anything else your visitor can see anywhere on your site is indexed and searchable.

This plugin can also index and search content that exists outside of your WordPress site, such as on other domains / sub-domains or other parts of your site external to WordPress. The search feature is currently limited to a single 'site' (domain/sub-domain) per search, so you can't search across multiple sites **yet**.

== Frequently Asked Questions ==

= What if my site or parts of my site are password protected or are only available to logged in users? =
Currently, only .htaccess password protected pages are supported, I'm currently working towards supporting WordPress User Authenticated indexing (depending what user group it's set to index as).

= How can I include or exclude content on my page from being indexed? =
* Exclude content within section(s) of your site by adding the class noindex to the element tag(s)
* Explicitly ONLY include content within section(s) of your site by adding the class onlyindex to the element tag(s)

= How can I allow / disallow URLs from being indexed? =
* Disallow the URI(s) in your robots.txt file
* Disallow the URI(s) with a meta robots tag w/ nofollow
* Disallow the link(s) with a rel attribute of nofollow
* Disallow many links within section(s) of your site by adding the class nofollow to the element tag(s)

== Changelog ==

= 0.5.7.1 =
* Bug fix for default site search (wasn't picking up current site if default site hadn't been set)

= 0.5.7 =
* Added setting (in Search Engine >> Settings) to set default site - This is used for Object Monitoring and the Site Search integration) - Leave blank for current host / scheme to be used, otherwise it'll default to the site of your choice
* Bug fix for output during Object Monitoring
* Bug fix for Index Template management screen on 'Site' column - wasn't showing 'scheme' or 'directory'

= 0.5.5 =
* Added Object Monitoring code, this is basic right now - Just WP Posts / Pages / CPT - Pods support coming when Pods 2.0 is released.
* Lots of bug fixes

= 0.5.3 =
* Now using wp_remote_request to simplify requests and maximize compatibility with multiple server / php configurations
* Fixed bug where the Spider doesn't detect it's finished (jQuery iframe issue)
* Fixed bug where the Spider goes into a loop depending on the URL if containing a hash #
* Fixed bug in detection of completion of Indexing (showed error in both cases)
* Minor css bug fixes

= 0.5.2 =
* Added option to Reset all data, and added ability to edit the Search Engine Token Key

= 0.5.1 =
* You can now index based off of a directory, when indexing a new site simply type in the path like a normal url at the end of a domain name and you'll start indexing from that page on (previously restricted to the root only)
* You can now use 'nofollow' as a class to exclude a set of URLs within an HTML tag, or 'noindex' in a class to exclude content on a page from indexing, or use 'onlyindex' in a class to ONLY include that content on the page for indexing (excludes all other data automatically): In all indexing cases, links will be pulled from the entire page even outside designated onlyindex/noindex sections -- The class check can be partial, so you can set your class to be noindexing and 'noindex' being a part of it will trigger that functionality during indexing
* XML Sitemaps - Download sitemaps containing links to every page of your site
* You can now continue an index if it did not complete, either via Reindexing based on the same template or from within Wizard (you will get a visual notice alerting you to any failed indexing)
* Added cross_scheme option, to allow switching between http and https during indexing (default restricts to the originating scheme)
* Bug fixes + features in Admin.class.php
* Bug fixes in indexing
* Bug fixes in search (encoding issues)

= 0.5.0 =
* View Search Logs - View queries recently typed, how long it took them to process, and how many results were returned
* Bug fixes + features in Admin.class.php
* Bug fixes in indexing
* Bug fixes in search

= 0.4.7 =
* Bug fixes in indexing
* Index Relevancy tweaks

= 0.4.6 =
* Better searching (more than one word works now)
* Search Term highlighting in Title / Description / URL
* CSS tweaks
* No longer looking for links in &lt;style&gt;, &lt;object&gt;, &lt;embed&gt;, &lt;applet&gt;, &lt;noscript&gt;, and &lt;noembed&gt; tags

= 0.4.5 =
* Bug fix for lmao

= 0.4.4 =
* preg_replace fix
* CSS tweaks

= 0.4.3 =
* Tweaks to CSS to make it work in other themes
* Added SEARCH_ENGINE_CUSTOM_CSS check to see if it's defined, if so - don't use the CSS provided by the plugin
* Added 'css' parameter in the shortcode, [search_engine sites='1,3,4' css='0'] which when set to 0, it will not output CSS
* Description excerpts on results
* Index exclusion of &lt;script&gt; content
* No longer looking for links in &lt;pre&gt; and &lt;code&gt; tags

= 0.4.2 =
* Bug fixes for Spider.class.php

= 0.4.1 =
* Bug fixes: Inclusion of SQL :-) and fix in Admin.class.php

= 0.4.0 =
* First official release to the public as a plugin

== Upgrade Notice ==

= 0.5.7.1 =
* Bug fix for default site search (wasn't picking up current site if default site hadn't been set)

= 0.5.7 =
* Added setting (in Search Engine >> Settings) to set default site - This is used for Object Monitoring and the Site Search integration) - Leave blank for current host / scheme to be used, otherwise it'll default to the site of your choice
* Bug fix for output during Object Monitoring
* Bug fix for Index Template management screen on 'Site' column - wasn't showing 'scheme' or 'directory'

= 0.5.5 =
* Added Object Monitoring code, this is basic right now - Just WP Posts / Pages / CPT - Pods support coming when Pods 2.0 is released.
* Lots of bug fixes

= 0.5.3 =
* Now using wp_remote_request to simplify requests and maximize compatibility with multiple server / php configurations
* Fixed bug where the Spider doesn't detect it's finished (jQuery iframe issue)
* Fixed bug where the Spider goes into a loop depending on the URL if containing a hash #
* Fixed bug in detection of completion of Indexing (showed error in both cases)
* Minor css bug fixes

= 0.5.2 =
* Added option to Reset all data, and added ability to edit the Search Engine Token Key

= 0.5.1 =
* You can now index based off of a directory, when indexing a new site simply type in the path like a normal url at the end of a domain name and you'll start indexing from that page on (previously restricted to the root only)
* You can now use 'nofollow' as a class to exclude a set of URLs within an HTML tag, or 'noindex' in a class to exclude content on a page from indexing, or use 'onlyindex' in a class to ONLY include that content on the page for indexing (excludes all other data automatically): In all indexing cases, links will be pulled from the entire page even outside designated onlyindex/noindex sections -- The class check can be partial, so you can set your class to be noindexing and 'noindex' being a part of it will trigger that functionality during indexing
* XML Sitemaps - Download sitemaps containing links to every page of your site
* You can now continue an index if it did not complete, either via Reindexing based on the same template or from within Wizard (you will get a visual notice alerting you to any failed indexing)
* Added cross_scheme option, to allow switching between http and https during indexing (default restricts to the originating scheme)
* Bug fixes + features in Admin.class.php
* Bug fixes in indexing
* Bug fixes in search (encoding issues)

= 0.5.0 =
* View Search Logs - View queries recently typed, how long it took them to process, and how many results were returned
* Bug fixes + features in Admin.class.php
* Bug fixes in indexing
* Bug fixes in search

= 0.4.7 =
* Bug fixes in indexing
* Index Relevancy tweaks

= 0.4.6 =
* Better searching (more than one word works now)
* Search Term highlighting in Title / Description / URL
* CSS tweaks
* No longer looking for links in &lt;style&gt;, &lt;object&gt;, &lt;embed&gt;, &lt;applet&gt;, &lt;noscript&gt;, and &lt;noembed&gt; tags

= 0.4.5 =
* Bug fix for lmao

= 0.4.4 =
* preg_replace fix
* CSS tweaks

= 0.4.3 =
* Tweaks to CSS to make it work in other themes
* Added SEARCH_ENGINE_CUSTOM_CSS check to see if it's defined, if so - don't use the CSS provided by the plugin
* Added 'css' parameter in the shortcode, [search_engine sites='1,3,4' css='0'] which when set to 0, it will not output CSS
* Description excerpts on results
* Index exclusion of &lt;script&gt; content
* No longer looking for links in &lt;pre&gt; and &lt;code&gt; tags

= 0.4.2 =
Bug fixes for Spider.class.php

= 0.4.1 =
You probably don't see this plugin working at all, upgrade and I promise it will work :-)

= 0.4.0 =
You aren't using the real plugin, upgrade and you enjoy what you originally downloaded this for!

== Installation ==

1. Unpack the entire contents of this plugin zip file into your `wp-content/plugins/` folder locally
1. Upload to your site
1. Navigate to `wp-admin/plugins.php` on your site (your WP plugin page)
1. Activate this plugin

OR you can just install it with WordPress by going to Plugins >> Add New >> and type this plugin's name

== Official Support ==

Search Engine - Support Forums: http://scottkclark.com/forums/search-engine/

== About the Plugin Author ==

Scott Kingsley Clark from SKC Development -- Scott specializes in WordPress and Pods CMS Framework development using PHP, MySQL, and AJAX. Scott is also a developer on the Pods CMS Framework plugin

== Other Plugin Contributors ==

* Greg Dean - PHP Development for Search.class.php
* Chris Jean from iThemes - PHP Development for Displaying Results on Search Pages
* Randy Jansen - HTML / CSS Development for Wizard UI and Search Results Template
* cdharrison - Icon Consulting
* Andy Weigel - PHP Consulting

== Awesome Plugin Testers ==

* Daniel Koskinen
* Benjamin Favre

These testers are especially helping make this Beta plugin get to 1.0! If you are interested in being listed here, test the heck out of this plugin and let me know if you've found anything that doesn't work right!

== Features ==

= Administration =
* Easy Indexing Wizard - Create Templates and Index Existing / New Sites
* Index Templates - Reindex Sites via the Wizard or via cronjob.php
* XML Sitemaps - Reindex Sites via the Wizard or via cronjob.php
* View Search Logs - View queries recently typed, how long it took them to process, and how many results were returned
* Admin.Class.php - A class for plugins to manage data using the WordPress UI appearance

= Spider =
* Link Extraction - Get links from pages from &lt;a&gt;,  &lt;area&gt;, &lt;iframe&gt;, and &lt;frame&gt; tags
* Link Redirection - Follows all 301 / 302 Redirects
* Link Validation - Check for Invalid URLs / Content-Types
* robots.txt Protocol Support - Follows rules from robots.txt at root of domain
* nofollow / noindex Support - Follows specific rules from Robots Meta, &lt;a&gt;, and other elements (class="nofollow") tags
* Depth Restricting - Restrict how deep spidering goes
* URL Words Whitelist / Blacklist - Include or Exclude URLs from being spidered based on words
* .htaccess Password Protection Support - Optional Username / Password can be passed to Spider to access restricted areas
* cronjob.php - Script to use when setting up Server Cronjob settings - Can Spider based on Site or Template

= Index =
* Keyword Extraction - Get keywords from pages from &lt;title&gt;,  &lt;meta name="description"&gt;, &lt;meta name="keywords"&gt;, and &lt;body&gt; tags
* Keyword Blacklist - Exclude Keywords from being Indexed
* Index Weight Assignment - Based on how often keywords are used, and in which places they are found, a weight is given to a URL to create relevancy

= Search =
* AND / OR / "Exact Phrase" multi-combination Support
* OR Support
* Shortcode can be used in WP Pages to display form and search specific site(s) (by site ID) - [search_engine sites="1,4,5"]
* search_engine_form() to display the Search Engine form anywhere on your site - Note, form points to the root of your site for search like normal WP search
* search_engine_content() can be placed in a template to call the Search Engine form / results

== Roadmap ==

= 0.6.0 =
* AND / OR / "Exact Phrase" multi-combination Support
* Search Settings - Setup/control multiple searches on your site
* Negative Keyword Matching using -word Format
* Cronjob Groups - Ability to run multiple templates at a time based on Cronjob Group in cronjob.php
* Integration with wp_cron - Option to enable a template to rerun on it's own - like magic!
* View Index Logs - View statistics from indexing like Links Not Found, Links Redirected, etc
* Inlinks Tracking - Weight should reflect more heavily for pages with more internal links pointing to them