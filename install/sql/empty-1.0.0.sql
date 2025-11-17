DROP TABLE IF EXISTS `glpi_plugin_wikitsemantics_configs`;
CREATE TABLE `glpi_plugin_wikitsemantics_configs` (
   `id` int unsigned NOT NULL auto_increment,
   `url_api` text COLLATE utf8mb4_unicode_ci default '' ,
   `app_id` text COLLATE utf8mb4_unicode_ci default '' ,
   `api_key` text COLLATE utf8mb4_unicode_ci default '' ,
   `organization_id` text COLLATE utf8mb4_unicode_ci default '' ,
   PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

INSERT INTO `glpi_plugin_wikitsemantics_configs` (`id`) VALUES ('1');
