ALTER TABLE `glpi_plugin_wikitsemantics_configs`
   CHANGE COLUMN `app_id` `app_id_answer` varchar(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
   ADD COLUMN `is_kb_enabled` tinyint NOT NULL DEFAULT 0 AFTER `is_streaming_enabled`,
   ADD COLUMN `app_id_kb` varchar(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `is_kb_enabled`,
   ADD COLUMN `is_editor_ai_enabled` tinyint NOT NULL DEFAULT 0 AFTER `app_id_kb`,
   ADD COLUMN `app_id_editor` varchar(255) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `is_editor_ai_enabled`,
   ADD COLUMN `is_sources_enabled_answer` tinyint NOT NULL DEFAULT 0 AFTER `app_id_answer`;

