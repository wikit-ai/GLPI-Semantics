<?php
/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2026 by the Wikit Development Team.
 * -------------------------------------------------------------------------
 */

Session::checkRight("plugin_wikitsemantics_configs", READ);

//Html::header(Ticket::getTypeName(Session::getPluralNumber()));
echo '<div style="display: block; width: 200px; padding: 20px"><i class="fas fa-4x fa-spinner fa-pulse m-5 start-50" style="position: relative;margin: auto !important;"></i></div>';
//Html::footer();
