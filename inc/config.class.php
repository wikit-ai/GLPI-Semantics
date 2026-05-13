<?php
/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2026 by the Wikit Development Team.
 * -------------------------------------------------------------------------
 */

/**
 * Configuration class for Wikit Semantics plugin
 *
 * Database table: glpi_plugin_wikitsemantics_configs
 * Class: PluginWikitsemanticsConfig
 */
class PluginWikitsemanticsConfig extends CommonDBTM
{
   public static $rightname = 'plugin_wikitsemantics_configs';
    public $dohistory = true;

   public function __construct() {
       /** @var \DBmysql $DB */
       global $DB;
      if ($DB->tableExists(self::getTable())) {
          $this->getFromDB(1);
      }
   }

   public static function canView(): bool {
       return Session::haveRight(self::$rightname, READ);
   }

   public static function canCreate(): bool {
       return Session::haveRight(self::$rightname, UPDATE);
   }

    /**
     * Prepare input before adding to database
     *
     * @param array $input
     * @return array
     */
   public function prepareInputForAdd($input) {
       return $this->prepareInputForUpdate($input);
   }

    /**
     * Prepare input before updating database
     * Encrypt API key before storing
     *
     * @param array $input
     * @return array
     */
   public function prepareInputForUpdate($input) {
       // Encrypt API key if provided and not already encrypted
      if (isset($input['api_key']) && !empty($input['api_key'])) {
          // If user didn't change the masked value, keep the existing encrypted key
         if ($input['api_key'] === '••••••••••••••••') {
            unset($input['api_key']); // Don't update
         } else if (strpos($input['api_key'], 'encrypted:') !== 0) {
             // Don't re-encrypt if already encrypted
             $input['api_key'] = 'encrypted:' . (new GLPIKey())->encrypt($input['api_key']);
         }
      }

       return $input;
   }

    /**
     * Get configuration instance
     *
     * @param bool $update Whether to refresh from database
     * @return self Configuration instance
     */
   public static function getConfig($update = false) {
       static $config = null;

      if (is_null($config)) {
          $config = new self();
      }
      if ($update) {
          $config->getFromDB(1);
      }

       return $config;
   }

    /**
     * Get localized name of the itemtype
     *
     * @param int $nb Number of items
     * @return string Localized name
     */
   public static function getTypeName($nb = 0) {
       return __("Configuration API", "wikitsemantics");
   }

    /**
     * Define tabs to display on the form page
     *
     * @param array $options Options
     * @return array Array of tabs
     * @see CommonGLPI::defineTabs()
     */
   public function defineTabs($options = []) {
       $ong = [];

       // Standard tabs related to other classes or your own
       $this->addStandardTab(PluginWikitsemanticsConfig::class, $ong, $options);
       $this->addStandardTab(__CLASS__, $ong, $options);
       return $ong;
   }

    /**
     * Check if this is a new item
     * For the tabs to display, this method must be present and return "false"
     * @return bool Always false for config (single row)
     */
   public function isNewItem() {
       return false;
   }

