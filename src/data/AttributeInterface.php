<?php

declare (strict_types=1);
namespace enshrined\Svg_Sanitize\data;

/**
 * Class AttributeInterface
 *
 * @package enshrined\svgSanitize\data
 */
interface Attribute_Interface
{
    /**
     * Returns an array of attributes
     *
     * @return array
     */
    public static function get_attributes();
}