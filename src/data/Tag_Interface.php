<?php

declare (strict_types=1);
namespace enshrined\Svg_Sanitize\data;

/**
 * Tag_Interface contract
 *
 * @package enshrined\Svg_Sanitize\data
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