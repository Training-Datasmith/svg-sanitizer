#!/usr/bin/env php
<?php

declare(strict_types=1);

$repoRoot = dirname(__DIR__);

$scanRoots = [
    $repoRoot . '/src',
    $repoRoot . '/tests',
    $repoRoot . '/README.md',
    $repoRoot . '/ARCHITECTURE.md',
];

$denylist = [
    'svgSanitize',
    'svg_Sanitize',
    'ElementReference',
    'NestingException',
    'AllowedAttributes',
    'AllowedTags',
    'AttributeInterface',
    'TagInterface',
    'XPath',
    'SanitizerTest',
    'AllowedAttributesTest',
    'AllowedTagsTest',
    'SubjectTest',
    'TestAllowedAttributes',
    'TestAllowedTags',
    'testAttribute',
    'testTag',
    'getAllowedTags',
    'getAllowedAttrs',
    'setAllowedTags',
    'setAllowedAttrs',
    'getXmlIssues',
    'removeRemoteReferences',
    'removeXMLTag',
    'setXMLOptions',
    'countUse',
    'countUsedIn',
    'addUse',
    'addUsedIn',
];

/**
 * Site-scoped allowlist: relative path + :: + class + :: + method => tokens.
 *
 * @var array<string, list<string>>
 */
$allowlist = [
    'src/Sanitizer.php::Sanitizer::reset_internal' => [
        'preserveWhiteSpace',
        'strictErrorChecking',
        'formatOutput',
    ],
    'src/Sanitizer.php::Sanitizer::sanitize' => ['loadXML', 'saveXML', 'childNodes', 'documentElement'],
    'src/Sanitizer.php::Sanitizer::start_clean' => [
        'DOMNodeList',
        'nodeName',
        'parentNode',
        'removeChild',
        'tagName',
        'getLineNo',
        'hasChildNodes',
        'childNodes',
    ],
    'src/Sanitizer.php::Sanitizer::clean_attributes_on_whitelist' => ['removeAttribute', 'getAttribute', 'getLineNo', 'nodeName'],
    'src/Sanitizer.php::Sanitizer::clean_xlink_hrefs' => ['nodeName', 'removeAttribute', 'getLineNo'],
    'src/Sanitizer.php::Sanitizer::clean_href_attributes' => ['nodeName', 'removeAttribute', 'setAttribute', 'getLineNo'],
    'src/Sanitizer.php::Sanitizer::clean_unsafe_nodes' => [
        'DOMCdataSection',
        'ownerDocument',
        'createTextNode',
        'nodeValue',
        'parentNode',
        'replaceChild',
        'removeChild',
        'nodeName',
        'getLineNo',
        'childNodes',
    ],
    'src/Helper.php::Helper::get_element_href' => [
        'hasAttribute',
        'getAttribute',
        'hasAttributeNS',
        'getAttributeNS',
    ],
    'src/Helper.php::Helper::is_element_contained_in' => ['childNodes'],
    'src/data/X_Path.php::X_Path::handle_default_namespace' => [
        'namespaceURI',
        'registerNamespace',
        'LogicException',
    ],
    'src/data/X_Path.php::X_Path::get_root_elements' => [
        'getElementsByTagName',
        'parentNode',
    ],
    'src/Element_Reference/Resolver.php::Resolver::collect_identified_elements' => ['getAttribute'],
    'src/Element_Reference/Subject.php::Subject::get_element_id' => ['getAttribute'],
    'src/data/Allowed_Tags.php::Allowed_Tags::get_tags' => [
        'feBlend',
        'feColorMatrix',
        'feComponentTransfer',
        'feComposite',
        'feConvolveMatrix',
        'feDiffuseLighting',
        'feDisplacementMap',
        'feDistantLight',
        'feFlood',
        'feFuncA',
        'feFuncB',
        'feFuncG',
        'feFuncR',
        'feGaussianBlur',
        'feMerge',
        'feMergeNode',
        'feMorphology',
        'feOffset',
        'fePointLight',
        'feSpecularLighting',
        'feSpotLight',
        'feTile',
        'feTurbulence',
    ],
    'src/data/Allowed_Attributes.php::Allowed_Attributes::get_attributes' => ['filterUnits'],
    'tests/Sanitizer_Test.php::Sanitizer_Test::test_minified_options' => ['viewBox'],
];

