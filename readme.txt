=== Search Engine ===
Contributors: sc0ttkclark
Donate link: http://www.scottkclark.com/
Tags: search engine, spider, index, search, google, buddypress, full search, wordpress search
Requires at least: 2.9
Tested up to: 3.0
Stable tag: 0.4.7

THIS IS A BETA VERSION - Currently in development - A search engine for WordPress that indexes ALL of your site and provides comprehensive search.

== Description ==

**THIS IS A BETA VERSION - Currently in development**

**OFFICIAL SUPPORT** - Search Engine - Support Forums: http://www.scottkclark.com/forums/search-engine/

This is a Search Engine for WordPress that indexes pages on a site and provides comprehensive search functionality.

All you do is install the plugin, and index your site. You can setup cronjobs to reindex hourly, daily, or weekly on your Server - or do it manually on an as needed basis. Indexing covers Full Pages as the visitor sees them.

So... that means Sidebars, Widgets, Pages, Posts, Custom Post Types, Pod Pages, Plugin Generated Content, Title tags, Meta tags, Template code, and anything else your visitor can see anywhere on your site is indexed and searchable.

== Frequently Asked Questions ==

What if my site or parts of my site is password protected? Currently, only htaccess password protected pages are supported, I'm currently working towards supporting WordPress User Authenticated indexing (depending what user group it's set to index as).

== Changelog ==

= 0.5.0 =
* AND / OR / "Exact Phrase" multi-combination Support
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

= 0.5.0 =
* AND / OR / "Exact Phrase" multi-combination Support
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

Search Engine - Support Forums: http://www.scottkclark.com/forums/search-engine/

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
* Admin.Class.php - A class for plugins to manage data using the WordPress UI appearance

= Spider =
* Link Extraction - Get links from pages from &lt;a&gt;,  &lt;area&gt;, &lt;iframe&gt;, and &lt;frame&gt; tags
* Link Redirection - Follows all 301 / 302 Redirects
* Link Validation - Check for Invalid URLs / Content-Types
* robots.txt Protocol Support - Follows rules from robots.txt at root of domain
* nofollow / noindex Support - Follows specific rules from Robots Meta and &lt;a&gt; tags
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

= 0.5.1 =
* Search Settings - Setup/control multiple searches on your site
* Negative Keyword Matching using -word Format
* View Search Logs - View queries recently typed, how long it took them to process, and how many results were returned
* Cronjob Groups - Ability to run multiple templates at a time based on Cronjob Group in cronjob.php
* Integration with wp_cron - Option to enable a template to rerun on it's own - like magic!
* View Index Logs - View statistics from indexing like Links Not Found, Links Redirected, etc
* Inlinks Tracking - Weight should reflect more heavily for pages with more internal links pointing to them