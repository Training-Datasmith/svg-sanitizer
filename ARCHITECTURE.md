# Architecture: svg-sanitizer

## Purpose

A PHP library that sanitises SVG files by removing potentially malicious elements and attributes (such as `<script>`, event handlers, and external references) while preserving the valid SVG structure. Used to safely accept user-uploaded SVG files.

## Directory Structure

```
src/
  Sanitizer.php                  - Main entry point: sanitize(string $svg): string
  Helper.php                     - Static utility methods for the sanitizer
  data/
    Allowed_Tags.php             - Allowlist of permitted SVG element names
    Allowed_Attributes.php       - Allowlist of permitted SVG attribute names
    Tag_Interface.php            - Contract for allowed tag lists
    Attribute_Interface.php      - Contract for allowed attribute lists
    X_Path.php                   - XPath expression constants for SVG traversal
  ElementReference/
    Resolver.php                 - Resolves element references (e.g., <use href="#id">)
    Subject.php                  - Represents a referenced element with its usages
    Usage.php                    - Represents a single use of a referenced element
  Exceptions/
    Nesting_Exception.php        - Thrown when element nesting exceeds the safe depth limit
  svg-scanner.php                - Stream filter for pre-processing SVG input
```

## Key Design Decisions

- **Allowlist approach**: Only explicitly permitted tags and attributes pass through — everything else is stripped. This is more secure than a denylist.
- **DOMDocument parsing**: Uses PHP's `DOMDocument`/`DOMXPath` for parsing and manipulation rather than regex, avoiding common regex-based bypass vectors.
- **Reference-loop detection**: `ElementReference\Resolver` detects circular `<use>` element references (a common SVG DoS/XSS vector) and removes them.
- **Nesting depth limit**: Deeply nested SVG structures are rejected via `Nesting_Exception` to prevent XML bomb-style attacks.

## Extension Points

- Extend `Allowed_Tags` or `Allowed_Attributes` to add or remove permitted elements for your use case.
- Pass a custom allowlist instance to `Sanitizer::__construct()`.

## Dependency Flow

```
Sanitizer::sanitize(string $svg)
  └─> DOMDocument::loadXML()     — parse SVG
  └─> DOMXPath traversal         — visit all nodes
        └─> check tag against Allowed_Tags
        └─> check attributes against Allowed_Attributes
        └─> ElementReference\Resolver — detect circular <use> refs
  └─> DOMDocument::saveXML()     — serialise cleaned SVG
```
