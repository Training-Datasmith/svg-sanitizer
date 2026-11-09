#!/usr/bin/env php
<?php

declare(strict_types=1);

$repoRoot = dirname(__DIR__);
$installedPhp = $repoRoot . '/vendor/composer/installed.php';
$installedJson = $repoRoot . '/vendor/composer/installed.json';

if (!is_readable($installedPhp) || !is_readable($installedJson)) {
    fwrite(STDERR, "check-cluster-pins: run composer update first.\n");
    exit(1);
}

/** @var array{versions: array<string, array{reference?: string, aliases?: list<string>, version?: string}>} $installedData */
$installedData = require $installedPhp;
$installedVersions = $installedData['versions'] ?? [];

$rawJson = file_get_contents($installedJson);
if ($rawJson === false) {
    fwrite(STDERR, "check-cluster-pins: could not read installed.json.\n");
    exit(1);
}
$jsonData = json_decode($rawJson, true);
if (!is_array($jsonData)) {
    fwrite(STDERR, "check-cluster-pins: installed.json is invalid JSON.\n");
    exit(1);
}

$jsonPackages = array_merge($jsonData['packages'] ?? [], $jsonData['packages-dev'] ?? []);
$jsonByName = [];
$jsonMeta = [];
foreach ($jsonPackages as $package) {
    if (!isset($package['name'], $package['source']['reference'])) {
        continue;
    }
    $name = $package['name'];
    $jsonByName[$name] = strtolower((string) $package['source']['reference']);
    $jsonMeta[$name] = $package;
}

/** @var array<string, array{alias: string, reference: string}> $expectedOrg */
$expectedOrg = [];

/**
 * @param array<string, array{alias: string, reference: string}> $orgMap
 * @param array<string, array<string, mixed>> $phpVersions
 * @param array<string, string> $jsonRefs
 */
function validate_pins(array $orgMap, array $phpVersions, array $jsonRefs, bool $log = true): bool
{
    $failed = false;
    foreach ($orgMap as $name => $spec) {
        if (!isset($phpVersions[$name])) {
            if ($log) {
                fwrite(STDERR, sprintf("check-cluster-pins: %s is not installed.\n", $name));
            }
            $failed = true;
            continue;
        }
        $pkg = $phpVersions[$name];
        $aliases = $pkg['aliases'] ?? [];
        if (!in_array($spec['alias'], $aliases, true)) {
            if ($log) {
                fwrite(STDERR, sprintf("check-cluster-pins: %s alias mismatch.\n", $name));
            }
            $failed = true;
        }
        $reference = isset($pkg['reference']) ? strtolower((string) $pkg['reference']) : '';
        if ($reference === '' || !hash_equals(strtolower($spec['reference']), $reference)) {
            if ($log) {
                fwrite(STDERR, sprintf("check-cluster-pins: %s reference mismatch in installed.php.\n", $name));
            }
            $failed = true;
        }
        if (!isset($jsonRefs[$name]) || !hash_equals(strtolower($spec['reference']), $jsonRefs[$name])) {
            if ($log) {
                fwrite(STDERR, sprintf("check-cluster-pins: %s reference mismatch in installed.json.\n", $name));
            }
            $failed = true;
        }
    }

    if (!isset($phpVersions['phpunit/phpunit'], $jsonRefs['phpunit/phpunit'])) {
        if ($log) {
            fwrite(STDERR, "check-cluster-pins: phpunit/phpunit is not installed.\n");
        }
        return false;
    }

    $phpRef = strtolower((string) ($phpVersions['phpunit/phpunit']['reference'] ?? ''));
    $jsonRef = $jsonRefs['phpunit/phpunit'];
    if ($phpRef === '' || !hash_equals($phpRef, $jsonRef)) {
        if ($log) {
            fwrite(STDERR, "check-cluster-pins: phpunit/phpunit reference mismatch between installed.php and installed.json.\n");
        }
        $failed = true;
    }

    return !$failed;
}

$indexShas = [
    '698cbbc1e75a1ef1968f9cbfec89be056b288d0f',
    'a37d66fabf01af0eedabffe271b70dbe71723001',
];

if (!validate_pins($expectedOrg, $installedVersions, $jsonByName)) {
    exit(1);
}

$phpunitRef = $jsonByName['phpunit/phpunit'];
if (in_array($phpunitRef, $indexShas, true)) {
    fwrite(STDERR, "check-cluster-pins: phpunit/phpunit must not be the Training-Datasmith index tip.\n");
    exit(1);
}

$version = (string) ($jsonMeta['phpunit/phpunit']['version'] ?? $installedVersions['phpunit/phpunit']['pretty_version'] ?? $installedVersions['phpunit/phpunit']['version'] ?? '');
if ($version === '' || version_compare($version, '8.5.52', '<') || version_compare($version, '9.0.0', '>=')) {
    fwrite(STDERR, sprintf("check-cluster-pins: phpunit/phpunit version must be ^8.5.52 (got %s).\n", $version));
    exit(1);
}

$sourceUrl = (string) ($jsonMeta['phpunit/phpunit']['source']['url'] ?? '');
if (stripos($sourceUrl, 'sebastianbergmann/phpunit') === false) {
    fwrite(STDERR, "check-cluster-pins: phpunit/phpunit must come from sebastianbergmann/phpunit on Packagist.\n");
    exit(1);
}

fwrite(STDOUT, sprintf(
    "check-cluster-pins: org pin map empty; phpunit/phpunit is Packagist %s %s\n",
    $version,
    $phpunitRef
));

$wrong = str_repeat('0', 40);
$phpMutated = $installedVersions;
$phpMutated['phpunit/phpunit']['reference'] = $wrong;
if (validate_pins($expectedOrg, $phpMutated, $jsonByName, false)) {
    fwrite(STDERR, "check-cluster-pins: wrong-sha demo installed.php should have failed.\n");
    exit(1);
}
if (!validate_pins($expectedOrg, $installedVersions, $jsonByName)) {
    fwrite(STDERR, "check-cluster-pins: wrong-sha demo broke real installed.json map.\n");
    exit(1);
}
fwrite(STDOUT, "check-cluster-pins: wrong-sha demo installed.php failed and installed.json passed\n");

$jsonMutated = $jsonByName;
$jsonMutated['phpunit/phpunit'] = $wrong;
if (validate_pins($expectedOrg, $installedVersions, $jsonMutated, false)) {
    fwrite(STDERR, "check-cluster-pins: wrong-sha demo installed.json should have failed.\n");
    exit(1);
}
fwrite(STDOUT, "check-cluster-pins: wrong-sha demo installed.json failed and installed.php passed\n");

exit(0);
