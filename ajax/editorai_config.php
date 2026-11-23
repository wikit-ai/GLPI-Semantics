<?php
/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2026 by the Wikit Development Team.
 * -------------------------------------------------------------------------
 *
 * AJAX endpoint that returns Editor AI configuration as JSON
 * Fallback of wikitsemantics.js when the configuration is not in the page (meta tag added by the post_init hook)
 */

header('Content-Type: application/json');

echo json_encode(PluginWikitsemanticsConfig::getEditorAIClientConfig());
