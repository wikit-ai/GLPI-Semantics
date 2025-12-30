<?php

declare(strict_types=1);

/**
 * Twig CS configuration for Wikit Semantics plugin
 */

use FriendsOfTwig\Twigcs;

$finder = Twigcs\Finder\TemplateFinder::create()
    ->in(__DIR__ . '/templates')
    ->depth('>= 0')
    ->name('*.html.twig')
    ->ignoreVCSIgnored(true);

return Twigcs\Config\Config::create()
    ->setFinder($finder)
    ->setRuleSet(\Glpi\Tools\GlpiTwigRuleset::class);
