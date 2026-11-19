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
use Glpi\Application\View\TemplateRenderer;
use Glpi\Toolbox\HttpClient;
use GLPIKey;
use Psr\SimpleCache\CacheInterface;
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

    /**
     * Maximum duration of a streamed API call, in seconds
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
     * Lifetime of the application types kept in the GLPI cache, in seconds
     */
    public const APP_TYPE_CACHE_TTL = 86400;

    /**
     * Application types already known during this request, by cache key
     *
     * @var array<string, string>
     */
    private array $appTypes = [];

    public bool $dohistory = true;

    /**
     * GLPI cache key of the settings used on every page, see `getPageSettings()`
     */
    private const string PAGE_SETTINGS_CACHE_KEY = 'wikitsemantics_page_settings';

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

        if ($config === null) {
            $config = new self();
            $config->getFromDB(1);
        } elseif ($update) {
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
    public static function getPageSettings(): array
    {
        /** @var CacheInterface $GLPI_CACHE */
        global $GLPI_CACHE;

        $settings = $GLPI_CACHE->get(self::PAGE_SETTINGS_CACHE_KEY);
        if (
            is_array($settings)
            && is_bool($settings['is_editor_ai_enabled'] ?? null)
            && is_bool($settings['is_streaming_enabled'] ?? null)
        ) {
            return ['is_editor_ai_enabled' => $settings['is_editor_ai_enabled'], 'is_streaming_enabled' => $settings['is_streaming_enabled']];
        }

        $config = self::getConfig(true);
        $settings = [
            'is_editor_ai_enabled' => !empty($config->fields['is_editor_ai_enabled']) && $config->getStringField('app_id_editor') !== '',
            'is_streaming_enabled' => !empty($config->fields['is_streaming_enabled']),
        ];
        $GLPI_CACHE->set(self::PAGE_SETTINGS_CACHE_KEY, $settings, self::APP_TYPE_CACHE_TTL);

        return $settings;
    }

    public function post_addItem()
    {
        parent::post_addItem();
        $this->clearPageSettingsCache();
    }

    public function post_updateItem($history = true)
    {
        parent::post_updateItem($history);
        $this->clearPageSettingsCache();
    }

    private function clearPageSettingsCache(): void
    {
        /** @var CacheInterface $GLPI_CACHE */
        global $GLPI_CACHE;

        $GLPI_CACHE->delete(self::PAGE_SETTINGS_CACHE_KEY);
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
     * Classic applications answer with JSON, workflow applications always answer with a stream
     * of events which is read entirely. See `getAppTypesToTry()` for the choice of the API.
     *
     * @param array|null $dataToPost Data containing the query
     * @param string $appIdField Config field name for the app ID
     * @return array|false Array with 'answer' and 'queryId' keys, or false on error
     */
    public function getAPIAnswer($dataToPost = null, $appIdField = 'app_id_answer')
    {
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
     * @param array<mixed>|null $dataToPost  Data containing the query
     * @param string            $appIdField  Config field name for the app ID
     * @param bool              $hasFallback Whether the workflow API will be tried after an error
     * @return array{0: array<string, mixed>|false, 1: bool} Answer (false on error), and whether the
     *                                                       error may mean that the application is a workflow
     */
    private function getClassicAnswer($dataToPost, string $appIdField, bool $hasFallback): array
    {
        $url = $this->getAppPath(self::APP_TYPE_SEMANTICS, $appIdField, '/query-executions');
        $return = $this->APICall($url, $dataToPost, 'POST', false);

        if ($return['httpCode'] == 200) {
            return [[
                'answer'  => $return['data']['answer'] ?? '',
                'queryId' => $return['data']['queryId'] ?? null,
            ], false];
        }

        $mayBeWrongType = is_int($return['httpCode']) && $this->isWrongAppTypeStatus($return['httpCode']);
        if ($return['httpCode'] !== 0) {
            $this->logFailedAttempt('API returned HTTP error', ['http_code' => $return['httpCode'], 'url' => $url], $mayBeWrongType && $hasFallback);
        }

        return [false, $mayBeWrongType];
    }

    /**
     * Get the complete answer of a workflow application, by reading its whole stream of events
     *
     * @param array<mixed>|null $dataToPost  Data containing the query
     * @param string            $appIdField  Config field name for the app ID
     * @param bool              $hasFallback Whether the classic API will be tried after an error
     * @return array{0: array{answer: string, queryId: string|null}|false, 1: bool} Answer (false on error),
     *                                                       and whether the error may mean that the application is a classic one
     */
    private function getWorkflowAnswer($dataToPost, string $appIdField, bool $hasFallback): array
    {
        if (!isset($dataToPost['query'])) {
            $dataToPost = ['query' => "Il fait beau aujourd'hui ?"];
        }

        $url = '';
        try {
            $url = $this->getAPIUrl($this->getAppPath(self::APP_TYPE_WORKFLOW, $appIdField, '/query-executions'));
            $content = $this->getHttpClient()->request('POST', $url, [
                'headers'      => $this->getAPIHeaders(),
                'json'         => $dataToPost,
                'max_duration' => self::STREAM_MAX_DURATION,
            ])->getContent();
        } catch (HttpExceptionInterface $e) {
            $httpCode = $e->getResponse()->getStatusCode();
            $mayBeWrongType = $this->isWrongAppTypeStatus($httpCode);
            $this->logFailedAttempt('API returned HTTP error', ['http_code' => $httpCode, 'url' => $url], $mayBeWrongType && $hasFallback);
            return [false, $mayBeWrongType];
        } catch (TransportExceptionInterface|RuntimeException $e) {
            Logger::error("API call failed", ['error' => $e->getMessage(), 'url' => $url]);
            return [false, false];
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

        return [['answer' => $answer, 'queryId' => $queryId], false];
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
        // The quoted sources are not available for the workflow applications
        if ($this->getAppType($appIdField) === self::APP_TYPE_WORKFLOW) {
            return [];
        }

        $url = $this->getAppPath(self::APP_TYPE_SEMANTICS, $appIdField, '/query-execution-sources/' . urlencode($queryId) . '/get-quoted-sources');

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
     * See `getAppTypesToTry()` for the choice of the API: nothing is sent before the application
     * answers with some text, so that the other API can still be tried.
     *
     * @param array $dataToPost Data containing the query
     * @param string $appIdField Config field name for the app ID
     * @return void
     *
     * @throws RuntimeException When the API cannot be reached, returns an HTTP error or an empty answer
     */
    public function streamAPIAnswer($dataToPost, $appIdField = 'app_id_answer')
    {
        if (!isset($dataToPost['query'])) {
            $dataToPost = ['query' => 'Hello ! Je suis un test venant du plugin GLPI !'];
        }

        $dataToPost = $this->cleanDataToPostQuery($dataToPost) ?? [];
        $appTypes = $this->getAppTypesToTry($appIdField);
        $lastAppType = end($appTypes);

        try {
            foreach ($appTypes as $appType) {
                try {
                    if ($this->streamFrom($appType, $appIdField, $dataToPost)) {
                        $this->rememberAppType($appIdField, $appType);
                        return;
                    }

                    // Empty answer: the workflow gateway answers this way when the application is not a workflow
                    $this->logFailedAttempt('API returned an empty answer', ['app_type' => $appType], $appType !== $lastAppType);
                } catch (HttpExceptionInterface $e) {
                    $httpCode = $e->getResponse()->getStatusCode();
                    if ($appType === $lastAppType || !$this->isWrongAppTypeStatus($httpCode)) {
                        throw $e;
                    }

                    $this->logFailedAttempt('API returned HTTP error', ['http_code' => $httpCode, 'app_type' => $appType], true);
                }
            }
        } catch (HttpExceptionInterface $e) {
            $httpCode = $e->getResponse()->getStatusCode();
            Logger::warning("API returned HTTP error", ['http_code' => $httpCode, 'url' => $e->getResponse()->getInfo('url')]);
            throw new RuntimeException(sprintf('Wikit Semantics API returned HTTP %d', $httpCode), 0, $e);
        } catch (TransportExceptionInterface $e) {
            Logger::error("Streaming API call failed", ['error' => $e->getMessage()]);
            throw new RuntimeException('Streaming API call failed: ' . $e->getMessage(), 0, $e);
        }

        throw new RuntimeException('Wikit Semantics API returned an empty answer');
    }

    /**
     * Stream the answer of an application as SSE events
     *
     * @param string               $appType    One of the `APP_TYPE_*` constants
     * @param string               $appIdField Config field name for the app ID
     * @param array<mixed>         $dataToPost Data containing the query
     * @return bool Whether at least one chunk of text was sent
     *
     * @throws HttpExceptionInterface|TransportExceptionInterface
     */
    private function streamFrom(string $appType, string $appIdField, array $dataToPost): bool
    {
        // Workflow applications always stream: the stream mode parameter is only needed by the classic ones
        $suffix = $appType === self::APP_TYPE_SEMANTICS ? '/query-executions?is_stream_mode=true' : '/query-executions';
        $url = $this->getAPIUrl($this->getAppPath($appType, $appIdField, $suffix));
        $client = $this->getHttpClient();

        $response = $client->request('POST', $url, [
            'headers'      => $this->getAPIHeaders(),
            'json'         => $dataToPost,
            'buffer'       => false,
            // A generated answer or article can take longer than the default 30 seconds
            'max_duration' => self::STREAM_MAX_DURATION,
        ]);

        // Throws for an HTTP error status (the GLPI client already does it on request)
        $response->getHeaders();

        // Process complete chunks ending with STOP as soon as they are received
        $buffer = '';
        $queryId = null;
        $hasText = false;
        foreach ($client->stream($response) as $chunk) {
            if ($chunk->isTimeout()) {
                continue;
            }

            $buffer .= $chunk->getContent();
            $hasText = $this->sendStreamedChunks($buffer, $queryId) || $hasText;
        }

        return $hasText;
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
    private function sendStreamedChunks(string &$buffer, ?string &$queryId): bool
    {
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
     * @return list<array{chunk: string, queryId?: string}>
     */
    private function extractStreamEvents(string &$buffer): array
    {
        $events = [];
        // Earliest delimiter: "}STOP" (the closing brace is kept) or "STOP" before the next event
        while (preg_match('/^(.*?)(\}STOP|STOP(?=\s*data:))/s', $buffer, $matches) === 1) {
            $chunk = ($matches[1] ?? '') . (($matches[2] ?? '') === '}STOP' ? '}' : '');
            $buffer = substr($buffer, strlen($matches[0] ?? '')); // Remove processed chunk + delimiter

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
    private function getAppPath(string $appType, string $appIdField, string $suffix): string
    {
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
     * @return list<string> `APP_TYPE_*` constants
     */
    private function getAppTypesToTry(string $appIdField): array
    {
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
    protected function getAppType(string $appIdField): ?string
    {
        /** @var CacheInterface $GLPI_CACHE */
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
    private function rememberAppType(string $appIdField, string $appType): void
    {
        /** @var CacheInterface $GLPI_CACHE */
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

    private function getAppTypeCacheKey(string $appId): string
    {
        return 'wikitsemantics_app_type_' . sha1($this->getStringField('url_api') . '|' . $appId);
    }

    /**
     * Value of a configuration field as a string, empty when missing or not scalar
     */
    private function getStringField(string $name): string
    {
        $value = $this->fields[$name] ?? null;

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Log a failed call to the Wikit Semantics API.
     *
     * When the other API is tried next (application type unknown or changed), the failure is
     * expected: it is logged as an information, not as a warning.
     *
     * @param array<string, mixed> $context
     */
    private function logFailedAttempt(string $message, array $context, bool $otherApiTriedNext): void
    {
        if ($otherApiTriedNext) {
            Logger::info($message . ', trying the other API (classic or workflow)', $context);
            return;
        }

        Logger::warning($message, $context);
    }

    /**
     * Whether an HTTP error may mean that the application is not of the type of the API called:
     * the classic API answers with an error 500 for a workflow application. Authentication and
     * rate limit errors are excluded.
     */
    private function isWrongAppTypeStatus(int $httpCode): bool
    {
        return $httpCode >= 400 && !in_array($httpCode, [401, 403, 429], true);
    }

    /**
     * Execute API call to Wikit Semantics
     *
     * @param string $url API endpoint URL
     * @param array $dataToPost Data to send in POST
     * @param bool $logHttpErrors Whether HTTP errors are logged (the caller may log them itself)
     * @return array Array containing 'httpCode', 'data', and optionally 'error'
     */
    public function APICall(string $url, ?array $dataToPost = null, string $method = 'POST', bool $logHttpErrors = true): array
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
            if ($logHttpErrors) {
                Logger::warning("API returned HTTP error", ['http_code' => $httpCode, 'url' => $url]);
            }

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

        // Same check as `prepareInputForUpdate()`, which accepts an uppercase scheme
        if (!is_string($scheme) || strtolower($scheme) !== 'https') {
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

        $dataToPost['query'] = self::htmlToMarkdown($dataToPost['query']);

        return $dataToPost;
    }

    /**
     * Convert rich HTML content from GLPI into readable Markdown/plain text.
     *
     * @param string $content HTML or text content
     * @return string Markdown/plain text content
     */
    public static function htmlToMarkdown(string $content): string
    {
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        if (preg_match('/<[a-z][\s\S]*>/i', $content)) {
            $content = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $content);
            $content = preg_replace('/<br\s*\/?>/i', "\n", $content);
            $content = preg_replace_callback('/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', fn($matches) => "\n\n" . str_repeat('#', (int) $matches[1]) . ' ' . trim(strip_tags($matches[2])) . "\n\n", $content);
            $content = preg_replace('/<li\b[^>]*>/i', "\n- ", $content);
            // Each item starts on a new line: no empty line between the items
            $content = preg_replace('/<\/li>/i', '', $content);
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
