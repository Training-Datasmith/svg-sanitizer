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
function validate_org_pins(array $orgMap, array $phpVersions, array $jsonRefs, bool $log = true): bool
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

    return !$failed;
}

function phpunit_php_reference(array $phpVersions): string
{
    return strtolower((string) ($phpVersions['phpunit/phpunit']['reference'] ?? ''));
}

function phpunit_json_reference(array $jsonRefs): string
{
    return strtolower((string) ($jsonRefs['phpunit/phpunit'] ?? ''));
}

function phpunit_ref_matches_expected(string $reference, string $expected): bool
{
    return $reference !== '' && hash_equals($expected, $reference);
}

/**
 * @param array<string, array<string, mixed>> $phpVersions
 * @param array<string, string> $jsonRefs
 */
function validate_packagist_phpunit(array $phpVersions, array $jsonRefs, array $jsonMeta, string $expectedRef): bool
{
    if (!isset($phpVersions['phpunit/phpunit'], $jsonRefs['phpunit/phpunit'])) {
        fwrite(STDERR, "check-cluster-pins: phpunit/phpunit is not installed.\n");
        return false;
    }
    $phpRef = phpunit_php_reference($phpVersions);
    $jsonRef = phpunit_json_reference($jsonRefs);
    if (!phpunit_ref_matches_expected($phpRef, $expectedRef)) {
        fwrite(STDERR, "check-cluster-pins: phpunit/phpunit installed.php reference does not match expected Packagist SHA.\n");
        return false;
    }
    if (!phpunit_ref_matches_expected($jsonRef, $expectedRef)) {
        fwrite(STDERR, "check-cluster-pins: phpunit/phpunit installed.json reference does not match expected Packagist SHA.\n");
        return false;
    }
    if (!hash_equals($phpRef, $jsonRef)) {
        fwrite(STDERR, "check-cluster-pins: phpunit/phpunit reference mismatch between installed.php and installed.json.\n");
        return false;
    }

    $indexShas = [
        '698cbbc1e75a1ef1968f9cbfec89be056b288d0f',
        'a37d66fabf01af0eedabffe271b70dbe71723001',
    ];
    if (in_array($phpRef, $indexShas, true)) {
        fwrite(STDERR, "check-cluster-pins: phpunit/phpunit must not be the Training-Datasmith index tip.\n");
        return false;
    }

    $version = (string) ($jsonMeta['phpunit/phpunit']['version'] ?? $phpVersions['phpunit/phpunit']['pretty_version'] ?? $phpVersions['phpunit/phpunit']['version'] ?? '');
    if ($version === '' || version_compare($version, '8.5.52', '<') || version_compare($version, '9.0.0', '>=')) {
        fwrite(STDERR, sprintf("check-cluster-pins: phpunit/phpunit version must be ^8.5.52 (got %s).\n", $version));
        return false;
    }

    $sourceUrl = (string) ($jsonMeta['phpunit/phpunit']['source']['url'] ?? '');
    if (stripos($sourceUrl, 'sebastianbergmann/phpunit') === false) {
        fwrite(STDERR, "check-cluster-pins: phpunit/phpunit must come from sebastianbergmann/phpunit on Packagist.\n");
        return false;
    }

    return true;
}

if (!validate_org_pins($expectedOrg, $installedVersions, $jsonByName)) {
    exit(1);
}

$expectedRef = phpunit_json_reference($jsonByName);
if ($expectedRef === '') {
    fwrite(STDERR, "check-cluster-pins: missing phpunit/phpunit reference.\n");
    exit(1);
}

if (!validate_packagist_phpunit($installedVersions, $jsonByName, $jsonMeta, $expectedRef)) {
    exit(1);
}

$version = (string) ($jsonMeta['phpunit/phpunit']['version'] ?? '');
fwrite(STDOUT, sprintf(
    "check-cluster-pins: org pin map empty; phpunit/phpunit is Packagist %s %s\n",
    $version,
    $expectedRef
));

$wrong = str_repeat('0', 40);

$phpSnap = $installedVersions;
$phpSnap['phpunit/phpunit']['reference'] = $wrong;
if (phpunit_ref_matches_expected(phpunit_php_reference($phpSnap), $expectedRef)) {
    fwrite(STDERR, "check-cluster-pins: wrong-sha demo installed.php should have failed.\n");
    exit(1);
}
if (!phpunit_ref_matches_expected(phpunit_json_reference($jsonByName), $expectedRef)) {
    fwrite(STDERR, "check-cluster-pins: wrong-sha demo installed.php snapshot broke unmutated installed.json side.\n");
    exit(1);
}
fwrite(STDOUT, "check-cluster-pins: wrong-sha demo installed.php failed and installed.json passed\n");

$jsonSnap = $jsonByName;
$jsonSnap['phpunit/phpunit'] = $wrong;
if (phpunit_ref_matches_expected(phpunit_json_reference($jsonSnap), $expectedRef)) {
    fwrite(STDERR, "check-cluster-pins: wrong-sha demo installed.json should have failed.\n");
    exit(1);
}
if (!phpunit_ref_matches_expected(phpunit_php_reference($installedVersions), $expectedRef)) {
    fwrite(STDERR, "check-cluster-pins: wrong-sha demo installed.json snapshot broke unmutated installed.php side.\n");
    exit(1);
}
fwrite(STDOUT, "check-cluster-pins: wrong-sha demo installed.json failed and installed.php passed\n");

$bothPhp = $installedVersions;
$bothPhp['phpunit/phpunit']['reference'] = $wrong;
$bothJson = $jsonByName;
$bothJson['phpunit/phpunit'] = $wrong;
if (phpunit_ref_matches_expected(phpunit_php_reference($bothPhp), $expectedRef)) {
    fwrite(STDERR, "check-cluster-pins: both-sides wrong-sha demo: installed.php should have failed.\n");
    exit(1);
}
if (phpunit_ref_matches_expected(phpunit_json_reference($bothJson), $expectedRef)) {
    fwrite(STDERR, "check-cluster-pins: both-sides wrong-sha demo: installed.json should have failed.\n");
    exit(1);
}
fwrite(STDOUT, "check-cluster-pins: wrong-sha demo both sides failed against expected reference\n");

exit(0);
