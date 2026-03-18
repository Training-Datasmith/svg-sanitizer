<?php

declare(strict_types=1);

namespace enshrined\svgSanitize\data;

/**
 * Interface TagInterface
 *
 * @package enshrined\svgSanitize\tags
 */
interface TagInterface
{
    /**
     * Returns an array of tags
     *
     * @return array
     */
    public static function getTags();

}
