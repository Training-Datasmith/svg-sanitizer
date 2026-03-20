<?php

declare (strict_types=1);
namespace enshrined\Svg_Sanitize\data;

/**
 * Interface TagInterface
 *
 * @package enshrined\svgSanitize\tags
 */
interface Tag_Interface
{
    /**
     * Returns an array of tags
     *
     * @return array
     */
    public static function get_tags();
}