<?php

declare(strict_types=1);

namespace enshrined\svgSanitize\Tests\Fixtures;

use enshrined\svgSanitize\data\AttributeInterface;

class TestAllowedAttributes implements AttributeInterface
{
    /**
     * Returns an array of attributes
     *
     * @return array
     */
    public static function getAttributes()
    {
        return [
            'testAttribute',
        ];
    }
}
