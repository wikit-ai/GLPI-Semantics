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

namespace GlpiPlugin\Wikitsemantics\Tests\Support;

use GlpiPlugin\Wikitsemantics\Config;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Configuration calling Wikit Semantics through a mock HTTP client instead of the network
 */
final class MockedConfig extends Config
{
    private HttpClientInterface $client;

    /**
     * Application type known from a previous call, see `$useAppTypeCache`
     */
    private ?string $appType = Config::APP_TYPE_SEMANTICS;

    /**
     * Whether the application type is read from the GLPI cache as in production, instead of `$appType`
     */
    private bool $useAppTypeCache = false;

    public function __construct()
    {
        // The parent constructor loads the configuration from the database
    }

    /**
     * @param array<string, mixed> $fields        Configuration values
     * @param string|null          $appType         Type of the applications known from a previous call (`Config::APP_TYPE_*`), null when unknown
     * @param bool                 $useAppTypeCache Read the type of the applications from the GLPI cache instead of using `$appType`
     */
    public static function create(
        HttpClientInterface $client,
        array $fields,
        ?string $appType = Config::APP_TYPE_SEMANTICS,
        bool $useAppTypeCache = false,
    ): self {
        $config = new self();
        $config->client = $client;
        $config->fields = $fields;
        $config->appType = $appType;
        $config->useAppTypeCache = $useAppTypeCache;

        return $config;
    }

    protected function getHttpClient(): HttpClientInterface
    {
        return $this->client;
    }

    protected function getAppType(string $appIdField): ?string
    {
        return $this->useAppTypeCache ? parent::getAppType($appIdField) : $this->appType;
    }
}
