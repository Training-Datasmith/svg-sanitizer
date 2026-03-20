<?php

declare (strict_types=1);
namespace enshrined\Svg_Sanitize;

class Helper
{
    public static function get_element_href(\Dom_Element $element): ?string
    {
        if ($element->has_attribute('href')) {
            return $element->get_attribute('href');
        }
        if ($element->has_attribute_ns('http://www.w3.org/1999/xlink', 'href')) {
            return $element->get_attribute_ns('http://www.w3.org/1999/xlink', 'href');
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
    public static function is_element_contained_in(\Dom_Element $needle, \Dom_Element $haystack): bool
    {
        if ($needle === $haystack) {
            return true;
        }
        foreach ($haystack->child_nodes as $child_node) {
            if (!$child_node instanceof \Dom_Element) {
                continue;
            }
            if (self::is_element_contained_in($needle, $child_node)) {
                return true;
            }
        }
        return false;
    }
}