$assertionMethods = [
    'assertSame',
    'assertEquals',
    'assertFalse',
    'assertInstanceOf',
    'assertXmlStringEqualsXmlString',
    'assertStringNotContainsStringIgnoringCase',
    'assertThat',
    'logicalNot',
    'stringContains',
];

foreach (glob($repoRoot . '/tests/*_Test.php') as $testFile) {
    $rel = substr($testFile, strlen($repoRoot) + 1);
    $allowlist[$rel . '::__construct'] = array_merge(['TestCase'], $assertionMethods);
    foreach ($assertionMethods as $method) {
        $allowlist[$rel . '::*::' . $method] = [$method];
    }
}

/**
 * @return array<int, string>
 */
function php_files_to_scan(): array
{
    global $scanRoots;
    $files = [];
    foreach ($scanRoots as $root) {
        if (is_file($root)) {
            $files[] = $root;
            continue;
        }
        if (!is_dir($root)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if ($file->isFile() && substr($file->getFilename(), -4) === '.php') {
                $files[] = $file->getPathname();
            }
        }
    }

    return $files;
}

/**
 * @return array{0: string, 1: string}
 */
function resolve_site(string $relativePath, string $content, int $lineNumber): array
{
    $lines = preg_split("/\r\n|\n|\r/", $content) ?: [];
    $class = '';
    $function = '';
    for ($i = 0; $i < $lineNumber && $i < count($lines); $i++) {
        if (preg_match('/\bclass\s+([A-Z][A-Za-z0-9_]*)\b/', $lines[$i], $m)) {
            $class = $m[1];
        }
        if (preg_match('/\bfunction\s+([a-z][A-Za-z0-9_]*)\b/', $lines[$i], $m)) {
            $function = $m[1];
        }
    }

    return [$class, $function];
}

function is_allowed_token(string $relativePath, string $class, string $function, string $token, array $allowlist): bool
{
    if ($class === '' && $function === '') {
        return false;
    }
    $sites = [
        $relativePath . '::' . $class . '::' . $function,
        $relativePath . '::' . $class . '::*',
        $relativePath . '::__construct',
    ];
    foreach ($sites as $site) {
        if (!isset($allowlist[$site])) {
            continue;
        }
        if (in_array($token, $allowlist[$site], true)) {
            return true;
        }
    }

    return false;
}

function strip_strings_and_comments(string $code): string
{
    $out = '';
    $len = strlen($code);
    $i = 0;
    while ($i < $len) {
        $two = substr($code, $i, 2);
        if ($two === '//') {
            while ($i < $len && $code[$i] !== "\n") {
                $i++;
            }
            continue;
        }
        if ($two === '/*') {
            $i += 2;
            while ($i < $len - 1 && substr($code, $i, 2) !== '*/') {
                $i++;
            }
            $i += 2;
            continue;
        }
        if ($code[$i] === "'" || $code[$i] === '"') {
            $quote = $code[$i];
            $i++;
            while ($i < $len) {
                if ($code[$i] === '\\') {
                    $i += 2;
                    continue;
                }
                if ($code[$i] === $quote) {
                    $i++;
                    break;
                }
                $i++;
            }
            $out .= ' "" ';
            continue;
        }
        $out .= $code[$i];
        $i++;
    }

    return $out;
}

/**
 * @return list<string>
 */
