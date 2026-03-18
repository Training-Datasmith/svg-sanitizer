<?php

declare(strict_types=1);

namespace enshrined\svgSanitize\Tests\Fixtures;

use enshrined\svgSanitize\data\TagInterface;

class TestAllowedTags implements TagInterface
{
    /**
     * Returns an array of tags
     *
     * @return array
     */
    public static function getTags()
    {
        return [
            'testTag',
        ];
    }
}
