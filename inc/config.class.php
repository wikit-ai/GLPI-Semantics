<?php
/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2025 by the Wikit Development Team.
 * -------------------------------------------------------------------------
 */
$GLPI_TYPES = [];

/* Since there is a table in the MySQL database that corresponds to the class name, we can access the fields as follows:
 $this->fields['field_name_in_table']
table : glpi_plugin_wikitsemantics_configs
class: PluginWikitsemanticsConfig
*/

class PluginWikitsemanticsConfig extends CommonDBTM
{
    public static $rightname = 'config';
    public $dohistory = true;

    public function __construct()
    {
        /** @var \DBmysql $DB */
        global $DB;
        if ($DB->tableExists(self::getTable())) {
            $this->getFromDB(1);
        }
    }

    public static function canView()
    {
        return Session::haveRight('config', READ);
    }

    public static function canCreate()
    {
        return Session::haveRight('config', UPDATE);
    }

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

    public static function getTypeName($nb = 0)
    {
        return __("Configuration API", "wikitsemantics");
    }

    /**
     * @see CommonGLPI::defineTabs()
     */
    public function defineTabs($options = [])
    {
        $ong = [];

        // Standard tabs related to other classes or your own
        $this->addStandardTab(PluginWikitSemanticsConfig::class, $ong, $options);
        $this->addStandardTab(__CLASS__, $ong, $options);
        return $ong;
    }

    /* For the tabs to display, this method must be present and return "false" */
    public function isNewItem()
    {
        return false;
    }

    public function showForm($ID, array $options = [])
    {
        Session::checkRight("plugin_wikitsemantics_configs", READ);
        $this->getFromDB($ID);

        //The configuration is not deletable
        $options['candel'] = false;
        $options['colspan'] = 1;

        $this->showFormHeader($options);

        /** First form : MDM configuration */

        //echo '<link rel="stylesheet" type="text/css" href="../css/style.css"/>';
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
        echo Html::input('api_key', ['value' => $this->fields['api_key'], 'size' => 100]);
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

    public function APICall($url, $dataToPost)
    {
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

        $header =
            [
                'Content-Type: application/json',
                'Wikit-Semantics-API-Key: ' . $this->fields['api_key'],
                'X-Wikit-Response-Format: json',
                'X-Wikit-Organization-Id: ' . $this->fields['organization_id'],
            ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
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
        curl_close($ch);

        return ['httpCode' => $httpCode, 'data' => json_decode($data, 1)];
    }
}
