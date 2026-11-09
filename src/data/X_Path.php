<?php

declare (strict_types=1);
namespace enshrined\Svg_Sanitize\data;

class X_Path extends \DOMXPath
{
    public const DEFAULT_NAMESPACE_PREFIX = 'svg';
    /**
     * @var string
     */
    protected $default_namespace_uri;
    public function __construct(\DOMDocument $doc)
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
        $this->default_namespace_uri = (string) $root_elements[0]->namespaceURI;
        if ($this->default_namespace_uri !== '') {
            $this->registerNamespace(self::DEFAULT_NAMESPACE_PREFIX, $this->default_namespace_uri);
        }
    }
    /**
     * @return \DOMElement[]
     */
    protected function get_root_elements(): array
    {
        $root_elements = [];
        $elements = $this->document->getElementsByTagName('svg');
        /** @var \DOMElement $element */
        foreach ($elements as $element) {
            if ($element->parentNode !== $this->document) {
                continue;
            }
            $root_elements[] = $element;
        }
        return $root_elements;
    }
}