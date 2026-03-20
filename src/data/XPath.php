<?php

declare (strict_types=1);
namespace enshrined\Svg_Sanitize\data;

class X_Path extends \Domx_Path
{
    public const DEFAULT_NAMESPACE_PREFIX = 'svg';
    /**
     * @var string
     */
    protected $default_namespace_uri;
    public function __construct(\Dom_Document $doc)
    {
        parent::__construct($doc);
        $this->handle_default_namespace();
    }
    public function create_node_name(string $node_name): string
    {
        if (empty($this->default_namespace_uri)) {
            return $node_name;
        }
        return self::DEFAULT_NAMESPACE_PREFIX . ':' . $node_name;
    }
    protected function handle_default_namespace()
    {
        $root_elements = $this->get_root_elements();
        if (count($root_elements) !== 1) {
            throw new \LogicException(sprintf('Got %d svg elements, expected exactly one', count($root_elements)), 1570870568);
        }
        $this->default_namespace_uri = (string) $root_elements[0]->namespace_uri;
        if ($this->default_namespace_uri !== '') {
            $this->register_namespace(self::DEFAULT_NAMESPACE_PREFIX, $this->default_namespace_uri);
        }
    }
    /**
     * @return \DOMElement[]
     */
    protected function get_root_elements(): array
    {
        $root_elements = [];
        $elements = $this->document->get_elements_by_tag_name('svg');
        /** @var \DOMElement $element */
        foreach ($elements as $element) {
            if ($element->parent_node !== $this->document) {
                continue;
            }
            $root_elements[] = $element;
        }
        return $root_elements;
    }
}