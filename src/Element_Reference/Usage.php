<?php

declare (strict_types=1);
namespace enshrined\Svg_Sanitize\Element_Reference;

class Usage
{
    /**
     * @var Subject
     */
    protected $subject;
    /**
     * @var int
     */
    protected $count;
    /**
     * @param int $count
     */
    public function __construct(Subject $subject, $count = 1)
    {
        $this->subject = $subject;
        $this->count = (int) $count;
    }
    /**
     * @param int $by
     */
    public function increment($by = 1): void
    {
        $this->count += (int) $by;
    }
    /**
     * @return Subject
     */
    public function get_subject()
    {
        return $this->subject;
    }
    /**
     * @return int
     */
    public function get_count()
    {
        return $this->count;
    }
}