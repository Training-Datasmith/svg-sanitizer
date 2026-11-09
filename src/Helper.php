<?php

declare (strict_types=1);
namespace enshrined\Svg_Sanitize;

class Helper
{
    public static function get_element_href(\DOMElement $element): ?string
    {
        if ($element->hasAttribute('href')) {
            return $element->getAttribute('href');
        }
        if ($element->hasAttributeNS('http://www.w3.org/1999/xlink', 'href')) {
            return $element->getAttributeNS('http://www.w3.org/1999/xlink', 'href');
        }
        return null;
    }
    /**
     * @param string $href
     * @return string|null
     */
    public static function extract_id_reference_from_href($href)
    {
        if (!is_string($href) || strpos($href, '#') !== 0) {
            return null;
        }
        return substr($href, 1);
    }
    public static function is_element_contained_in(\DOMElement $needle, \DOMElement $haystack): bool
    {
        if ($needle === $haystack) {
            return true;
        }
        foreach ($haystack->childNodes as $child_node) {
            if (!$child_node instanceof \DOMElement) {
                continue;
            }
            if (self::is_element_contained_in($needle, $child_node)) {
                return true;
            }
        }
        return false;
    }
}