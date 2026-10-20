<?php

/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Wikit Semantics.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2026 by Wikit.
 * @license   Apache-2.0 https://www.apache.org/licenses/LICENSE-2.0
 * @link      https://github.com/wikit-ai/GLPI-Semantics
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Wikitsemantics;

use CommonDBTM;
use DBmysql;
use Glpi\Application\View\TemplateRenderer;
use Glpi\Toolbox\HttpClient;
use GLPIKey;
use RuntimeException;
use Safe\Exceptions\JsonException;
use Safe\Exceptions\UrlException;
use Session;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Safe\json_decode;
use function Safe\json_encode;
use function Safe\ob_flush;
use function Safe\parse_url;
use function Safe\preg_match;
use function Safe\preg_replace;
use function Safe\preg_replace_callback;

/**
 * Configuration class for Wikit Semantics plugin
 *
 * Database table: glpi_plugin_wikitsemantics_configs
 * Class: Config
 */
class Config extends CommonDBTM
{
    public static string $rightname = 'plugin_wikitsemantics_configs';

    public bool $dohistory = true;

    public function __construct()
    {
        /** @var DBmysql $DB */
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
     * @return array|false
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
     * @return array|false
     */
    public function prepareInputForUpdate($input)
    {
        // Only accept a well-formed HTTPS URL for the API
        if (isset($input['url_api'])) {
            $input['url_api'] = trim($input['url_api']);
            try {
                $scheme = parse_url($input['url_api'], PHP_URL_SCHEME);
                $host = parse_url($input['url_api'], PHP_URL_HOST);
            } catch (UrlException) {
                $scheme = null;
                $host = null;
            }

            if (!is_string($scheme) || strtolower($scheme) !== 'https' || empty($host)) {
                Session::addMessageAfterRedirect(
                    __('The API URL must be a valid HTTPS URL.', 'wikitsemantics'),
                    false,
                    ERROR,
                );
                return false;
            }
        }

        // Encrypt API key if provided and not already encrypted
        if (isset($input['api_key']) && !empty($input['api_key'])) {
            // If user didn't change the masked value, keep the existing encrypted key
            if ($input['api_key'] === '••••••••••••••••') {
                unset($input['api_key']); // Don't update
            } elseif (!str_starts_with($input['api_key'], 'encrypted:')) {
                // Don't re-encrypt if already encrypted
                $input['api_key'] = 'encrypted:' . (new GLPIKey())->encrypt($input['api_key']);
            }
        }

        return $input;
    }

    /**
     * Web path of the plugin, e.g. `/glpi/plugins/wikitsemantics`
     */
    public static function getPluginWebPath(): string
    {
        /** @var array $CFG_GLPI */
        global $CFG_GLPI;

        return $CFG_GLPI['root_doc'] . '/plugins/wikitsemantics';
    }

    /**
     * Get configuration instance
     *
     * @param bool $update Whether to refresh from database
     * @return self Configuration instance
     */
    public static function getConfig($update = false): self
    {
        /** @var self|null $config */
        static $config = null;
        $config ??= new self();

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
        $this->addStandardTab(Config::class, $ong, $options);
        $this->addStandardTab(self::class, $ong, $options);
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

        // Never display the stored API key, even encrypted
        $apiKeyDisplay = $this->fields['api_key'] ?? '';
        if ($apiKeyDisplay !== '' && str_starts_with($apiKeyDisplay, 'encrypted:')) {
            $apiKeyDisplay = '••••••••••••••••';
        }

        TemplateRenderer::getInstance()->display('@wikitsemantics/config.html.twig', [
            'item'            => $this,
            'params'          => $options,
            'api_key_display' => $apiKeyDisplay,
        ]);

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
        // Direct call from ticket — test single app
        if ($dataToPost) {
            $result = $this->getAPIAnswer($dataToPost);
            return $result ? (string) $result['answer'] : false;
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
                    WARNING,
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
                    ERROR,
                );
            } else {
                Session::addMessageAfterRedirect(
                    sprintf(__('%s: connection successful', 'wikitsemantics'), $app['label']),
                    false,
                );
            }
        }

