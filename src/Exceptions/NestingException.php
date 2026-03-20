<?php

declare (strict_types=1);
namespace enshrined\Svg_Sanitize\Exceptions;

use Exception;
class Nesting_Exception extends \Exception
{
    /**
     * @var \DOMElement
     */
    protected $element;
    /**
     * NestingException constructor.
     *
     * @param string           $message
     * @param int              $code
     */
    public function __construct($message = '', $code = 0, ?Exception $previous = null, ?\Dom_Element $element = null)
    {
        $this->element = $element;
        parent::__construct($message, $code, $previous);
    }
    /**
     * Get the element that caused the exception.
     *
     * @return \DOMElement
     */
    public function get_element()
    {
        return $this->element;
    }
}