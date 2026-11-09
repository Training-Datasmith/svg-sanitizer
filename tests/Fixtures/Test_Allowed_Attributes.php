<?php

declare(strict_types=1);

namespace enshrined\Svg_Sanitize\Tests\Fixtures;

use enshrined\Svg_Sanitize\data\Attribute_Interface;

class Test_Allowed_Attributes implements Attribute_Interface
{
    public static function get_attributes()
    {
        return [
            'test_attribute',
        ];
    }
}