        if ($testedCount === 0) {
            Session::addMessageAfterRedirect(
                __('No application to test. Enable at least one feature and configure its Application ID.', 'wikitsemantics'),
                false,
                WARNING,
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
    public function getAPIAnswer($dataToPost = null, $appIdField = 'app_id_answer')
    {
        $url = '/semantics/apps/' . $this->fields[$appIdField] . '/query-executions';
        $dataToPost = $this->cleanDataToPostQuery($dataToPost);

        $return = $this->APICall($url, $dataToPost);

        if ($return['httpCode'] == 200) {
            return [
                'answer'  => $return['data']['answer'] ?? '',
                'queryId' => $return['data']['queryId'] ?? null,
            ];
        }

        return false;
    }

    /**
     * Get quoted sources for a query execution
     *
     * @param string $queryId The query execution ID
     * @param string $appIdField Config field name for the app ID
     * @return array|false Array of sources or false on error
     */
    public function getQuotedSources(string $queryId, string $appIdField = 'app_id_answer')
    {
        $url = '/semantics/apps/' . $this->fields[$appIdField]
            . '/query-execution-sources/' . urlencode($queryId) . '/get-quoted-sources';

        $return = $this->APICall($url, null, 'GET');

        if ($return['httpCode'] !== 200 || !is_array($return['data'])) {
            return false;
        }

        // API may wrap sources in a nested key
        $data = $return['data'];
        foreach (['quotedSources', 'data', 'sources'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $data[$key];
            }
        }

        return $data;
    }

    /**
     * Stream AI answer from Wikit Semantics API using SSE
     * Outputs Server-Sent Events directly to the response stream
     *
     * @param array $dataToPost Data containing the query
     * @param string $appIdField Config field name for the app ID
     * @return void
     *
     * @throws RuntimeException When the API cannot be reached or returns an HTTP error
     */
    public function streamAPIAnswer($dataToPost, $appIdField = 'app_id_answer')
    {
        if (!isset($dataToPost['query'])) {
            $dataToPost = ['query' => 'Hello ! Je suis un test venant du plugin GLPI !'];
        }

        $dataToPost = $this->cleanDataToPostQuery($dataToPost);

        $url = $this->getAPIUrl('/semantics/apps/' . $this->fields[$appIdField] . '/query-executions?is_stream_mode=true');
        $client = $this->getHttpClient();

        try {
            $response = $client->request('POST', $url, [
                'headers' => $this->getAPIHeaders(),
                'json'    => $dataToPost,
                'buffer'  => false,
            ]);

            // Throws for an HTTP error status (the GLPI client already does it on request)
            $response->getHeaders();

            // Process complete chunks ending with STOP as soon as they are received
            $buffer = '';
            foreach ($client->stream($response) as $chunk) {
                if ($chunk->isTimeout()) {
                    continue;
                }

                $buffer .= $chunk->getContent();
                $this->sendStreamedChunks($buffer);
            }
        } catch (HttpExceptionInterface $e) {
            $httpCode = $e->getResponse()->getStatusCode();
            Logger::warning("API returned HTTP error", ['http_code' => $httpCode, 'url' => $url]);
            throw new RuntimeException(sprintf('Wikit Semantics API returned HTTP %d', $httpCode), 0, $e);
        } catch (TransportExceptionInterface $e) {
            Logger::error("Streaming API call failed", ['error' => $e->getMessage(), 'url' => $url]);
            throw new RuntimeException('Streaming API call failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Send the complete chunks of the Wikit API stream as SSE events, and remove them from the buffer.
     *
     * The format from Wikit API is: data: {"queryId": "...", "chunk":"..."}STOP
     *
     * @param string $buffer Data received from the API and not processed yet
     */
    private function sendStreamedChunks(string &$buffer): void
    {
        while (($pos = strpos($buffer, 'STOP')) !== false) {
            $chunk = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + 4); // Remove processed chunk + "STOP"

            foreach (explode("\n", trim($chunk)) as $line) {
                $line = trim($line);

                // Look for lines starting with "data: "
                if (!str_starts_with($line, 'data:')) {
                    continue;
                }

                // Remove "data:" prefix and trim spaces
                try {
                    $decoded = json_decode(trim(substr($line, 5)), true);
                } catch (JsonException) {
                    $decoded = null;
                }

                if (!$decoded || !isset($decoded['chunk'])) {
                    continue;
                }

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

    /**
     * Execute API call to Wikit Semantics
     *
     * @param string $url API endpoint URL
     * @param array $dataToPost Data to send in POST
     * @return array Array containing 'httpCode', 'data', and optionally 'error'
     */
    public function APICall(string $url, ?array $dataToPost = null, string $method = 'POST'): array
    {
        if ($method === 'POST' && !isset($dataToPost['query'])) {
            $dataToPost = ['query' => "Il fait beau aujourd'hui ?"];
        }

        $options = ['headers' => $this->getAPIHeaders()];
        if ($method === 'POST' && isset($dataToPost)) {
            $options['json'] = $dataToPost;
        }

        try {
            $url = $this->getAPIUrl($url);
            $response = $this->getHttpClient()->request($method, $url, $options);
            $httpCode = $response->getStatusCode();
            $data = $response->getContent();
        } catch (HttpExceptionInterface $e) {
            $httpCode = $e->getResponse()->getStatusCode();
            Logger::warning("API returned HTTP error", ['http_code' => $httpCode, 'url' => $url]);
            return ['httpCode' => $httpCode, 'data' => null, 'error' => $e->getMessage()];
        } catch (TransportExceptionInterface|RuntimeException $e) {
            Logger::error("API call failed", ['error' => $e->getMessage(), 'url' => $url]);
            return ['httpCode' => 0, 'data' => null, 'error' => $e->getMessage()];
        }

        Logger::debug("API call successful", ['http_code' => $httpCode, 'url' => $url]);

        try {
            $decoded = json_decode($data, true);
        } catch (JsonException) {
            $decoded = null;
        }

        return ['httpCode' => $httpCode, 'data' => $decoded];
    }

    /**
     * HTTP client used to call Wikit Semantics.
     *
     * It uses the GLPI proxy configuration (unless the plugin is excluded from it) and refuses
     * private network addresses, unless `GlpiPlugin\Wikitsemantics\Config` is listed in the
     * `GLPI_SERVERSIDE_URL_ALLOWED_PRIVATE_NETWORKS_CONTEXTS` constant (self-hosted Wikit Semantics).
     *
     * Protected so that the tests can use a Symfony mock client instead.
     */
    protected function getHttpClient(): HttpClient|HttpClientInterface
    {
        return new HttpClient(self::class, [
            'max_connect_duration' => 10,
            'max_duration'         => 30,
        ]);
    }

    /**
     * Build the full URL of a Wikit Semantics API endpoint.
     *
     * @param string $path Endpoint path, starting with a slash
     *
     * @throws RuntimeException When the configured API URL is not an HTTPS URL
     */
    private function getAPIUrl(string $path): string
    {
        $base = rtrim($this->fields['url_api'] ?? '', '/');
        try {
            $scheme = parse_url($base, PHP_URL_SCHEME);
        } catch (UrlException) {
            $scheme = null;
        }

        if ($scheme !== 'https') {
            throw new RuntimeException('Wikit Semantics URL must be an HTTPS URL');
        }

        return $base . $path;
    }

    /**
     * Headers sent to the Wikit Semantics API.
     *
     * @return array<string, string>
     */
    private function getAPIHeaders(): array
    {
        // Decrypt API key before use
        $api_key = $this->fields['api_key'];
        if (!empty($api_key) && str_starts_with($api_key, 'encrypted:')) {
            $api_key = (new GLPIKey())->decrypt(substr($api_key, 10));
        }

        return [
            'Wikit-Semantics-API-Key' => (string) $api_key,
            'X-Wikit-Response-Format' => 'json',
            'X-Wikit-Organization-Id' => (string) $this->fields['organization_id'],
        ];
    }

    /**
     * Clean the query payload before sending it to Wikit Semantics.
     *
     * @param array|null $dataToPost Data containing the query
     * @return array|null Cleaned data
     */
    private function cleanDataToPostQuery($dataToPost)
    {
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
    private function htmlToMarkdown($content)
    {
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        if (preg_match('/<[a-z][\s\S]*>/i', $content)) {
            $content = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $content);
            $content = preg_replace('/<br\s*\/?>/i', "\n", $content);
            $content = preg_replace_callback('/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', fn($matches) => "\n\n" . str_repeat('#', (int) $matches[1]) . ' ' . trim(strip_tags($matches[2])) . "\n\n", $content);
            $content = preg_replace('/<li\b[^>]*>/i', "\n- ", $content);
            $content = preg_replace('/<\/li>/i', "\n", $content);
            $content = preg_replace('/<(strong|b)\b[^>]*>(.*?)<\/\1>/is', '**$2**', $content);
            $content = preg_replace('/<(em|i)\b[^>]*>(.*?)<\/\1>/is', '*$2*', $content);
            $content = preg_replace('/<code\b[^>]*>(.*?)<\/code>/is', '`$1`', $content);
            $content = preg_replace_callback('/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', function ($matches) {
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
