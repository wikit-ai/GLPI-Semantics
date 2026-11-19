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

namespace GlpiPlugin\Wikitsemantics\Tests\Units;

use Glpi\Tests\DbTestCase;
use GLPIKey;
use GlpiPlugin\Wikitsemantics\Config;
use GlpiPlugin\Wikitsemantics\Tests\Support\MockedConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

use function Safe\json_encode;
use function Safe\ob_end_flush;
use function Safe\ob_start;

final class ConfigTest extends DbTestCase
{
    /**
     * @param array<MockResponse>|callable $responses
     * @param array<string, mixed>         $fields
     */
    private function getMockedConfig(
        array|callable $responses,
        array $fields = [],
        ?string $appType = Config::APP_TYPE_SEMANTICS,
        bool $useAppTypeCache = false,
    ): MockedConfig {
        return MockedConfig::create(new MockHttpClient($responses), $fields + [
            'url_api'         => 'https://wikit.example.com/',
            'api_key'         => 'encrypted:' . (new GLPIKey())->encrypt('secret-key'),
            'organization_id' => 'org-1',
            'app_id_answer'   => 'app-answer',
            'app_id_kb'       => 'app-kb',
            'app_id_editor'   => 'app-editor',
        ], $appType, $useAppTypeCache);
    }

    /**
     * Stream sent by the workflow gateway: text chunks, keep-alive comment, event without text
     */
    private const string WORKFLOW_STREAM = 'data: {"conversationId": "c-1", "queryId": "q-1", "chunk": "Restart "}STOP' . "\n\n"
        . ": ping\n\n"
        . 'data: {"conversationId": "c-1", "queryId": "q-1", "metadata": {"events": []}, "chunk": ""}STOP' . "\n\n"
        . 'data: {"conversationId": "c-1", "queryId": "q-1", "chunk": "the printer"}STOP' . "\n\n";

    public function testPrepareInputRejectsNonHttpsUrl(): void
    {
        $this->assertFalse((new Config())->prepareInputForUpdate(['url_api' => 'http://wikit.example.com']));
        $this->hasSessionMessages(ERROR, ['The API URL must be a valid HTTPS URL.']);
    }

    public function testPrepareInputTrimsHttpsUrl(): void
    {
        $input = (new Config())->prepareInputForUpdate(['url_api' => ' https://wikit.example.com ']);

        $this->assertIsArray($input);
        $this->assertSame('https://wikit.example.com', $input['url_api']);
    }

    public function testPrepareInputEncryptsApiKey(): void
    {
        $input = (new Config())->prepareInputForUpdate(['api_key' => 'secret-key']);

        $this->assertIsArray($input);
        $this->assertIsString($input['api_key']);
        $this->assertStringStartsWith('encrypted:', $input['api_key']);
        $this->assertSame('secret-key', (new GLPIKey())->decrypt(substr($input['api_key'], 10)));
    }

    public function testPrepareInputKeepsStoredApiKeyWhenMasked(): void
    {
        $input = (new Config())->prepareInputForUpdate(['api_key' => '••••••••••••••••', 'organization_id' => 'org-1']);

        $this->assertSame(['organization_id' => 'org-1'], $input);
    }

    public function testPrepareInputDoesNotEncryptTwice(): void
    {
        $input = (new Config())->prepareInputForUpdate(['api_key' => 'encrypted:abc']);

        $this->assertSame(['api_key' => 'encrypted:abc'], $input);
    }

    public function testGetPluginWebPath(): void
    {
        global $CFG_GLPI;

        $root_doc = $CFG_GLPI['root_doc'];
        $this->assertIsString($root_doc);
        $this->assertSame($root_doc . '/plugins/wikitsemantics', Config::getPluginWebPath());
    }

    public function testGetApiAnswerSendsTheQuery(): void
    {
        $requests = [];
        $config = $this->getMockedConfig(function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = ['method' => $method, 'url' => $url, 'headers' => $options['headers'], 'body' => $options['body']];

            return new MockResponse('{"answer": "Restart the printer", "queryId": "q-1"}');
        });

        $result = $config->getAPIAnswer(['query' => '<p><strong>Printer</strong> is down</p>']);