    /**
     * Display configuration form
     *
     * @param int $ID Configuration ID
     * @param array $options Additional options
     * @return bool
     */
   public function showForm($ID, array $options = []) {
       Session::checkRight(self::$rightname, READ);
       $this->getFromDB($ID);

       $options['candel'] = false;
       $options['colspan'] = 1;

       $this->showFormHeader($options);

       echo "<table class='wikitsemantics tab_cadre_fixe'>";

       // ── Section 1: Common Settings ──
       echo "<tr><th colspan='2' style='text-align: center;'>" . __(
           "Common settings",
           "wikitsemantics"
       ) . "</th></tr>";

       echo "<tr class='tab_bg_1'>";
       echo "<td>" . __("Wikit Semantics URL base", "wikitsemantics") . "</td>";
       echo "<td>";
       $url_api = !empty($this->fields['url_api']) ? $this->fields['url_api'] : 'https://apis.wikit.ai';
       echo Html::input('url_api', ['value' => $url_api, 'size' => 100]);
       echo "</td>";
       echo "</tr>";

       echo "<tr class='tab_bg_1'>";
       echo "<td>" . __("Organization ID", "wikitsemantics") . "</td>";
       echo "<td>";
       echo Html::input('organization_id', ['value' => $this->fields['organization_id'], 'size' => 100]);
       echo "</td>";
       echo "</tr>";

       echo "<tr class='tab_bg_1'>";
       echo "<td>" . __("API key", "wikitsemantics") . "</td>";
       echo "<td>";
       $api_key_display = $this->fields['api_key'];
      if (!empty($api_key_display) && strpos($api_key_display, 'encrypted:') === 0) {
          $api_key_display = '••••••••••••••••';
      }
       echo Html::input('api_key', [
           'value' => $api_key_display,
           'size' => 100,
           'placeholder' => __('Enter new API key to change', 'wikitsemantics')
       ]);
       echo "</td>";
       echo "</tr>";

       echo "<tr class='tab_bg_1'>";
       echo "<td>" . __("Enable Streaming Mode", "wikitsemantics") . "</td>";
       echo "<td>";
       $is_streaming = isset($this->fields['is_streaming_enabled']) ? $this->fields['is_streaming_enabled'] : 0;
       echo "<input type='hidden' name='is_streaming_enabled' value='0'>";
       echo "<label class='form-check form-switch d-inline-flex align-items-center gap-2'>";
       echo "<input type='checkbox' class='form-check-input' name='is_streaming_enabled' value='1' " . ($is_streaming ? "checked" : "") . ">";
       echo "<span class='text-muted'>" . __('Stream responses token by token', 'wikitsemantics') . "</span>";
       echo "</label>";
       echo "</td>";
       echo "</tr>";

       // ── Section 2: Ticket Answer Suggestion ──
       echo "<tr><th colspan='2' style='text-align: center;'>" . __(
           "Ticket answer suggestion",
           "wikitsemantics"
       ) . "</th></tr>";

       echo "<tr class='tab_bg_1'>";
       echo "<td>" . __("Application ID (Answer)", "wikitsemantics") . "</td>";
       echo "<td>";
       echo Html::input('app_id_answer', ['value' => $this->fields['app_id_answer'], 'size' => 100]);
       echo "</td>";
       echo "</tr>";

       // ── Section 3: Knowledge Base Generation ──
       echo "<tr><th colspan='2' style='text-align: center;'>" . __(
           "Knowledge base generation",
           "wikitsemantics"
       ) . "</th></tr>";

       echo "<tr class='tab_bg_1'>";
       echo "<td>" . __("Enable Knowledge Base generation", "wikitsemantics") . "</td>";
       echo "<td>";
       $is_kb_enabled = isset($this->fields['is_kb_enabled']) ? $this->fields['is_kb_enabled'] : 0;
       echo "<input type='hidden' name='is_kb_enabled' value='0'>";
       echo "<label class='form-check form-switch'>";
       echo "<input type='checkbox' class='form-check-input' name='is_kb_enabled' value='1' " . ($is_kb_enabled ? "checked" : "") . ">";
       echo "</label>";
       echo "</td>";
       echo "</tr>";

       echo "<tr class='tab_bg_1'>";
       echo "<td>" . __("Application ID (Knowledge Base)", "wikitsemantics") . "</td>";
       echo "<td>";
       echo Html::input('app_id_kb', ['value' => $this->fields['app_id_kb'], 'size' => 100]);
       echo "</td>";
       echo "</tr>";

       // ── Section 4: Editor AI Assistant ──
       echo "<tr><th colspan='2' style='text-align: center;'>" . __(
           "Editor AI assistant",
           "wikitsemantics"
       ) . "</th></tr>";

       echo "<tr class='tab_bg_1'>";
       echo "<td>" . __("Enable Editor AI assistant", "wikitsemantics") . "</td>";
       echo "<td>";
       $is_editor_ai_enabled = isset($this->fields['is_editor_ai_enabled']) ? $this->fields['is_editor_ai_enabled'] : 0;
       echo "<input type='hidden' name='is_editor_ai_enabled' value='0'>";
       echo "<label class='form-check form-switch d-inline-flex align-items-center gap-2'>";
       echo "<input type='checkbox' class='form-check-input' name='is_editor_ai_enabled' value='1' " . ($is_editor_ai_enabled ? "checked" : "") . ">";
       echo "<span class='text-muted'>" . implode(' · ', [
           __('Correction', 'wikitsemantics'),
           __('Formatting', 'wikitsemantics'),
           __('Translation', 'wikitsemantics'),
       ]) . "</span>";
       echo "</label>";
       echo "</td>";
       echo "</tr>";

       echo "<tr class='tab_bg_1'>";
       echo "<td>" . __("Application ID (Editor AI)", "wikitsemantics") . "</td>";
       echo "<td>";
       echo Html::input('app_id_editor', ['value' => $this->fields['app_id_editor'], 'size' => 100]);
       echo "</td>";
       echo "</tr>";

       // ── Test Connection Button (bottom) ──
      if (isset($this->fields['url_api']) && !empty($this->fields['url_api'])) {
          echo "<tr><td colspan='2'>&nbsp;</td></tr>";
          echo "<tr class='tab_bg_1'>";
          echo "<td colspan='2' class='center'>";
          echo "<button class=\"btn btn-outline-secondary\" type=\"submit\" name=\"TestConnection\" value=\"1\">";
          echo "<i class=\"ti ti-plug-connected me-1\"></i>";
          echo __('Test connection', 'wikitsemantics');
          echo "</button>";
          echo "</td>";
          echo "</tr>";
      }

       echo "</table>";

       $this->showFormButtons($options);

       return true;
   }

