<?php
/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2025 by the Wikit Development Team.
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

    public function __construct()
    {
        /** @var \DBmysql $DB */
        global $DB;
        if ($DB->tableExists(self::getTable())) {
            $this->getFromDB(1);
        }
    }

    public static function canView(): bool
    {
        return Session::haveRight(self::$rightname, READ);
    }

    public static function canCreate(): bool
    {
        return Session::haveRight(self::$rightname, UPDATE);
    }

    /**
     * Prepare input before adding to database
     *
     * @param array $input
     * @return array
     */
    public function prepareInputForAdd($input)
    {
        return $this->prepareInputForUpdate($input);
    }

    /**
     * Prepare input before updating database
     * Encrypt API key before storing
     *
     * @param array $input
     * @return array
     */
    public function prepareInputForUpdate($input)
    {
        // Encrypt API key if provided and not already encrypted
        if (isset($input['api_key']) && !empty($input['api_key'])) {
            // If user didn't change the masked value, keep the existing encrypted key
            if ($input['api_key'] === '••••••••••••••••') {
                unset($input['api_key']); // Don't update
            } elseif (strpos($input['api_key'], 'encrypted:') !== 0) {
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
    public static function getConfig($update = false)
    {
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
    public static function getTypeName($nb = 0)
    {
        return __("Configuration API", "wikitsemantics");
    }

    /**
     * Define tabs to display on the form page
     *
     * @param array $options Options
     * @return array Array of tabs
     * @see CommonGLPI::defineTabs()
     */
    public function defineTabs($options = [])
    {
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
    public function isNewItem()
    {
        return false;
    }

    /**
     * Display configuration form
     *
     * @param int $ID Configuration ID
     * @param array $options Additional options
     * @return bool
     */
    public function showForm($ID, array $options = [])
    {
        Session::checkRight(self::$rightname, READ);
        $this->getFromDB($ID);

        $options['candel'] = false;
        $options['colspan'] = 1;

        $this->showFormHeader($options);

        echo "<div class='center'>";
        echo "<form name='form' method='post' action='" . $this->getFormURL() . "'>";

        echo Html::hidden('id', ['value' => 1]);

        echo "<table class='wikitsemantics tab_cadre_fixe'>";

        /* Column title (Wikitsemantics configuration) */
        echo "<tr><th colspan='2' style='text-align: center;'>" . __(
            "Wikitsemantics configuration",
            "wikitsemantics"
        ) . "</th></tr>";

        /* API URL input field */
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __("Wikit Semantics URL base", "wikitsemantics") . "</td>";
        echo "<td>";
        echo Html::input('url_api', ['value' => $this->fields['url_api'], 'size' => 100]);
        echo "</td>";
        echo "</tr>";

        /* Organization ID input field */
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __("Organization ID", "wikitsemantics") . "</td>";
        echo "<td>";
        echo Html::input('organization_id', ['value' => $this->fields['organization_id'], 'size' => 100]);
        echo "</td>";
        echo "</tr>";

        /* Application ID input field */
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __("Application ID", "wikitsemantics") . "</td>";
        echo "<td>";
        echo Html::input('app_id', ['value' => $this->fields['app_id'], 'size' => 100]);
        echo "</td>";
        echo "</tr>";

        /* API key input field */
        echo "<tr class='tab_bg_1' >";
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

        /* Streaming mode checkbox */
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __("Enable Streaming Mode", "wikitsemantics") . "</td>";
        echo "<td>";
        $is_streaming = isset($this->fields['is_streaming_enabled']) ? $this->fields['is_streaming_enabled'] : 0;

        echo "<input type='hidden' name='is_streaming_enabled' value='0'>";
        echo "<input type='checkbox' name='is_streaming_enabled' value='1' " . ($is_streaming ? "checked" : "") . ">";
        echo "<span class='ms-2 text-muted'>" . __('Stream responses token by token', 'wikitsemantics') . "</span>";
        echo "</td>";
        echo "</tr>";

        if (isset($this->fields['url_api']) && !empty($this->fields['url_api'])) {
            echo "<tr class='tab_bg_1'>";

            echo "<td colspan='2' class='center'>";
            echo "   <button class=\"btn btn-outline-secondary me-2\" type=\"submit\" name=\"TestConnection\" value=\"1\">
                     <i class=\"fas fa-circle-check\"></i>
                    <span>" . __('Test connection', 'wikitsemantics') . "</span>
                 </button>";

            echo "</td>";
            echo "</tr>";
        }

        /* Submit button row */
        echo "<tr class='tab_bg_1' >";
        echo "<td colspan='2' style='text-align: center;'>";
        echo "</td>";
        echo "</tr>";

        echo "</table>";
        echo "</div>";


        $this->showFormButtons($options);

        return true;
    }

    /**
     * Test connection to Wikit Semantics API
     *
     * @param array|null $dataToPost Optional data to post (for testing specific query)
     * @return bool|string Boolean on UI test, string answer on direct call
     */
    public function testConnection($dataToPost = null)
    {
        $jwtToken = $this->getAPIAnswer($dataToPost);
        if (!$jwtToken) {
            if (!$dataToPost) {
                Session::addMessageAfterRedirect(__('Connection Failed', 'wikitsemantics'), false, ERROR);
            }
            return false;
        } else {
            if (!$dataToPost) {
                Session::addMessageAfterRedirect(__('Connection Successful', 'wikitsemantics'), false);
                return true;
            }
            return $jwtToken;
        }
    }

    /**
     * Get AI answer from Wikit Semantics API
     *
     * @param array|null $dataToPost Data containing the query
     * @return string|bool Answer from API or false on error
     */
    public function getAPIAnswer($dataToPost = null)
    {
        $url = '/semantics/apps/' . $this->fields['app_id'] . '/query-executions';

        $return = $this->APICall($url, $dataToPost);

        if ($return['httpCode'] == 200) {
            return $return['data']['answer'];
        } else {
            return false;
        }
    }

    /**
     * Stream AI answer from Wikit Semantics API using SSE
     * Outputs Server-Sent Events directly to the response stream
     *
     * @param array $dataToPost Data containing the query
     * @return void
     */
    public function streamAPIAnswer($dataToPost)
    {
        global $CFG_GLPI;

        if (!isset($dataToPost['query'])) {
            $dataToPost = ['query' => 'Hello ! Je suis un test venant du plugin GLPI !'];
        }

        $proxy_host  = !empty($CFG_GLPI["proxy_name"]) ? ($CFG_GLPI["proxy_name"] . ":" . $CFG_GLPI["proxy_port"]) : false;
        $proxy_ident = !empty($CFG_GLPI["proxy_user"]) ? ($CFG_GLPI["proxy_user"] . ":" .
            (new GLPIKey())->decrypt($CFG_GLPI["proxy_passwd"])) : false;

        // Build URL with streaming parameter
        $url = '/semantics/apps/' . $this->fields['app_id'] . '/query-executions?is_stream_mode=true';

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
                            // Send SSE event with the chunk
                            echo "event: chunk\n";
                            echo "data: " . json_encode(['chunk' => $decoded['chunk']]) . "\n\n";

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
            curl_close($ch);
            throw new Exception("Streaming API call failed: " . $error);
        }

        curl_close($ch);
    }

    /**
     * Execute API call to Wikit Semantics
     *
     * @param string $url API endpoint URL
     * @param array $dataToPost Data to send in POST
     * @return array Array containing 'httpCode', 'data', and optionally 'error'
     */
    public function APICall($url, $dataToPost)
    {
        global $CFG_GLPI;

        if (!isset($dataToPost['query'])) {
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
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        // Enable the use of a proxy server
        if (!empty($CFG_GLPI["proxy_name"])) {
            // Set the proxy address
            curl_setopt($ch, CURLOPT_PROXY, $proxy_host);

            // Set credentials if the proxy requires authentication
            if ($proxy_ident) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_ident);
            }
        }
        if (isset($dataToPost)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dataToPost));
        }

        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Handle cURL errors
        if ($data === false) {
            $error = curl_error($ch);
            curl_close($ch);
            Toolbox::logError("Wikit Semantics API Call failed: " . $error);
            return ['httpCode' => 0, 'data' => null, 'error' => $error];
        }

        curl_close($ch);

        return ['httpCode' => $httpCode, 'data' => json_decode($data, 1)];
    }
}
