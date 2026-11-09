<?php

declare(strict_types=1);

namespace enshrined\Svg_Sanitize\Tests;

use enshrined\Svg_Sanitize\data\Allowed_Tags;
use PHPUnit\Framework\TestCase;

class Allowed_Tags_Test extends TestCase
{
    public function test_it_implements_the_interface()
    {
        $class = new Allowed_Tags();
        self::assertInstanceOf('enshrined\Svg_Sanitize\data\Tag_Interface', $class);
    }

    public function test_that_it_returns_an_array()
    {
        $result = Allowed_Tags::get_tags();
        self::assertSame('array', gettype($result));
    }
}