    /**
     * Test connection to Wikit Semantics API
     *
     * @param array|null $dataToPost Optional data to post (for testing specific query)
     * @return bool|string Boolean on UI test, string answer on direct call
     */
   public function testConnection($dataToPost = null) {
       // Direct call from ticket — test single app
      if ($dataToPost) {
          $result = $this->getAPIAnswer($dataToPost);
          return $result ? $result['answer'] : false;
      }

       // Test all active apps from config page
       $apps = [
           [
               'field'   => 'app_id_answer',
               'label'   => __('Ticket answer suggestion', 'wikitsemantics'),
               'enabled' => true, // Always enabled if app_id is set
           ],
           [
               'field'   => 'app_id_kb',
               'label'   => __('Knowledge base generation', 'wikitsemantics'),
               'enabled' => !empty($this->fields['is_kb_enabled']),
           ],
           [
               'field'   => 'app_id_editor',
               'label'   => __('Editor AI assistant', 'wikitsemantics'),
               'enabled' => !empty($this->fields['is_editor_ai_enabled']),
           ],
       ];

       $hasError = false;
       $testedCount = 0;

      foreach ($apps as $app) {
          // Skip disabled apps
         if (!$app['enabled']) {
             continue;
         }

          // Skip apps without an app ID configured
         if (empty($this->fields[$app['field']])) {
             Session::addMessageAfterRedirect(
                 sprintf(__('%s: no Application ID configured', 'wikitsemantics'), $app['label']),
                 false,
                 WARNING
             );
             continue;
         }

          $testedCount++;
          $result = $this->getAPIAnswer(null, $app['field']);

         if (!$result) {
             $hasError = true;
             Session::addMessageAfterRedirect(
                 sprintf(__('%s: connection failed', 'wikitsemantics'), $app['label']),
                 false,
                 ERROR
             );
         } else {
             Session::addMessageAfterRedirect(
                 sprintf(__('%s: connection successful', 'wikitsemantics'), $app['label']),
                 false
             );
         }
      }

      if ($testedCount === 0) {
          Session::addMessageAfterRedirect(
              __('No application to test. Enable at least one feature and configure its Application ID.', 'wikitsemantics'),
              false,
              WARNING
          );
      }

       return !$hasError;
   }

