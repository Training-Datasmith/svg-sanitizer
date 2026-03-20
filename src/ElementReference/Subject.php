<?php

declare (strict_types=1);
namespace enshrined\Svg_Sanitize\Element_Reference;

class Subject
{
    /**
     * @var \DOMElement
     */
    protected $element;
    /**
     * @var Usage[]
     */
    protected $use_collection = [];
    /**
     * @var Usage[]
     */
    protected $used_in_collection = [];
    /**
     * @var int
     */
    protected $use_nesting_limit;
    /**
     * Subject constructor.
     *
     * @param int         $useNestingLimit
     */
    public function __construct(\Dom_Element $element, $use_nesting_limit)
    {
        $this->element = $element;
        $this->use_nesting_limit = $use_nesting_limit;
    }
    /**
     * @return \DOMElement
     */
    public function get_element()
    {
        return $this->element;
    }
    public function get_element_id(): string
    {
        return $this->element->get_attribute('id');
    }
    /**
     * @param array $subjects   Previously processed subjects
     * @param int   $level      The current level of nesting.
     * @throws \enshrined\svgSanitize\Exceptions\NestingException
     */
    public function has_infinite_loop(array $subjects = [], $level = 1): bool
    {
        if ($level > $this->use_nesting_limit) {
            throw new \enshrined\Svg_Sanitize\Exceptions\Nesting_Exception('Nesting level too high, aborting', 1570713498, null, $this->get_element());
        }
        if (in_array($this, $subjects, true)) {
            return true;
        }
        $subjects[] = $this;
        foreach ($this->use_collection as $usage) {
            if ($usage->get_subject()->has_infinite_loop($subjects, $level + 1)) {
                return true;
            }
        }
        return false;
    }
    public function add_use(Subject $subject): void
    {
        if ($subject === $this) {
            throw new \LogicException('Cannot add self usage', 1570713416);
        }
        $identifier = $subject->get_element_id();
        if (isset($this->use_collection[$identifier])) {
            $this->use_collection[$identifier]->increment();
            return;
        }
        $this->use_collection[$identifier] = new Usage($subject);
    }
    public function add_used_in(Subject $subject): void
    {
        if ($subject === $this) {
            throw new \LogicException('Cannot add self as usage', 1570713417);
        }
        $identifier = $subject->get_element_id();
        if (isset($this->used_in_collection[$identifier])) {
            $this->used_in_collection[$identifier]->increment();
            return;
        }
        $this->used_in_collection[$identifier] = new Usage($subject);
    }
    /**
     * @param bool $accumulated
     * @return int
     */
    public function count_use($accumulated = false)
    {
        $count = 0;
        foreach ($this->use_collection as $use) {
            $use_count = $use->get_subject()->count_use();
            $count += $use->get_count() * ($accumulated ? 1 + $use_count : max(1, $use_count));
        }
        return $count;
    }
    /**
     * @return int
     */
    public function count_used_in()
    {
        $count = 0;
        foreach ($this->used_in_collection as $used_in) {
            $count += $used_in->get_count() * max(1, $used_in->get_subject()->count_used_in());
        }
        return $count;
    }
    /**
     * Clear the internal arrays (to free up memory as they can get big)
     * and return all the child usages DOMElement's
     */
    public function clear_internal_and_get_affected_elements(): array
    {
        $elements = array_map(function (Usage $usage) {
            return $usage->get_subject()->get_element();
        }, $this->use_collection);
        $this->used_in_collection = [];
        $this->use_collection = [];
        return $elements;
    }
}