        $this->assertSame(['answer' => 'Restart the printer', 'queryId' => 'q-1'], $result);
        $this->assertCount(1, $requests);
        $this->assertSame('POST', $requests[0]['method']);
        $this->assertSame('https://wikit.example.com/semantics/apps/app-answer/query-executions', $requests[0]['url']);
        $this->assertIsArray($requests[0]['headers']);
        $this->assertContains('Wikit-Semantics-API-Key: secret-key', $requests[0]['headers']);
        $this->assertContains('X-Wikit-Organization-Id: org-1', $requests[0]['headers']);
        // Rich text is converted to Markdown before being sent
        $this->assertSame('{"query":"**Printer** is down"}', $requests[0]['body']);
    }

    public function testGetApiAnswerUsesTheGivenApplication(): void
    {
        $config = $this->getMockedConfig(function (string $method, string $url): MockResponse {
            $this->assertSame('https://wikit.example.com/semantics/apps/app-kb/query-executions', $url);

            return new MockResponse('{"answer": "Article"}');
        });

        $this->assertSame(['answer' => 'Article', 'queryId' => null], $config->getAPIAnswer(['query' => 'Ticket'], 'app_id_kb'));
    }

    public function testGetApiAnswerReadsTheWorkflowStream(): void
    {
        $requests = [];
        $config = $this->getMockedConfig(function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = ['method' => $method, 'url' => $url, 'body' => $options['body']];

            return new MockResponse(self::WORKFLOW_STREAM, ['response_headers' => ['content-type' => 'text/event-stream']]);
        }, [], Config::APP_TYPE_WORKFLOW);

        $this->assertSame(
            ['answer' => 'Restart the printer', 'queryId' => 'q-1'],
            $config->getAPIAnswer(['query' => 'Printer is down']),
        );
        $this->assertCount(1, $requests);
        $this->assertSame('POST', $requests[0]['method']);
        $this->assertSame('https://wikit.example.com/semantics-workflow/apps/app-answer/query-executions', $requests[0]['url']);
        $this->assertSame('{"query":"Printer is down"}', $requests[0]['body']);
    }

    public function testGetApiAnswerFailsOnAnEmptyWorkflowStream(): void
    {
        // The workflow gateway answers with an empty stream when the execution fails, then the classic API fails too
        $config = $this->getMockedConfig([
            new MockResponse(": ping\n\n"),
            new MockResponse('Internal Server Error', ['http_code' => 500]),
        ], [], Config::APP_TYPE_WORKFLOW);

        $this->assertFalse($config->getAPIAnswer(['query' => 'Printer is down']));
    }

    /**
     * Mock of the Wikit Semantics API for an application of the given type: the classic API answers
     * with an error 500 for a workflow application, the workflow API with an empty stream for a
     * classic application (as the real APIs)
     *
     * @param list<string> $urls Called URLs
     */
    private function getMockedApiFor(string $appType, array &$urls): callable
    {
        return function (string $method, string $url) use ($appType, &$urls): MockResponse {
            $urls[] = $method . ' ' . $url;
            $isWorkflowApi = str_contains($url, '/semantics-workflow/');

            return match (true) {
                $isWorkflowApi && $appType === Config::APP_TYPE_WORKFLOW   => new MockResponse(self::WORKFLOW_STREAM),
                $isWorkflowApi                                              => new MockResponse('', ['http_code' => 201]),
                $appType === Config::APP_TYPE_SEMANTICS                     => new MockResponse('{"answer": "Restart the printer", "queryId": "q-1"}'),
                default                                                     => new MockResponse('Internal Server Error', ['http_code' => 500]),
            };
        };
    }

    public function testWorkflowAppIsDetectedAndRemembered(): void
    {
        $appId = 'app-workflow-' . mt_rand();
        $urls = [];
        $config = $this->getMockedConfig($this->getMockedApiFor(Config::APP_TYPE_WORKFLOW, $urls), ['app_id_answer' => $appId], null, true);

        $this->assertSame('Restart the printer', $config->getAPIAnswer(['query' => 'Printer is down'])['answer'] ?? null);
        // The type is kept: the workflow API is called directly
        $this->assertSame('Restart the printer', $config->getAPIAnswer(['query' => 'Printer is still down'])['answer'] ?? null);
        $this->assertSame([
            'POST https://wikit.example.com/semantics/apps/' . $appId . '/query-executions',
            'POST https://wikit.example.com/semantics-workflow/apps/' . $appId . '/query-executions',
            'POST https://wikit.example.com/semantics-workflow/apps/' . $appId . '/query-executions',
        ], $urls);
    }

    public function testClassicAppIsCalledDirectly(): void
    {
        $appId = 'app-classic-' . mt_rand();
        $urls = [];
        $config = $this->getMockedConfig($this->getMockedApiFor(Config::APP_TYPE_SEMANTICS, $urls), ['app_id_answer' => $appId], null, true);

        $this->assertSame(['answer' => 'Restart the printer', 'queryId' => 'q-1'], $config->getAPIAnswer(['query' => 'Printer is down']));
        $this->assertSame(['POST https://wikit.example.com/semantics/apps/' . $appId . '/query-executions'], $urls);
    }

    public function testAppTypeChangeIsDetected(): void
    {
        // Known as a workflow, the application is now a classic one
        $urls = [];
        $config = $this->getMockedConfig($this->getMockedApiFor(Config::APP_TYPE_SEMANTICS, $urls), [], Config::APP_TYPE_WORKFLOW);

        $this->assertSame(['answer' => 'Restart the printer', 'queryId' => 'q-1'], $config->getAPIAnswer(['query' => 'Printer is down']));
        $this->assertCount(2, $urls);
        $this->assertStringContainsString('/semantics-workflow/apps/', $urls[0]);
        $this->assertStringContainsString('/semantics/apps/', $urls[1]);
    }

    public function testNoFallbackToWorkflowOnAuthenticationError(): void
    {
        $config = $this->getMockedConfig([
            new MockResponse('{"message": "Unauthorized"}', ['http_code' => 401]),
        ], [], null);

        $this->assertFalse($config->getAPIAnswer(['query' => 'Printer is down']));
    }

    public function testWorkflowAppsHaveNoQuotedSources(): void
    {
        // No request is expected: the mock client has no response
        $this->assertSame([], $this->getMockedConfig([], [], Config::APP_TYPE_WORKFLOW)->getQuotedSources('q-1'));
    }

    public function testApiCallReportsHttpErrors(): void
    {
        $config = $this->getMockedConfig([new MockResponse('{"message": "Unauthorized"}', ['http_code' => 401])]);

        $result = $config->APICall('/semantics/apps/app-answer/query-executions', ['query' => 'Ticket']);

        $this->assertSame(401, $result['httpCode']);
        $this->assertNull($result['data']);
        $this->assertFalse($this->getMockedConfig([new MockResponse('', ['http_code' => 500])])->getAPIAnswer(['query' => 'Ticket']));
    }

    public function testApiCallReportsTransportErrors(): void
    {
        $config = $this->getMockedConfig([new MockResponse('', ['error' => 'Connection refused'])]);

        $result = $config->APICall('/semantics/apps/app-answer/query-executions', ['query' => 'Ticket']);

        $this->assertSame(0, $result['httpCode']);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('Connection refused', $result['error']);
    }

    public function testApiCallRefusesNonHttpsUrl(): void
    {
        $config = $this->getMockedConfig(function (): never {
            $this->fail('No request must be sent to a non HTTPS URL');
        }, ['url_api' => 'http://wikit.example.com']);

        $result = $config->APICall('/semantics/apps/app-answer/query-executions', ['query' => 'Ticket']);

        $this->assertSame(0, $result['httpCode']);
        $this->assertIsString($result['error']);
        $this->assertStringContainsString('HTTPS', $result['error']);
    }

    /**
     * @return iterable<string, array{string, array<mixed>}>
     */
    public static function quotedSourcesProvider(): iterable
    {
        $sources = [['title' => 'Printer guide', 'url' => 'https://docs.example.com/printer']];

        yield 'quotedSources key' => [json_encode(['quotedSources' => $sources]), $sources];
        yield 'data key' => [json_encode(['data' => $sources]), $sources];
        yield 'sources key' => [json_encode(['sources' => $sources]), $sources];
        yield 'plain list' => [json_encode($sources), $sources];
    }

    /**
     * @param array<mixed> $expected
     */
    #[DataProvider('quotedSourcesProvider')]
    public function testGetQuotedSources(string $body, array $expected): void
    {
        $config = $this->getMockedConfig(function (string $method, string $url) use ($body): MockResponse {
            $this->assertSame('GET', $method);
            $this->assertSame('https://wikit.example.com/semantics/apps/app-answer/query-execution-sources/q-1/get-quoted-sources', $url);

            return new MockResponse($body);
        });

        $this->assertSame($expected, $config->getQuotedSources('q-1'));
    }

    public function testGetQuotedSourcesOnError(): void
    {
        $this->assertFalse($this->getMockedConfig([new MockResponse('', ['http_code' => 404])])->getQuotedSources('q-1'));
    }

    public function testStreamApiAnswerSendsCompleteChunks(): void
    {
        // Chunks split anywhere by the network, an invalid line is ignored
        $config = $this->getMockedConfig([new MockResponse([
            'data: {"queryId": "q-1", "chunk": "Restart "}STOP',
            'data: {"chunk": "the print',
            'er"}STOPdata: not jsonSTOP',
        ])]);

        $this->expectOutputString(
            "event: chunk\ndata: {\"chunk\":\"Restart \",\"queryId\":\"q-1\"}\n\n"
            . "event: chunk\ndata: {\"chunk\":\"the printer\"}\n\n",
        );

        $this->streamAnswer($config, ['query' => 'Printer is down']);
    }

    public function testStreamApiAnswerKeepsStopWrittenInTheText(): void
    {
        $config = $this->getMockedConfig([new MockResponse([
            'data: {"chunk": "Press STOP, then STOP"}STOP',
            'data: not jsonSTOP',
            'data: {"chunk": " again"}ST',
            'OP',
        ])]);

        $this->expectOutputString(
            "event: chunk\ndata: {\"chunk\":\"Press STOP, then STOP\"}\n\n"
            . "event: chunk\ndata: {\"chunk\":\" again\"}\n\n",
        );

        $this->streamAnswer($config, ['query' => 'Printer is down']);
    }

    public function testStreamApiAnswerUsesStreamMode(): void
    {
        $config = $this->getMockedConfig(function (string $method, string $url): MockResponse {
            $this->assertSame('https://wikit.example.com/semantics/apps/app-editor/query-executions?is_stream_mode=true', $url);

            return new MockResponse('data: {"chunk": "Bonjour"}STOP');
        });

        $this->expectOutputString("event: chunk\ndata: {\"chunk\":\"Bonjour\"}\n\n");

        $this->streamAnswer($config, ['query' => 'correction: Bonjour'], 'app_id_editor');
    }

    public function testStreamApiAnswerFromWorkflow(): void
    {
        $config = $this->getMockedConfig(function (string $method, string $url): MockResponse {
            // Workflow applications always stream: no stream mode parameter
            $this->assertSame('https://wikit.example.com/semantics-workflow/apps/app-answer/query-executions', $url);

            return new MockResponse(self::WORKFLOW_STREAM);
        }, [], Config::APP_TYPE_WORKFLOW);

        // The event without text (metadata) is not sent
        $this->expectOutputString(
            "event: chunk\ndata: {\"chunk\":\"Restart \",\"queryId\":\"q-1\"}\n\n"
            . "event: chunk\ndata: {\"chunk\":\"the printer\",\"queryId\":\"q-1\"}\n\n",
        );

        $this->streamAnswer($config, ['query' => 'Printer is down']);
    }

    public function testStreamApiAnswerFallsBackToWorkflow(): void
    {
        $urls = [];
        $config = $this->getMockedConfig($this->getMockedApiFor(Config::APP_TYPE_WORKFLOW, $urls), [], null);

        // The queryId of the event without text is sent with the next chunk
        $this->expectOutputString(
            "event: chunk\ndata: {\"chunk\":\"Restart \",\"queryId\":\"q-1\"}\n\n"
            . "event: chunk\ndata: {\"chunk\":\"the printer\",\"queryId\":\"q-1\"}\n\n",
        );

        $this->streamAnswer($config, ['query' => 'Printer is down']);
        $this->assertCount(2, $urls);
    }

    public function testStreamApiAnswerFallsBackToClassic(): void
    {
        // Known as a workflow, the application is now a classic one: the empty workflow stream sends nothing
        $config = $this->getMockedConfig([
            new MockResponse('data: {"queryId": "q-0", "metadata": {}, "chunk": ""}STOP' . "\n\n"),
            new MockResponse('data: {"queryId": "q-1", "chunk": "Restart"}STOP'),
        ], [], Config::APP_TYPE_WORKFLOW);

        $this->expectOutputString("event: chunk\ndata: {\"chunk\":\"Restart\",\"queryId\":\"q-1\"}\n\n");

        $this->streamAnswer($config, ['query' => 'Printer is down']);
    }

    public function testStreamApiAnswerReportsEmptyAnswers(): void
    {
        $config = $this->getMockedConfig([new MockResponse(''), new MockResponse(": ping\n\n")]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Wikit Semantics API returned an empty answer');

        $this->streamAnswer($config, ['query' => 'Printer is down']);
    }

    public function testStreamApiAnswerReportsHttpErrors(): void
    {
        $config = $this->getMockedConfig([new MockResponse('', ['http_code' => 401])]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Wikit Semantics API returned HTTP 401');

        $this->streamAnswer($config, ['query' => 'Printer is down']);
    }

    public function testStreamApiAnswerReportsTransportErrors(): void
    {
        $config = $this->getMockedConfig([new MockResponse('', ['error' => 'Connection refused'])]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Streaming API call failed');

        $this->streamAnswer($config, ['query' => 'Printer is down']);
    }

    /**
     * The streaming flushes the output buffers: use a dedicated one so that the events end up in the PHPUnit one
     *
     * @param array<string, string> $dataToPost
     */
    private function streamAnswer(MockedConfig $config, array $dataToPost, string $appIdField = 'app_id_answer'): void
    {
        ob_start();
        try {
            $config->streamAPIAnswer($dataToPost, $appIdField);
        } finally {
            ob_end_flush();
        }
    }
}