    /**
     * Get AI answer from Wikit Semantics API
     *
     * @param array|null $dataToPost Data containing the query
     * @param string $appIdField Config field name for the app ID
     * @return array|false Array with 'answer' and 'queryId' keys, or false on error
     */
   public function getAPIAnswer($dataToPost = null, $appIdField = 'app_id_answer') {
       $url = '/semantics/apps/' . $this->fields[$appIdField] . '/query-executions';
       $dataToPost = $this->cleanDataToPostQuery($dataToPost);

       $return = $this->APICall($url, $dataToPost);

      if ($return['httpCode'] == 200) {
          return [
              'answer'  => $return['data']['answer'] ?? '',
              'queryId' => $return['data']['queryId'] ?? null,
          ];
      } else {
          return false;
      }
   }

    /**
     * Get quoted sources for a query execution
     *
     * @param string $queryId The query execution ID
     * @param string $appIdField Config field name for the app ID
     * @return array|false Array of sources or false on error
     */
   public function getQuotedSources(string $queryId, string $appIdField = 'app_id_answer') {
       $url = '/semantics/apps/' . $this->fields[$appIdField]
           . '/query-execution-sources/' . urlencode($queryId) . '/get-quoted-sources';

       $return = $this->APICall($url, null, 'GET');

      if ($return['httpCode'] == 200) {
          $data = $return['data'] ?? [];
          // API may wrap sources in a nested key
          if (isset($data['quotedSources'])) {
              return $data['quotedSources'];
          }
          if (isset($data['data'])) {
              return $data['data'];
          }
          if (isset($data['sources'])) {
              return $data['sources'];
          }
          return $data;
      } else {
          return false;
      }
   }

    /**
     * Stream AI answer from Wikit Semantics API using SSE
     * Outputs Server-Sent Events directly to the response stream
     *
     * @param array $dataToPost Data containing the query
     * @param string $appIdField Config field name for the app ID
     * @return void
     */
   public function streamAPIAnswer($dataToPost, $appIdField = 'app_id_answer') {
       global $CFG_GLPI;

      if (!isset($dataToPost['query'])) {
          $dataToPost = ['query' => 'Hello ! Je suis un test venant du plugin GLPI !'];
      }
       $dataToPost = $this->cleanDataToPostQuery($dataToPost);

       $proxy_host  = !empty($CFG_GLPI["proxy_name"]) ? ($CFG_GLPI["proxy_name"] . ":" . $CFG_GLPI["proxy_port"]) : false;
       $proxy_ident = !empty($CFG_GLPI["proxy_user"]) ? ($CFG_GLPI["proxy_user"] . ":" .
           (new GLPIKey())->decrypt($CFG_GLPI["proxy_passwd"])) : false;

       // Build URL with streaming parameter
       $url = '/semantics/apps/' . $this->fields[$appIdField] . '/query-executions?is_stream_mode=true';

      if (substr($this->fields['url_api'], -1) == '/') {
          $url = substr($this->fields['url_api'], 0, -1) . $url;
      } else {
          $url = $this->fields['url_api'] . $url;
      }

       // Decrypt API key before use
       $api_key = $this->fields['api_key'];
      if (!empty($api_key) && strpos($api_key, 'encrypted:') === 0) {
          $api_key = (new GLPIKey())->decrypt(substr($api_key, 10));
      }

       $header = [
           'Content-Type: application/json',
           'Wikit-Semantics-API-Key: ' . $api_key,
           'X-Wikit-Response-Format: json',
           'X-Wikit-Organization-Id: ' . $this->fields['organization_id'],
       ];

       $ch = curl_init();
       curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
       curl_setopt($ch, CURLOPT_URL, $url);
       curl_setopt($ch, CURLOPT_HEADER, 0);
       curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
       curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
       curl_setopt($ch, CURLOPT_POST, 1);
       curl_setopt($ch, CURLOPT_TIMEOUT, 30);
       curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

       // Enable the use of a proxy server
       if (!empty($CFG_GLPI["proxy_name"])) {
           curl_setopt($ch, CURLOPT_PROXY, $proxy_host);
          if ($proxy_ident) {
              curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_ident);
          }
       }

       curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dataToPost));

       // Use write function to process streaming data
       $buffer = '';
       curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$buffer) {
           $buffer .= $data;

           // Process complete chunks ending with STOP
         while (($pos = strpos($buffer, 'STOP')) !== false) {
            $chunk = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + 4); // Remove processed chunk + "STOP"

            // The format from Wikit API is: data: {"queryId": "...", "chunk":"..."}STOP
            // We need to extract the JSON part
            $lines = explode("\n", trim($chunk));

            foreach ($lines as $line) {
                $line = trim($line);

                // Look for lines starting with "data: "
               if (strpos($line, 'data:') === 0) {
                   // Remove "data:" prefix and trim spaces
                   $jsonData = trim(substr($line, 5));
                   $decoded = json_decode($jsonData, true);

                  if ($decoded && isset($decoded['chunk'])) {
                     // Send SSE event with the chunk (include queryId if present)
                     $eventData = ['chunk' => $decoded['chunk']];
                     if (isset($decoded['queryId'])) {
                         $eventData['queryId'] = $decoded['queryId'];
                     }
                     echo "event: chunk\n";
                     echo "data: " . json_encode($eventData) . "\n\n";

                     // Force flush to send data immediately
                     if (ob_get_level() > 0) {
                        ob_flush();
                     }
                     flush();
                  }
               }
            }
         }

           return strlen($data);
       });

       $result = curl_exec($ch);
       $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

      if ($result === false) {
          $error = curl_error($ch);
          throw new \Exception("Streaming API call failed: " . $error);
      }
   }

    /**
     * Execute API call to Wikit Semantics
     *
     * @param string $url API endpoint URL
     * @param array $dataToPost Data to send in POST
     * @return array Array containing 'httpCode', 'data', and optionally 'error'
     */
   public function APICall($url, $dataToPost = null, $method = 'POST') {
       global $CFG_GLPI;

      if ($method === 'POST' && !isset($dataToPost['query'])) {
          $dataToPost = ['query' => 'Il fait beau aujourd\'hui ?'];
      }

       $proxy_host  = !empty($CFG_GLPI["proxy_name"]) ? ($CFG_GLPI["proxy_name"] . ":" . $CFG_GLPI["proxy_port"]) : false; // host:port
       $proxy_ident = !empty($CFG_GLPI["proxy_user"]) ? ($CFG_GLPI["proxy_user"] . ":" .
           (new GLPIKey())->decrypt($CFG_GLPI["proxy_passwd"])) : false; // username:password

      if (substr($this->fields['url_api'], -1) == '/') {
          $url = substr($this->fields['url_api'], 0, -1) . $url;
      } else {
          $url = $this->fields['url_api'] . $url;
      }

       // Decrypt API key before use
       $api_key = $this->fields['api_key'];
      if (!empty($api_key) && strpos($api_key, 'encrypted:') === 0) {
          $api_key = (new GLPIKey())->decrypt(substr($api_key, 10));
      }

       $header =
           [
               'Content-Type: application/json',
               'Wikit-Semantics-API-Key: ' . $api_key,
               'X-Wikit-Response-Format: json',
               'X-Wikit-Organization-Id: ' . $this->fields['organization_id'],
           ];

       $ch = curl_init();
       curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
       curl_setopt($ch, CURLOPT_URL, $url);
       curl_setopt($ch, CURLOPT_HEADER, 0);
       curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
       curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
       curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
       curl_setopt($ch, CURLOPT_TIMEOUT, 30);
       curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

      if ($method === 'POST') {
          curl_setopt($ch, CURLOPT_POST, 1);
         if (isset($dataToPost)) {
             curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dataToPost));
         }
      }

       // Enable the use of a proxy server
       if (!empty($CFG_GLPI["proxy_name"])) {
           curl_setopt($ch, CURLOPT_PROXY, $proxy_host);
          if ($proxy_ident) {
              curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_ident);
          }
       }

       $data = curl_exec($ch);
       $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

       // Handle cURL errors
       if ($data === false) {
           $error = curl_error($ch);
           curl_close($ch);
           PluginWikitsemanticsLogger::error("API call failed (cURL)", ['error' => $error, 'url' => $url]);
           return ['httpCode' => 0, 'data' => null, 'error' => $error];
       }

       curl_close($ch);

       if ($httpCode >= 400) {
           PluginWikitsemanticsLogger::warning("API returned HTTP error", ['http_code' => $httpCode, 'url' => $url]);
       } else {
           PluginWikitsemanticsLogger::debug("API call successful", ['http_code' => $httpCode, 'url' => $url]);
       }

       return ['httpCode' => $httpCode, 'data' => json_decode($data, 1)];
   }

    /**
     * Clean the query payload before sending it to Wikit Semantics.
     *
     * @param array|null $dataToPost Data containing the query
     * @return array|null Cleaned data
     */
   private function cleanDataToPostQuery($dataToPost) {
      if (!is_array($dataToPost) || !isset($dataToPost['query']) || !is_string($dataToPost['query'])) {
          return $dataToPost;
      }

       $dataToPost['query'] = $this->htmlToMarkdown($dataToPost['query']);

       return $dataToPost;
   }

    /**
     * Convert rich HTML content from GLPI into readable Markdown/plain text.
     *
     * @param string $content HTML or text content
     * @return string Markdown/plain text content
     */
   private function htmlToMarkdown($content) {
       $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
       $content = str_replace(["\r\n", "\r"], "\n", $content);

      if (preg_match('/<[a-z][\s\S]*>/i', $content)) {
          $content = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $content);
          $content = preg_replace('/<br\s*\/?>/i', "\n", $content);
          $content = preg_replace_callback('/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', function($matches) {
              return "\n\n" . str_repeat('#', (int)$matches[1]) . ' ' . trim(strip_tags($matches[2])) . "\n\n";
          }, $content);
          $content = preg_replace('/<li\b[^>]*>/i', "\n- ", $content);
          $content = preg_replace('/<\/li>/i', "\n", $content);
          $content = preg_replace('/<(strong|b)\b[^>]*>(.*?)<\/\1>/is', '**$2**', $content);
          $content = preg_replace('/<(em|i)\b[^>]*>(.*?)<\/\1>/is', '*$2*', $content);
          $content = preg_replace('/<code\b[^>]*>(.*?)<\/code>/is', '`$1`', $content);
          $content = preg_replace_callback('/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', function($matches) {
              $label = trim(strip_tags($matches[2]));
              $href = trim($matches[1]);
              return $label !== '' ? '[' . $label . '](' . $href . ')' : $href;
          }, $content);
          $content = preg_replace('/<\/(p|div|section|article|header|footer|blockquote|ul|ol|table|thead|tbody|tr)>/i', "\n\n", $content);
          $content = preg_replace('/<\/(td|th)>/i', " | ", $content);
          $content = strip_tags($content);
      }

       $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
       $content = preg_replace("/[ \t]+/", ' ', $content);
       $content = preg_replace("/ *\n */", "\n", $content);
       $content = preg_replace("/\n{3,}/", "\n\n", $content);

       return trim($content);
   }
}