function scan_content(string $relativePath, string $content, array $denylist, array $allowlist): array
{
    $errors = [];
    $lines = preg_split("/\r\n|\n|\r/", $content) ?: [];
    if (substr($relativePath, -3) === '.md') {
        foreach ($lines as $lineIndex => $line) {
            foreach ($denylist as $token) {
                if (preg_match('/\b' . preg_quote($token, '/') . '\b/', $line)) {
                    $errors[] = sprintf('%s:%d: forbidden legacy name %s', $relativePath, $lineIndex + 1, $token);
                }
            }
        }

        return $errors;
    }
    foreach ($lines as $lineIndex => $line) {
        $lineNo = $lineIndex + 1;
        foreach ($denylist as $token) {
            if (preg_match('/\b' . preg_quote($token, '/') . '\b/', $line)) {
                $errors[] = sprintf('%s:%d: forbidden legacy name %s', $relativePath, $lineNo, $token);
            }
        }
    }

    foreach ($lines as $lineIndex => $line) {
        $lineNo = $lineIndex + 1;
        $trimmed = ltrim($line);
        if ($trimmed === '' || $trimmed[0] === '*' || strncmp($trimmed, '//', 2) === 0) {
            continue;
        }
        $code_line = strip_strings_and_comments($line);
        if (!preg_match_all('/\b([a-z]+[A-Z][A-Za-z0-9_]*)\b/', $code_line, $matches)) {
            continue;
        }
        [$class, $function] = resolve_site($relativePath, $content, $lineNo);
        foreach ($matches[1] as $token) {
            if (is_allowed_token($relativePath, $class, $function, $token, $allowlist)) {
                continue;
            }
            if (strpos($relativePath, 'tests/') === 0 && in_array($token, ['assertSame', 'assertEquals', 'assertFalse', 'assertInstanceOf', 'assertXmlStringEqualsXmlString', 'assertStringNotContainsStringIgnoringCase', 'assertThat', 'logicalNot', 'stringContains', 'TestCase'], true)) {
                continue;
            }
            $errors[] = sprintf('%s:%d: repo-owned camelCase identifier %s', $relativePath, $lineNo, $token);
        }
    }

    return $errors;
}

/**
 * @param list<string> $roots
 * @return list<string>
 */
function scan_repository(array $roots, string $repoRoot, array $denylist, array $allowlist): array
{
    $errors = [];
    $files = php_files_to_scan();
    foreach ($files as $file) {
        $relative = substr($file, strlen($repoRoot) + 1);
        $content = file_get_contents($file);
        if ($content === false) {
            $errors[] = sprintf('%s: unreadable', $relative);
            continue;
        }
        $errors = array_merge($errors, scan_content($relative, $content, $denylist, $allowlist));
    }
    return $errors;
}

$errors = scan_repository($scanRoots, $repoRoot, $denylist, $allowlist);
if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, 'check-rename: ' . $error . "\n");
    }
    exit(1);
}

// In-memory demo: same scanner, token allowed at Sanitizer::reset_internal only.
$demoAllow = $allowlist;
$demoContent = <<<'PHP'
<?php
namespace enshrined\Svg_Sanitize;
class Helper {
    public static function get_element_href() {
        $x = preserveWhiteSpace;
    }
}
PHP;
$demoErrors = scan_content('src/Helper.php', $demoContent, $denylist, $demoAllow);
if ($demoErrors === []) {
    fwrite(STDERR, "check-rename: allowlist demo should reject preserveWhiteSpace outside Sanitizer::reset_internal\n");
    exit(1);
}

$goodSnippet = <<<'PHP'
<?php
namespace enshrined\Svg_Sanitize;
class Sanitizer {
    protected function reset_internal() {
        $this->xml_document->preserveWhiteSpace = false;
    }
}
PHP;
$goodErrors = scan_content('src/Sanitizer.php', $goodSnippet, $denylist, $demoAllow);
if ($goodErrors !== []) {
    fwrite(STDERR, "check-rename: allowlist demo rejected valid Sanitizer::reset_internal site\n");
    exit(1);
}

fwrite(STDOUT, "check-rename: no disallowed camelCase repo-owned names\n");
fwrite(STDOUT, "check-rename: allowlisted preserveWhiteSpace rejected outside Sanitizer::reset_internal\n");
exit(0);
