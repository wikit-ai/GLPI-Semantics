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
    private function getMockedConfig(array|callable $responses, array $fields = []): MockedConfig
    {
        return MockedConfig::create(new MockHttpClient($responses), $fields + [
            'url_api'         => 'https://wikit.example.com/',
            'api_key'         => 'encrypted:' . (new GLPIKey())->encrypt('secret-key'),
            'organization_id' => 'org-1',
            'app_id_answer'   => 'app-answer',
            'app_id_kb'       => 'app-kb',
            'app_id_editor'   => 'app-editor',
        ]);
    }

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

    public function testStreamApiAnswerUsesStreamMode(): void
    {
        $config = $this->getMockedConfig(function (string $method, string $url): MockResponse {
            $this->assertSame('https://wikit.example.com/semantics/apps/app-editor/query-executions?is_stream_mode=true', $url);

            return new MockResponse('');
        });

        $this->streamAnswer($config, ['query' => 'correction: Bonjour'], 'app_id_editor');
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
