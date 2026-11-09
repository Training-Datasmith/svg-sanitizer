<?php

declare(strict_types=1);

namespace enshrined\Svg_Sanitize\Tests;

use enshrined\Svg_Sanitize\Element_Reference\Subject;
use PHPUnit\Framework\TestCase;

class Subject_Test extends TestCase
{
    /** @var int */
    protected $nesting_limit = 15;

    /**
     * @test
     */
    public function one_level_counts_use_is_correct()
    {
        $first = new \DOMElement('first');
        $first_subject = new Subject($first, $this->nesting_limit);
        self::assertSame(0, $first_subject->count_use(false));
        self::assertSame(0, $first_subject->count_use(true));
    }

    /**
     * @test
     */
    public function two_levels_count_use_is_correct()
    {
        $first = new \DOMElement('first');
        $second = new \DOMElement('second');
        $first_subject = new Subject($first, $this->nesting_limit);
        $second_subject = new Subject($second, $this->nesting_limit);
        $first_subject->add_use($second_subject);
        $first_subject->add_use($second_subject);
        self::assertSame(2, $first_subject->count_use(false));
        self::assertSame(2, $first_subject->count_use(true));
    }

    /**
     * @test
     */
    public function three_levels_count_use_is_correct()
    {
        $first = new \DOMElement('first');
        $second = new \DOMElement('second');
        $third = new \DOMElement('third');
        $first_subject = new Subject($first, $this->nesting_limit);
        $second_subject = new Subject($second, $this->nesting_limit);
        $third_subject = new Subject($third, $this->nesting_limit);
        $first_subject->add_use($second_subject);
        $first_subject->add_use($second_subject);
        $second_subject->add_use($third_subject);
        $second_subject->add_use($third_subject);
        $second_subject->add_use($third_subject);
        self::assertSame(6, $first_subject->count_use(false));
        self::assertSame(8, $first_subject->count_use(true));
    }

    /**
     * @test
     */
    public function one_level_counts_used_in_is_correct()
    {
        $first = new \DOMElement('first');
        $first_subject = new Subject($first, $this->nesting_limit);
        self::assertSame(0, $first_subject->count_used_in());
    }

    /**
     * @test
     */
    public function two_levels_count_used_in_is_correct()
    {
        $first = new \DOMElement('first');
        $second = new \DOMElement('second');
        $first_subject = new Subject($first, $this->nesting_limit);
        $second_subject = new Subject($second, $this->nesting_limit);
        $second_subject->add_used_in($first_subject);
        $second_subject->add_used_in($first_subject);
        self::assertSame(2, $second_subject->count_used_in());
    }

    /**
     * @test
     */
    public function three_levels_count_used_in_is_correct()
    {
        $first = new \DOMElement('first');
        $second = new \DOMElement('second');
        $third = new \DOMElement('third');
        $first_subject = new Subject($first, $this->nesting_limit);
        $second_subject = new Subject($second, $this->nesting_limit);
        $third_subject = new Subject($third, $this->nesting_limit);
        $third_subject->add_used_in($second_subject);
        $third_subject->add_used_in($second_subject);
        $third_subject->add_used_in($second_subject);
        $second_subject->add_used_in($first_subject);
        $second_subject->add_used_in($first_subject);
        self::assertSame(6, $third_subject->count_used_in());
    }
}
