#!/usr/bin/env php
<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_readable($autoload)) {
    require_once $autoload;
} else {
    require_once __DIR__ . '/data/Attribute_Interface.php';
    require_once __DIR__ . '/data/Tag_Interface.php';
    require_once __DIR__ . '/data/Allowed_Attributes.php';
    require_once __DIR__ . '/data/Allowed_Tags.php';
    require_once __DIR__ . '/data/X_Path.php';
    require_once __DIR__ . '/Element_Reference/Resolver.php';
    require_once __DIR__ . '/Element_Reference/Subject.php';
    require_once __DIR__ . '/Element_Reference/Usage.php';
    require_once __DIR__ . '/Exceptions/Nesting_Exception.php';
    require_once __DIR__ . '/Helper.php';
    require_once __DIR__ . '/Sanitizer.php';
}

function sysexit(array $results, int $status): void
{
    echo json_encode($results, JSON_PRETTY_PRINT);
    exit($status);
}

global $argv;

$results = ['totals' => ['errors' => 0], 'files' => []];
$files_to_scan = $argv;
unset($files_to_scan[0]);
$files_to_scan = array_values($files_to_scan);

if (empty($files_to_scan)) {
    $results['totals']['errors']++;
    $results['messages'] = [['No files to scan specified']];
    sysexit($results, 1);
}

$sanitizer = new enshrined\Svg_Sanitize\Sanitizer();
$sanitizer->remove_remote_references(true);

foreach ($files_to_scan as $file_name) {
    $svg_file = @file_get_contents($file_name);
    if (false === $svg_file) {
        $results['totals']['errors']++;
        $results['files'][$file_name][] = ['errors' => 1, 'messages' => [['message' => 'File specified could not be read (' . $file_name . ')', 'line' => null]]];
        continue;
    }
    $sanitize_status = $sanitizer->sanitize($svg_file);
    $xml_issues = $sanitizer->get_xml_issues();
    if (empty($xml_issues) && false !== $sanitize_status) {
        $results['files'][$file_name] = ['errors' => 0, 'messages' => []];
    } elseif ('' === $sanitize_status || false === $sanitize_status) {
        $results['totals']['errors']++;
        $results['files'][$file_name] = ['errors' => 1, 'messages' => [['message' => 'Unable to sanitize file \'' . $file_name . '\'', 'line' => null]]];
    } else {
        $results['totals']['errors'] += count($xml_issues);
        $results['files'][$file_name] = ['errors' => count($xml_issues), 'messages' => $xml_issues];
    }
}

sysexit($results, $results['totals']['errors'] === 0 ? 0 : 1);
