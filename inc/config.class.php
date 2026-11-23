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

    /**
     * Maximum duration of a call that generates an answer (streamed or workflow), in seconds
     */
    public const STREAM_MAX_DURATION = 180;

    /**
     * Classic application, called through `/semantics/apps/...`
     */
    public const APP_TYPE_SEMANTICS = 'semantics';

    /**
     * Workflow application, called through `/semantics-workflow/apps/...`
     */
    public const APP_TYPE_WORKFLOW = 'semantics-workflow';

    /**
     * Lifetime of the application types and page settings kept in the GLPI cache, in seconds
     */
    public const APP_TYPE_CACHE_TTL = 86400;

    /**
     * Name of the meta tag holding the Editor AI client configuration in the GLPI pages
     */
    public const EDITOR_AI_CLIENT_CONFIG_META = 'wikitsemantics-editor-ai';

    /**
     * GLPI cache key of the settings used on every page, see `getPageSettings()`
     */
    private const PAGE_SETTINGS_CACHE_KEY = 'wikitsemantics_page_settings';

    /**
     * Application types already known during this request, by cache key
     *
     * @var array<string, string>
     */
    private $appTypes = [];

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
       // Only accept a well-formed HTTPS URL for the API
      if (isset($input['url_api'])) {
          $input['url_api'] = trim($input['url_api']);
          $scheme = parse_url($input['url_api'], PHP_URL_SCHEME);
          $host = parse_url($input['url_api'], PHP_URL_HOST);
         if (!is_string($scheme) || strtolower($scheme) !== 'https' || empty($host)) {
             Session::addMessageAfterRedirect(
                 __('The API URL must be a valid HTTPS URL.', 'wikitsemantics'),
                 false,
                 ERROR
             );
             return false;
         }
      }

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

       // The configuration is read once per request, not each time the class is instantiated
       // (e.g. by the GLPI history and search)
      if (is_null($config)) {
          $config = new self();
          $config->getFromDB(1);
      } else if ($update) {
          $config->getFromDB(1);
      }

       return $config;
   }

    /**
     * Settings needed on every GLPI page (Editor AI assistant), kept in the GLPI cache so that
     * no database query is needed to display a page. The cache is cleared when the
     * configuration is saved.
     *
     * @return array{is_editor_ai_enabled: bool, is_streaming_enabled: bool}
     */
   public static function getPageSettings(): array {
       /** @var \Psr\SimpleCache\CacheInterface $GLPI_CACHE */
       global $GLPI_CACHE;

       $settings = $GLPI_CACHE->get(self::PAGE_SETTINGS_CACHE_KEY);
      if (is_array($settings)
           && is_bool($settings['is_editor_ai_enabled'] ?? null)
           && is_bool($settings['is_streaming_enabled'] ?? null)
       ) {
          return [
              'is_editor_ai_enabled' => $settings['is_editor_ai_enabled'],
              'is_streaming_enabled' => $settings['is_streaming_enabled'],
          ];
      }

       $config = self::getConfig(true);
       $settings = [
           'is_editor_ai_enabled' => !empty($config->fields['is_editor_ai_enabled']) && $config->getStringField('app_id_editor') !== '',
           'is_streaming_enabled' => !empty($config->fields['is_streaming_enabled']),
       ];
       $GLPI_CACHE->set(self::PAGE_SETTINGS_CACHE_KEY, $settings, self::APP_TYPE_CACHE_TTL);

       return $settings;
   }

    /**
     * Configuration of the Editor AI assistant for the JavaScript of the GLPI pages.
     *
     * It needs no database query (settings kept in the GLPI cache): it is added to every page
     * (meta tag, see `plugin_wikitsemantics_post_init()`), and returned by `ajax/editorai_config.php`.
     *
     * @return array<string, mixed>
     */
   public static function getEditorAIClientConfig(): array {
       /** @var array $CFG_GLPI */
       global $CFG_GLPI;

      if (!Session::getLoginUserID() || !Session::haveRight('plugin_wikitsemantics_editorai', READ)) {
          return ['enabled' => false];
      }

       $settings = self::getPageSettings();
      if (!$settings['is_editor_ai_enabled']) {
          return ['enabled' => false];
      }

       $pluginWebPath = $CFG_GLPI['root_doc'] . '/plugins/wikitsemantics';

       return [
           'enabled'            => true,
           'isStreamingEnabled' => (int)$settings['is_streaming_enabled'],
           'ajaxUrl'            => $pluginWebPath . '/ajax/editorai.php',
           'ajaxStreamUrl'      => $pluginWebPath . '/ajax/editorai_stream.php',
           'labels'             => [
               'editorAI'    => __('Editor AI assistant', 'wikitsemantics'),
               'correction'  => __('Correction', 'wikitsemantics'),
               'formatting'  => __('Formatting', 'wikitsemantics'),
               'translation' => __('Translation', 'wikitsemantics'),
               'apply'       => __('Apply', 'wikitsemantics'),
               'close'       => __('Close', 'wikitsemantics'),
               'error'       => __('GLPI encountered a problem connecting to the Wikit Semantics application. Please try again later.', 'wikitsemantics'),
           ],
       ];
   }

   public function post_addItem() {
       parent::post_addItem();
       self::clearPageSettingsCache();
   }

   public function post_updateItem($history = true) {
       parent::post_updateItem($history);
       self::clearPageSettingsCache();
   }

   private static function clearPageSettingsCache(): void {
       /** @var \Psr\SimpleCache\CacheInterface $GLPI_CACHE */
       global $GLPI_CACHE;

       $GLPI_CACHE->delete(self::PAGE_SETTINGS_CACHE_KEY);
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
     * Classic applications answer with JSON, workflow applications always answer with a stream
     * of events which is read entirely. See `getAppTypesToTry()` for the choice of the API.
     *
     * @param array|null $dataToPost Data containing the query
     * @param string $appIdField Config field name for the app ID
     * @return array|false Array with 'answer' and 'queryId' keys, or false on error
     */
   public function getAPIAnswer($dataToPost = null, $appIdField = 'app_id_answer') {
       $dataToPost = $this->cleanDataToPostQuery($dataToPost);

       $appTypes = $this->getAppTypesToTry($appIdField);
       $lastAppType = end($appTypes);

      foreach ($appTypes as $appType) {
          $hasFallback = $appType !== $lastAppType;
          [$result, $mayBeWrongType] = $appType === self::APP_TYPE_WORKFLOW
              ? $this->getWorkflowAnswer($dataToPost, $appIdField, $hasFallback)
              : $this->getClassicAnswer($dataToPost, $appIdField, $hasFallback);

         if ($result !== false) {
             $this->rememberAppType($appIdField, $appType);
             return $result;
         }

         if (!$mayBeWrongType) {
             break;
         }
      }

       return false;
   }

    /**
     * Get the answer of a classic application
     *
     * @param array|null $dataToPost  Data containing the query
     * @param string     $appIdField  Config field name for the app ID
     * @param bool       $hasFallback Whether the workflow API will be tried after an error
     * @return array{0: array|false, 1: bool} Answer (false on error), and whether the error may
     *                                        mean that the application is a workflow
     */
   private function getClassicAnswer($dataToPost, string $appIdField, bool $hasFallback): array {
       $url = $this->getAppPath(self::APP_TYPE_SEMANTICS, $appIdField, '/query-executions');
       $return = $this->APICall($url, $dataToPost, 'POST', false);

      if ($return['httpCode'] == 200) {
          return [[
              'answer'  => $return['data']['answer'] ?? '',
              'queryId' => $return['data']['queryId'] ?? null,
          ], false];
      }

       $mayBeWrongType = $this->isWrongAppTypeStatus((int)$return['httpCode']);
      if ($return['httpCode'] !== 0) {
          $this->logFailedAttempt(
              'API returned HTTP error',
              ['http_code' => $return['httpCode'], 'url' => $url],
              $mayBeWrongType && $hasFallback
          );
      }

       return [false, $mayBeWrongType];
   }

    /**
     * Get the complete answer of a workflow application, by reading its whole stream of events
     *
     * @param array|null $dataToPost  Data containing the query
     * @param string     $appIdField  Config field name for the app ID
     * @param bool       $hasFallback Whether the classic API will be tried after an error
     * @return array{0: array|false, 1: bool} Answer (false on error), and whether the error may
     *                                        mean that the application is a classic one
     */
   private function getWorkflowAnswer($dataToPost, string $appIdField, bool $hasFallback): array {
      if (!isset($dataToPost['query'])) {
          $dataToPost = ['query' => 'Il fait beau aujourd\'hui ?'];
      }

       $url = $this->getAPIUrl($this->getAppPath(self::APP_TYPE_WORKFLOW, $appIdField, '/query-executions'));
       $ch = $this->initCurl($url, self::STREAM_MAX_DURATION);
       curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
       curl_setopt($ch, CURLOPT_POST, 1);
       curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dataToPost));

       $content = curl_exec($ch);
       $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

      if ($content === false) {
          PluginWikitsemanticsLogger::error("API call failed (cURL)", ['error' => curl_error($ch), 'url' => $url]);
          return [false, false];
      }

      if ($httpCode >= 400) {
          $mayBeWrongType = $this->isWrongAppTypeStatus($httpCode);
          $this->logFailedAttempt('API returned HTTP error', ['http_code' => $httpCode, 'url' => $url], $mayBeWrongType && $hasFallback);
          return [false, $mayBeWrongType];
      }

       $answer = '';
       $queryId = null;
      foreach ($this->extractStreamEvents($content) as $event) {
          $answer .= $event['chunk'];
          $queryId ??= $event['queryId'] ?? null;
      }

       // The workflow gateway answers with an empty stream when the execution fails,
       // for instance when the application is not a workflow
      if (trim($answer) === '') {
          $this->logFailedAttempt('Workflow returned an empty answer', ['url' => $url], $hasFallback);
          return [false, true];
      }

       PluginWikitsemanticsLogger::debug("API call successful", ['http_code' => $httpCode, 'url' => $url]);

       return [['answer' => $answer, 'queryId' => $queryId], false];
   }

    /**
     * Get quoted sources for a query execution
     *
     * @param string $queryId The query execution ID
     * @param string $appIdField Config field name for the app ID
     * @return array|false Array of sources or false on error
     */
   public function getQuotedSources(string $queryId, string $appIdField = 'app_id_answer') {
       // The quoted sources are not available for the workflow applications
      if ($this->getAppType($appIdField) === self::APP_TYPE_WORKFLOW) {
          return [];
      }

       $url = $this->getAppPath(
           self::APP_TYPE_SEMANTICS,
           $appIdField,
           '/query-execution-sources/' . urlencode($queryId) . '/get-quoted-sources'
       );

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
     * See `getAppTypesToTry()` for the choice of the API: nothing is sent before the application
     * answers with some text, so that the other API can still be tried.
     *
     * @param array $dataToPost Data containing the query
     * @param string $appIdField Config field name for the app ID
     * @return void
     *
     * @throws \RuntimeException When the API cannot be reached, returns an HTTP error or an empty answer
     */
   public function streamAPIAnswer($dataToPost, $appIdField = 'app_id_answer') {
      if (!isset($dataToPost['query'])) {
          $dataToPost = ['query' => 'Hello ! Je suis un test venant du plugin GLPI !'];
      }

       $dataToPost = $this->cleanDataToPostQuery($dataToPost) ?? [];
       $appTypes = $this->getAppTypesToTry($appIdField);
       $lastAppType = end($appTypes);

      foreach ($appTypes as $appType) {
          [$hasText, $httpCode] = $this->streamFrom($appType, $appIdField, $dataToPost);

         if ($hasText) {
             $this->rememberAppType($appIdField, $appType);
             return;
         }

         if ($httpCode >= 400) {
            if ($appType === $lastAppType || !$this->isWrongAppTypeStatus($httpCode)) {
                PluginWikitsemanticsLogger::warning("API returned HTTP error", ['http_code' => $httpCode, 'app_type' => $appType]);
                throw new \RuntimeException(sprintf('Wikit Semantics API returned HTTP %d', $httpCode));
            }

             $this->logFailedAttempt('API returned HTTP error', ['http_code' => $httpCode, 'app_type' => $appType], true);
             continue;
         }

          // Empty answer: the workflow gateway answers this way when the application is not a workflow
          $this->logFailedAttempt('API returned an empty answer', ['app_type' => $appType], $appType !== $lastAppType);
      }

       throw new \RuntimeException('Wikit Semantics API returned an empty answer');
   }

    /**
     * Stream the answer of an application as SSE events
     *
     * @param string $appType    One of the `APP_TYPE_*` constants
     * @param string $appIdField Config field name for the app ID
     * @param array  $dataToPost Data containing the query
     * @return array{0: bool, 1: int} Whether at least one chunk of text was sent, and the HTTP code
     *
     * @throws \RuntimeException When the API cannot be reached
     */
   private function streamFrom(string $appType, string $appIdField, array $dataToPost): array {
       // Workflow applications always stream: the stream mode parameter is only needed by the classic ones
       $suffix = $appType === self::APP_TYPE_SEMANTICS ? '/query-executions?is_stream_mode=true' : '/query-executions';
       $url = $this->getAPIUrl($this->getAppPath($appType, $appIdField, $suffix));

       // A generated answer or article can take longer than 30 seconds
       $ch = $this->initCurl($url, self::STREAM_MAX_DURATION);
       curl_setopt($ch, CURLOPT_POST, 1);
       curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dataToPost));

       // Process complete chunks ending with STOP as soon as they are received
       $buffer = '';
       $queryId = null;
       $hasText = false;
       curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$buffer, &$queryId, &$hasText) {
           // The body of an HTTP error is not an answer: it is ignored, the caller handles the status
          if ((int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE) >= 400) {
              return strlen($data);
          }

           $buffer .= $data;
           $hasText = $this->sendStreamedChunks($buffer, $queryId) || $hasText;

           return strlen($data);
       });

       $result = curl_exec($ch);
       $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

      if ($result === false) {
          $error = curl_error($ch);
          PluginWikitsemanticsLogger::error("Streaming API call failed", ['error' => $error, 'url' => $url]);
          throw new \RuntimeException('Streaming API call failed: ' . $error);
      }

      if ($hasText) {
          PluginWikitsemanticsLogger::debug("API call successful", ['http_code' => $httpCode, 'url' => $url]);
      }

       return [$hasText, $httpCode];
   }

    /**
     * Send the complete chunks of the Wikit API stream as SSE events, and remove them from the buffer.
     *
     * Events without text (workflow metadata) are not sent, their queryId is added to the next
     * chunk: nothing is sent before the application answers with some text.
     *
     * @param string      $buffer  Data received from the API and not processed yet
     * @param string|null $queryId queryId received in an event without text and not sent yet
     * @return bool Whether at least one chunk of text was sent
     */
   private function sendStreamedChunks(string &$buffer, ?string &$queryId): bool {
       $hasText = false;
      foreach ($this->extractStreamEvents($buffer) as $eventData) {
         if ($eventData['chunk'] === '') {
             $queryId ??= $eventData['queryId'] ?? null;
             continue;
         }

         if (!isset($eventData['queryId']) && $queryId !== null) {
             $eventData['queryId'] = $queryId;
         }

          $hasText = true;

          echo "event: chunk\n";
          echo "data: " . json_encode($eventData) . "\n\n";

          // Force flush to send data immediately
         if (ob_get_level() > 0) {
             ob_flush();
         }
          flush();
      }

       return $hasText;
   }

    /**
     * Extract the complete events of the Wikit API stream, and remove them from the buffer.
     *
     * The format from Wikit API is: data: {"queryId": "...", "chunk":"..."}STOP
     * Workflow applications also send keep-alive comments (": ping") and events without text.
     *
     * The delimiter is the "STOP" right after the closing brace of the JSON object, or right
     * before the next event (invalid data), so that a "STOP" written in the generated text
     * does not cut the event in two.
     *
     * @param string $buffer Data received from the API and not processed yet
     * @return array<int, array{chunk: string, queryId?: string}>
     */
   private function extractStreamEvents(string &$buffer): array {
       $events = [];
       // Earliest delimiter: "}STOP" (the closing brace is kept) or "STOP" before the next event
      while (preg_match('/^(.*?)(\}STOP|STOP(?=\s*data:))/s', $buffer, $matches) === 1) {
          $chunk = $matches[1] . ($matches[2] === '}STOP' ? '}' : '');
          $buffer = substr($buffer, strlen($matches[0])); // Remove processed chunk + delimiter

         foreach (explode("\n", trim($chunk)) as $line) {
             $line = trim($line);

             // Look for lines starting with "data: "
            if (strpos($line, 'data:') !== 0) {
                continue;
            }

             $decoded = json_decode(trim(substr($line, 5)), true);
            if (!is_array($decoded) || !isset($decoded['chunk']) || !is_string($decoded['chunk'])) {
                continue;
            }

             // Chunk with the queryId if present
             $eventData = ['chunk' => $decoded['chunk']];
            if (isset($decoded['queryId']) && is_string($decoded['queryId'])) {
                $eventData['queryId'] = $decoded['queryId'];
            }

             $events[] = $eventData;
         }
      }

       return $events;
   }

    /**
     * Path of an endpoint of the configured application
     *
     * @param string $appType    One of the `APP_TYPE_*` constants
     * @param string $appIdField Config field name for the app ID
     * @param string $suffix     Endpoint path after the application ID, starting with a slash
     */
   private function getAppPath(string $appType, string $appIdField, string $suffix): string {
       return '/' . $appType . '/apps/' . rawurlencode($this->getStringField($appIdField)) . $suffix;
   }

    /**
     * APIs to try for the configured application, in order.
     *
     * The type of an application (classic: `/semantics/`, workflow: `/semantics-workflow/`) cannot
     * be read with an API key: the type that answered last time is tried first (classic when
     * unknown), then the other one when the call fails in a way that may mean a wrong type
     * (see `isWrongAppTypeStatus()`, empty answer of the workflow API). The type that answers
     * is kept in the GLPI cache, so an application that changes of type is detected again.
     *
     * @param string $appIdField Config field name for the app ID
     * @return string[] `APP_TYPE_*` constants
     */
   private function getAppTypesToTry(string $appIdField): array {
       return $this->getAppType($appIdField) === self::APP_TYPE_WORKFLOW
           ? [self::APP_TYPE_WORKFLOW, self::APP_TYPE_SEMANTICS]
           : [self::APP_TYPE_SEMANTICS, self::APP_TYPE_WORKFLOW];
   }

    /**
     * Type of the configured application that answered last time, kept in the GLPI cache
     *
     * @param string $appIdField Config field name for the app ID
     * @return string|null One of the `APP_TYPE_*` constants, null when unknown
     */
   protected function getAppType(string $appIdField): ?string {
       /** @var \Psr\SimpleCache\CacheInterface $GLPI_CACHE */
       global $GLPI_CACHE;

       $appId = $this->getStringField($appIdField);
      if ($appId === '') {
          return null;
      }

       $cacheKey = $this->getAppTypeCacheKey($appId);
      if (isset($this->appTypes[$cacheKey])) {
          return $this->appTypes[$cacheKey];
      }

       $appType = $GLPI_CACHE->get($cacheKey);
      if (!in_array($appType, [self::APP_TYPE_SEMANTICS, self::APP_TYPE_WORKFLOW], true)) {
          return null;
      }

       $this->appTypes[$cacheKey] = $appType;

       return $appType;
   }

    /**
     * Keep the type of the configured application for the next calls
     *
     * @param string $appIdField Config field name for the app ID
     * @param string $appType    One of the `APP_TYPE_*` constants
     */
   private function rememberAppType(string $appIdField, string $appType): void {
       /** @var \Psr\SimpleCache\CacheInterface $GLPI_CACHE */
       global $GLPI_CACHE;

       $appId = $this->getStringField($appIdField);
      if ($appId === '') {
          return;
      }

       $cacheKey = $this->getAppTypeCacheKey($appId);
      if (($this->appTypes[$cacheKey] ?? null) === $appType) {
          return;
      }

       $this->appTypes[$cacheKey] = $appType;
       $GLPI_CACHE->set($cacheKey, $appType, self::APP_TYPE_CACHE_TTL);
   }

   private function getAppTypeCacheKey(string $appId): string {
       return 'wikitsemantics_app_type_' . sha1($this->getStringField('url_api') . '|' . $appId);
   }

    /**
     * Value of a configuration field as a string, empty when missing or not scalar
     */
   private function getStringField(string $name): string {
       $value = $this->fields[$name] ?? null;

       return is_scalar($value) ? (string)$value : '';
   }

    /**
     * Log a failed call to the Wikit Semantics API.
     *
     * When the other API is tried next (application type unknown or changed), the failure is
     * expected: it is logged as an information, not as a warning.
     *
     * @param array<string, mixed> $context
     */
   private function logFailedAttempt(string $message, array $context, bool $otherApiTriedNext): void {
      if ($otherApiTriedNext) {
          PluginWikitsemanticsLogger::info($message . ', trying the other API (classic or workflow)', $context);
          return;
      }

       PluginWikitsemanticsLogger::warning($message, $context);
   }

    /**
     * Whether an HTTP error may mean that the application is not of the type of the API called:
     * the classic API answers with an error 500 for a workflow application. Authentication and
     * rate limit errors are excluded.
     */
   private function isWrongAppTypeStatus(int $httpCode): bool {
       return $httpCode >= 400 && !in_array($httpCode, [401, 403, 429], true);
   }

    /**
     * Full URL of a Wikit Semantics API endpoint
     *
     * @param string $path Endpoint path, starting with a slash
     */
   private function getAPIUrl(string $path): string {
       return rtrim($this->getStringField('url_api'), '/') . $path;
   }

    /**
     * cURL handle for a call to the Wikit Semantics API: authentication headers, HTTPS only,
     * GLPI proxy
     *
     * @param string $url     Full URL of the endpoint
     * @param int    $timeout Maximum duration of the call, in seconds
     * @return \CurlHandle
     */
   private function initCurl(string $url, int $timeout) {
       global $CFG_GLPI;

       // Decrypt API key before use
       $api_key = $this->getStringField('api_key');
      if ($api_key !== '' && strpos($api_key, 'encrypted:') === 0) {
          $api_key = (new GLPIKey())->decrypt(substr($api_key, 10));
      }

       $ch = curl_init();
       curl_setopt($ch, CURLOPT_HTTPHEADER, [
           'Content-Type: application/json',
           'Wikit-Semantics-API-Key: ' . $api_key,
           'X-Wikit-Response-Format: json',
           'X-Wikit-Organization-Id: ' . $this->getStringField('organization_id'),
       ]);
       curl_setopt($ch, CURLOPT_URL, $url);
       curl_setopt($ch, CURLOPT_HEADER, 0);
       curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
       curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
       curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
       curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
       curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

       // Enable the use of a proxy server
      if (!empty($CFG_GLPI["proxy_name"])) {
          curl_setopt($ch, CURLOPT_PROXY, $CFG_GLPI["proxy_name"] . ":" . $CFG_GLPI["proxy_port"]);
         if (!empty($CFG_GLPI["proxy_user"])) {
             curl_setopt(
                 $ch,
                 CURLOPT_PROXYUSERPWD,
                 $CFG_GLPI["proxy_user"] . ":" . (new GLPIKey())->decrypt($CFG_GLPI["proxy_passwd"])
             );
         }
      }

       return $ch;
   }

    /**
     * Execute API call to Wikit Semantics
     *
     * @param string $url API endpoint path
     * @param array $dataToPost Data to send in POST
     * @param string $method HTTP method (POST or GET)
     * @param bool $logHttpErrors Whether HTTP errors are logged (the caller may log them itself)
     * @return array Array containing 'httpCode', 'data', and optionally 'error'
     */
   public function APICall($url, $dataToPost = null, $method = 'POST', $logHttpErrors = true) {
      if ($method === 'POST' && !isset($dataToPost['query'])) {
          $dataToPost = ['query' => 'Il fait beau aujourd\'hui ?'];
      }

       $url = $this->getAPIUrl($url);
       $ch = $this->initCurl($url, 30);
       curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

      if ($method === 'POST') {
          curl_setopt($ch, CURLOPT_POST, 1);
         if (isset($dataToPost)) {
             curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dataToPost));
         }
      }

       $data = curl_exec($ch);
       $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

       // Handle cURL errors
      if ($data === false) {
          $error = curl_error($ch);
          PluginWikitsemanticsLogger::error("API call failed (cURL)", ['error' => $error, 'url' => $url]);
          return ['httpCode' => 0, 'data' => null, 'error' => $error];
      }

      if ($httpCode >= 400) {
         if ($logHttpErrors) {
             PluginWikitsemanticsLogger::warning("API returned HTTP error", ['http_code' => $httpCode, 'url' => $url]);
         }
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

       $dataToPost['query'] = self::htmlToMarkdown($dataToPost['query']);

       return $dataToPost;
   }

    /**
     * Convert rich HTML content from GLPI into readable Markdown/plain text.
     *
     * @param string $content HTML or text content
     * @return string Markdown/plain text content
     */
   public static function htmlToMarkdown($content) {
       $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
       $content = str_replace(["\r\n", "\r"], "\n", $content);

      if (preg_match('/<[a-z][\s\S]*>/i', $content)) {
          $content = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $content);
          $content = preg_replace('/<br\s*\/?>/i', "\n", $content);
          $content = preg_replace_callback('/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', function($matches) {
              return "\n\n" . str_repeat('#', (int)$matches[1]) . ' ' . trim(strip_tags($matches[2])) . "\n\n";
          }, $content);
          $content = preg_replace('/<li\b[^>]*>/i', "\n- ", $content);
          // Each item starts on a new line: no empty line between the items
          $content = preg_replace('/<\/li>/i', '', $content);
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
