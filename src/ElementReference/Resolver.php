<?php

declare (strict_types=1);
namespace enshrined\Svg_Sanitize\Element_Reference;

use enshrined\Svg_Sanitize\data\X_Path;
use enshrined\Svg_Sanitize\Exceptions\Nesting_Exception;
use enshrined\Svg_Sanitize\Helper;
class Resolver
{
    /**
     * @var XPath
     */
    protected $x_path;
    /**
     * @var Subject[]
     */
    protected $subjects = [];
    /**
     * @var array DOMElement[]
     */
    protected $elements_to_remove = [];
    /**
     * @var int
     */
    protected $use_nesting_limit;
    public function __construct(X_Path $x_path, $use_nesting_limit)
    {
        $this->x_path = $x_path;
        $this->use_nesting_limit = $use_nesting_limit;
    }
    public function collect(): void
    {
        $this->collect_identified_elements();
        $this->process_references();
        $this->determine_invalid_subjects();
    }
    /**
     * Resolves one subject by element.
     *
     * @param bool $considerChildren Whether to search in Subject's children as well
     * @return Subject|null
     */
    public function find_by_element(\Dom_Element $element, $consider_children = false)
    {
        foreach ($this->subjects as $subject) {
            if ($element === $subject->get_element() || $consider_children && Helper::is_element_contained_in($element, $subject->get_element())) {
                return $subject;
            }
        }
        return null;
    }
    /**
     * Resolves subjects (plural!) by element id - in theory malformed
     * DOM might have same ids assigned to different elements and leaving
     * it to client/browser implementation which element to actually use.
     *
     * @param string $elementId
     * @return Subject[]
     */
    public function find_by_element_id($element_id): array
    {
        return array_filter($this->subjects, function (Subject $subject) use ($element_id): bool {
            return $element_id === $subject->get_element_id();
        });
    }
    /**
     * Collects elements having `id` attribute (those that can be referenced).
     */
    protected function collect_identified_elements()
    {
        /** @var \DOMNodeList|\DOMElement[] $elements */
        $elements = $this->x_path->query('//*[@id]');
        foreach ($elements as $element) {
            $this->subjects[$element->get_attribute('id')] = new Subject($element, $this->use_nesting_limit);
        }
    }
    /**
     * Processes references from and to elements having `id` attribute concerning
     * their occurrence in `<use ... xlink:href="#identifier">` statements.
     */
    protected function process_references()
    {
        $use_node_name = $this->x_path->create_node_name('use');
        foreach ($this->subjects as $subject) {
            $use_elements = $this->x_path->query($use_node_name . '[@href or @xlink:href]', $subject->get_element());
            /** @var \DOMElement $useElement */
            foreach ($use_elements as $use_element) {
                $use_id = Helper::extract_id_reference_from_href(Helper::get_element_href($use_element));
                if ($use_id === null) {
                    continue;
                }
                if (!isset($this->subjects[$use_id])) {
                    continue;
                }
                $subject->add_use($this->subjects[$use_id]);
                $this->subjects[$use_id]->add_used_in($subject);
            }
        }
    }
    /**
     * Determines and tags infinite loops.
     */
    protected function determine_invalid_subjects()
    {
        foreach ($this->subjects as $subject) {
            if (in_array($subject->get_element(), $this->elements_to_remove)) {
                continue;
            }
            $use_id = Helper::extract_id_reference_from_href(Helper::get_element_href($subject->get_element()));
            try {
                if ($use_id === $subject->get_element_id()) {
                    $this->mark_subject_as_invalid($subject);
                } elseif ($subject->has_infinite_loop()) {
                    $this->mark_subject_as_invalid($subject);
                }
            } catch (Nesting_Exception $e) {
                $this->elements_to_remove[] = $e->get_element();
                $this->mark_subject_as_invalid($subject);
            }
        }
    }
    /**
     * Get all the elements that caused a nesting exception.
     *
     * @return array
     */
    public function get_elements_to_remove()
    {
        return $this->elements_to_remove;
    }
    /**
     * The Subject is invalid for some reason, therefore we should
     * remove it and all it's child usages.
     */
    protected function mark_subject_as_invalid(Subject $subject)
    {
        $this->elements_to_remove = array_merge($this->elements_to_remove, $subject->clear_internal_and_get_affected_elements());
    }
}