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
    'src/Element_Reference/Subject.php::Subject::add_use' => ['LogicException'],
    'src/Element_Reference/Subject.php::Subject::add_used_in' => ['LogicException'],
    'tests/Sanitizer_Test.php::Sanitizer_Test::test_minified_options' => ['viewBox'],
];

/**
 * @return array<string, list<string>>
 */
function build_test_method_allowlist(string $repoRoot, array $assertionMethods): array
{
    $extra = [];
    foreach (glob($repoRoot . '/tests/*_Test.php') ?: [] as $testFile) {
        $rel = substr($testFile, strlen($repoRoot) + 1);
        $content = file_get_contents($testFile);
        if ($content === false) {
            continue;
        }
        $class = '';
        if (preg_match('/\bclass\s+([A-Z][A-Za-z0-9_]*)\b/', $content, $m)) {
            $class = $m[1];
        }
        if ($class === '') {
            continue;
        }
        $extra[$rel . '::' . $class . '::__header'] = ['TestCase'];
        if (preg_match_all('/\bfunction\s+([a-z][a-z0-9_]*)\b/', $content, $methods)) {
            foreach ($methods[1] as $method) {
                $extra[$rel . '::' . $class . '::' . $method] = $assertionMethods;
            }
        }
    }

    return $extra;
}

$allowlist = array_merge($allowlist, build_test_method_allowlist($repoRoot, $assertionMethods));

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
        if (preg_match('/\bfunction\s+([a-z][a-z0-9_]*)\b/', $lines[$i], $m)) {
            $function = $m[1];
        }
    }

    return [$class, $function];
}

function is_allowed_token(string $relativePath, string $class, string $function, string $token, array $allowlist, string $line): bool
{
    if (preg_match('/\bextends\s+TestCase\b/', $line) && $class !== '') {
        $header = $relativePath . '::' . $class . '::__header';
        if (isset($allowlist[$header]) && in_array($token, $allowlist[$header], true)) {
            return true;
        }
    }
    if ($class === '') {
        return false;
    }
    $sites = [
        $relativePath . '::' . $class . '::' . $function,
        $relativePath . '::' . $class . '::__header',
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
 * Repo-owned camelCase: any lowercase-to-uppercase transition inside an identifier (including after '_').
 *
 * @return list<string>
 */
function find_repo_camel_case_tokens(string $text): array
{
    $tokens = [];
    if (preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*[a-z][A-Z][a-zA-Z0-9_]*)\b/', $text, $matches)) {
        foreach ($matches[1] as $token) {
            $tokens[] = $token;
        }
    }

    return $tokens;
}

/**
 * Scan one-line block comments with @var or @param before comment stripping.
 *
 * @return list<string>
 */
function scan_one_line_phpdoc_blocks(
    string $relativePath,
    string $content,
    int $lineNo,
    string $line,
    array $denylist,
    array $allowlist
): array {
    $errors = [];
    if (strpos($line, '/**') === false || strpos($line, '*/') === false) {
        return $errors;
    }
    if (!preg_match_all('/\/\*\*(.*?)\*\//', $line, $matches)) {
        return $errors;
    }
    foreach ($matches[1] as $inner) {
        $inner = trim($inner);
        if (!preg_match('/@(var|param)\b/', $inner)) {
            continue;
        }
        $docLine = preg_replace('/\b[A-Za-z0-9_\\\\]+::[a-zA-Z0-9_]+\b/', '', $inner);
        if (!preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $docLine, $varMatches)) {
            continue;
        }
        foreach ($denylist as $token) {
            if (preg_match('/\b' . preg_quote($token, '/') . '\b/', $docLine)) {
                $errors[] = sprintf('%s:%d: forbidden legacy name %s', $relativePath, $lineNo, $token);
            }
        }
        [$class, $function] = resolve_site($relativePath, $content, $lineNo);
        foreach ($varMatches[1] as $token) {
            if (!preg_match('/[a-z][A-Z]/', $token)) {
                continue;
            }
            if (is_allowed_token($relativePath, $class, $function, $token, $allowlist, $line)) {
                continue;
            }
            $errors[] = sprintf('%s:%d: repo-owned camelCase identifier %s', $relativePath, $lineNo, $token);
        }
    }

    return $errors;
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
        $isDocblock = $trimmed !== '' && $trimmed[0] === '*';
        if (strncmp($trimmed, '//', 2) === 0) {
            continue;
        }
        if (preg_match('/^\s*use\s+[\w\\\\{]/', $line)) {
            continue;
        }

        $errors = array_merge(
            $errors,
            scan_one_line_phpdoc_blocks($relativePath, $content, $lineNo, $line, $denylist, $allowlist)
        );

        $scanLine = $isDocblock ? ltrim(substr($trimmed, 1)) : $line;
        if ($isDocblock) {
            $scanLine = preg_replace('/\b[A-Za-z0-9_\\\\]+::[a-zA-Z0-9_]+\b/', '', $scanLine);
        }
        $code_line = strip_strings_and_comments($scanLine);
        $tokens = find_repo_camel_case_tokens($code_line);
        if ($tokens === []) {
            continue;
        }
        [$class, $function] = resolve_site($relativePath, $content, $lineNo);
        foreach ($tokens as $token) {
            if (is_allowed_token($relativePath, $class, $function, $token, $allowlist, $line)) {
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

    foreach ([$repoRoot . '/README.md', $repoRoot . '/ARCHITECTURE.md'] as $doc) {
        if (!is_readable($doc)) {
            continue;
        }
        $relative = substr($doc, strlen($repoRoot) + 1);
        $content = file_get_contents($doc);
        if ($content === false) {
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

$genericCamel = <<<'PHP'
<?php
namespace enshrined\Svg_Sanitize;
class Helper {
    public static function bad_helper() {
        $totallyWrong = 1;
    }
}
PHP;
$genericErrors = scan_content('src/Helper.php', $genericCamel, $denylist, $demoAllow);
if ($genericErrors === []) {
    fwrite(STDERR, "check-rename: generic camelCase demo should fail\n");
    exit(1);
}

$oneLineVarDoc = <<<'PHP'
<?php
namespace enshrined\Svg_Sanitize;
class Sanitizer {
    public function start_clean() {
        /** @var \DOMElement $currentElement */
        $current_element = 1;
    }
}
PHP;
$oneLineVarErrors = scan_content('src/Sanitizer.php', $oneLineVarDoc, $denylist, $demoAllow);
if ($oneLineVarErrors === []) {
    fwrite(STDERR, "check-rename: one-line @var demo should reject \$currentElement\n");
    exit(1);
}

fwrite(STDOUT, "check-rename: no disallowed camelCase repo-owned names\n");
fwrite(STDOUT, "check-rename: allowlisted preserveWhiteSpace rejected outside Sanitizer::reset_internal\n");
fwrite(STDOUT, "check-rename: generic camelCase identifier rejected\n");
fwrite(STDOUT, "check-rename: one-line @var camelCase identifier rejected\n");
exit(0);
