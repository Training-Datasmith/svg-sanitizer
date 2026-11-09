<?php

declare(strict_types=1);

namespace enshrined\Svg_Sanitize\Tests\Fixtures;

use enshrined\Svg_Sanitize\data\Tag_Interface;

class Test_Allowed_Tags implements Tag_Interface
{
    public static function get_tags()
    {
        return [
            'test_tag',
        ];
    }
}
