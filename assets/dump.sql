DROP TABLE IF EXISTS `wp_searchengine_cronjobs`;
CREATE TABLE `wp_searchengine_cronjobs` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `templates` longtext NOT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `wp_searchengine_groups`;
CREATE TABLE `wp_searchengine_groups` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created` datetime NOT NULL,
  `updated` datetime NOT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `wp_searchengine_index`;
CREATE TABLE `wp_searchengine_index` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `link` int(10) NOT NULL,
  `keyword` int(10) NOT NULL,
  `weight` int(10) NOT NULL,
  `site` int(10) NOT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `wp_searchengine_keywords`;
CREATE TABLE `wp_searchengine_keywords` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `wp_searchengine_links`;
CREATE TABLE `wp_searchengine_links` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site` int(10) NOT NULL,
  `url` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `fulltxt` longtext NOT NULL,
  `lastmod` datetime NOT NULL,
  `indexed` datetime NOT NULL,
  `updated` datetime NOT NULL,
  `size` varchar(255) NOT NULL,
  `md5_checksum` varchar(255) NOT NULL,
  `level` int(10) NOT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `wp_searchengine_log`;
CREATE TABLE `wp_searchengine_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `query` varchar(255) NOT NULL,
  `time` datetime NOT NULL,
  `elapsed` int(10) NOT NULL,
  `results` int(10) NOT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `wp_searchengine_queue`;
CREATE TABLE `wp_searchengine_queue` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `site` int(10) NOT NULL,
  `template` int(10) NOT NULL,
  `shutdown` int(1) NOT NULL,
  `added` datetime NOT NULL,
  `updated` datetime NOT NULL,
  `queue` longtext COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `wp_searchengine_search`;
CREATE TABLE `wp_searchengine_search` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `sites` longtext NOT NULL,
  `query_filters` longtext NOT NULL,
  `uri_filters` longtext NOT NULL,
  `updated` datetime NOT NULL,
  `searched` datetime NOT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `wp_searchengine_sites`;
CREATE TABLE `wp_searchengine_sites` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group` int(10) NOT NULL,
  `host` varchar(255) NOT NULL,
  `scheme` varchar(5) NOT NULL,
  `indexed` datetime DEFAULT NULL,
  `updated` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `wp_searchengine_templates`;
CREATE TABLE `wp_searchengine_templates` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `group` int(10) NOT NULL,
  `site` int(10) NOT NULL,
  `directory` varchar(255) NOT NULL,
  `blacklist_words` longtext NOT NULL,
  `blacklist_uri_words` longtext NOT NULL,
  `max_depth` int(10) NOT NULL,
  `cross_scheme` int(10) unsigned DEFAULT NULL,
  `whitelist_uri_words` longtext NOT NULL,
  `disable_robots` int(1) NOT NULL,
  `htaccess_username` varchar(255) NOT NULL,
  `htaccess_password` varchar(255) NOT NULL,
  `updated` datetime NOT NULL,
  `indexed` datetime NOT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8;