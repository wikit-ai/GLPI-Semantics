<?php

/**
 * Twig CS Fixer configuration for Wikit Semantics plugin
 */

use TwigCsFixer\Config\Config;
use TwigCsFixer\File\Finder;
use TwigCsFixer\Ruleset\Ruleset;
use TwigCsFixer\Standard\TwigCsFixer;

$finder = new Finder();
$finder->in(__DIR__ . '/templates');

$config = new Config();
$config->setFinder($finder);
$config->setRuleset(new Ruleset());

return $config;
