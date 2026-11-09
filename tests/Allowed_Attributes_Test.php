<?php

declare(strict_types=1);

namespace enshrined\Svg_Sanitize\Tests;

use enshrined\Svg_Sanitize\data\Allowed_Attributes;
use PHPUnit\Framework\TestCase;

class Allowed_Attributes_Test extends TestCase
{
    public function test_it_implements_the_interface()
    {
        $class = new Allowed_Attributes();
        self::assertInstanceOf('enshrined\Svg_Sanitize\data\Attribute_Interface', $class);
    }

    public function test_that_it_returns_an_array()
    {
        $result = Allowed_Attributes::get_attributes();
        self::assertSame('array', gettype($result));
    }
}
