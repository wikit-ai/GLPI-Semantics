DROP TABLE IF EXISTS `glpi_plugin_wikitsemantics_configs`;
CREATE TABLE `glpi_plugin_wikitsemantics_configs` (
   `id` int unsigned NOT NULL auto_increment,
   `url_api` varchar(500) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
   `app_id` varchar(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
   `api_key` varchar(500) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'Encrypted with GLPIKey',
   `organization_id` varchar(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
   `date_creation` timestamp NULL DEFAULT NULL,
   `date_mod` timestamp NULL DEFAULT NULL,
   PRIMARY KEY  (`id`),
   KEY `date_mod` (`date_mod`),
   KEY `date_creation` (`date_creation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

INSERT INTO `glpi_plugin_wikitsemantics_configs` (`id`, `date_creation`) VALUES ('1', NOW());
