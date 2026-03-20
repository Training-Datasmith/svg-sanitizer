<?php

declare (strict_types=1);
namespace enshrined\Svg_Sanitize;

use enshrined\Svg_Sanitize\data\Allowed_Attributes;
use enshrined\Svg_Sanitize\data\Allowed_Tags;
use enshrined\Svg_Sanitize\data\Attribute_Interface;
use enshrined\Svg_Sanitize\data\Tag_Interface;
use enshrined\Svg_Sanitize\data\X_Path;
use enshrined\Svg_Sanitize\Element_Reference\Resolver;
/**
 * Class Sanitizer
 *
 * @package enshrined\svgSanitize
 */
class Sanitizer
{
    /**
     * @var \DOMDocument
     */
    protected $xml_document;
    /**
     * @var array
     */
    protected $allowed_tags;
    /**
     * @var array
     */
    protected $allowed_attrs;
    /**
     * @var
     */
    protected $xml_loader_value;
    /**
     * @var bool
     */
    protected $xml_error_handler_previous_value;
    /**
     * @var bool
     */
    protected $minify_xml = false;
    /**
     * @var bool
     */
    protected $remove_remote_references = false;
    /**
     * @var int
     */
    protected $use_threshold = 1000;
    /**
     * @var bool
     */
    protected $remove_xml_tag = false;
    /**
     * @var int
     */
    protected $xml_options = LIBXML_NOEMPTYTAG;
    /**
     * @var array
     */
    protected $xml_issues = [];
    /**
     * @var Resolver
     */
    protected $element_reference_resolver;
    /**
     * @var int
     */
    protected $use_nesting_limit = 15;
    /**
     * @var bool
     */
    protected $allow_huge_files = false;
    /**
     *
     */
    public function __construct()
    {
        // Load default tags/attributes
        $this->allowed_attrs = array_map('strtolower', Allowed_Attributes::get_attributes());
        $this->allowed_tags = array_map('strtolower', Allowed_Tags::get_tags());
    }
    /**
     * Set up the DOMDocument
     */
    protected function reset_internal()
    {
        $this->xml_document = new \Dom_Document();
        $this->xml_document->preserve_white_space = false;
        $this->xml_document->strict_error_checking = false;
        $this->xml_document->format_output = !$this->minify_xml;
    }
    /**
     * Set XML options to use when saving XML
     * See: DOMDocument::saveXML
     *
     * @param int  $xmlOptions
     */
    public function set_xml_options($xml_options): void
    {
        $this->xml_options = $xml_options;
    }
    /**
     * Get XML options to use when saving XML
     * See: DOMDocument::saveXML
     *
     * @return int
     */
    public function get_xml_options()
    {
        return $this->xml_options;
    }
    /**
     * Get the array of allowed tags
     *
     * @return array
     */
    public function get_allowed_tags()
    {
        return $this->allowed_tags;
    }
    /**
     * Set custom allowed tags
     */
    public function set_allowed_tags(Tag_Interface $allowed_tags): void
    {
        $this->allowed_tags = array_map('strtolower', $allowed_tags::get_tags());
    }
    /**
     * Get the array of allowed attributes
     *
     * @return array
     */
    public function get_allowed_attrs()
    {
        return $this->allowed_attrs;
    }
    /**
     * Set custom allowed attributes
     */
    public function set_allowed_attrs(Attribute_Interface $allowed_attrs): void
    {
        $this->allowed_attrs = array_map('strtolower', $allowed_attrs::get_attributes());
    }
    /**
     * Should we remove references to remote files?
     *
     * @param bool $removeRemoteRefs
     */
    public function remove_remote_references($remove_remote_refs = false): void
    {
        $this->remove_remote_references = $remove_remote_refs;
    }
    /**
     * Get XML issues.
     *
     * @return array
     */
    public function get_xml_issues()
    {
        return $this->xml_issues;
    }
    /**
     * Can we allow huge files?
     *
     * @return bool
     */
    public function get_allow_huge_files()
    {
        return $this->allow_huge_files;
    }
    /**
     * Set whether we can allow huge files.
     *
     * @param bool $allowHugeFiles
     */
    public function set_allow_huge_files($allow_huge_files): void
    {
        $this->allow_huge_files = $allow_huge_files;
    }
    /**
     * Sanitize the passed string
     *
     * @param string $dirty
     * @return string|false
     */
    public function sanitize($dirty)
    {
        // Don't run on an empty string
        if (empty($dirty)) {
            return '';
        }
        do {
            /*
             * recursively remove php tags because they can be hidden inside tags
             * i.e. <?p<?php test?>hp echo . ' danger! ';?>
             */
            $dirty = preg_replace('/<\?(=|php)(.+?)\?>/i', '', $dirty);
        } while (preg_match('/<\?(=|php)(.+?)\?>/i', $dirty) != 0);
        $this->reset_internal();
        $this->set_up_before();
        $loaded = $this->xml_document->load_xml($dirty, $this->get_allow_huge_files() ? LIBXML_PARSEHUGE : 0);
        // If we couldn't parse the XML then we go no further. Reset and return false
        if (!$loaded) {
            $this->xml_issues = self::get_xml_errors();
            $this->reset_after();
            return false;
        }
        // Pre-process all identified elements
        $x_path = new X_Path($this->xml_document);
        $this->element_reference_resolver = new Resolver($x_path, $this->use_nesting_limit);
        $this->element_reference_resolver->collect();
        $elements_to_remove = $this->element_reference_resolver->get_elements_to_remove();
        // Start the cleaning process
        $this->start_clean($this->xml_document->child_nodes, $elements_to_remove);
        // Save cleaned XML to a variable
        if ($this->remove_xml_tag) {
            $clean = $this->xml_document->save_xml($this->xml_document->document_element, $this->xml_options);
        } else {
            $clean = $this->xml_document->save_xml($this->xml_document, $this->xml_options);
        }
        $this->reset_after();
        // Remove any extra whitespaces when minifying
        if ($this->minify_xml) {
            return preg_replace('/\s+/', ' ', $clean);
        }
        // Return result
        return $clean;
    }
    /**
     * Set up libXML before we start
     */
    protected function set_up_before()
    {
        // This function has been deprecated in PHP 8.0 because in libxml 2.9.0, external entity loading is
        // disabled by default, so this function is no longer needed to protect against XXE attacks.
        if (\LIBXML_VERSION < 20900) {
            // Turn off the entity loader
            $this->xml_loader_value = libxml_disable_entity_loader(true);
        }
        // Suppress the errors because we don't really have to worry about formation before cleansing.
        // See reset in resetAfter().
        $this->xml_error_handler_previous_value = libxml_use_internal_errors(true);
        // Reset array of altered XML
        $this->xml_issues = [];
    }
    /**
     * Reset the class after use
     */
    protected function reset_after()
    {
        // This function has been deprecated in PHP 8.0 because in libxml 2.9.0, external entity loading is
        // disabled by default, so this function is no longer needed to protect against XXE attacks.
        if (\LIBXML_VERSION < 20900) {
            // Reset the entity loader
            libxml_disable_entity_loader($this->xml_loader_value);
        }
        libxml_clear_errors();
        libxml_use_internal_errors($this->xml_error_handler_previous_value);
    }
    /**
     * Start the cleaning with tags, then we move onto attributes and hrefs later
     */
    protected function start_clean(\Dom_Node_List $elements, array $elements_to_remove)
    {
        // loop through all elements
        // we do this backwards so we don't skip anything if we delete a node
        // see comments at: http://php.net/manual/en/class.domnamednodemap.php
        for ($i = $elements->length - 1; $i >= 0; $i--) {
            /** @var \DOMElement $currentElement */
            $current_element = $elements->item($i);
            /**
             * If the element has exceeded the nesting limit, we should remove it.
             *
             * As it's only <use> elements that cause us issues with nesting DOS attacks
             * we should check what the element is before removing it. For now we'll only
             * remove <use> elements.
             */
            if (in_array($current_element, $elements_to_remove) && 'use' === $current_element->node_name) {
                $current_element->parent_node->remove_child($current_element);
                $this->xml_issues[] = ['message' => 'Invalid \'' . $current_element->tag_name . '\'', 'line' => $current_element->get_line_no()];
                continue;
            }
            if ($current_element instanceof \Dom_Element) {
                // If the tag isn't in the whitelist, remove it and continue with next iteration
                if (!in_array(strtolower($current_element->tag_name), $this->allowed_tags)) {
                    $current_element->parent_node->remove_child($current_element);
                    $this->xml_issues[] = ['message' => 'Suspicious tag \'' . $current_element->tag_name . '\'', 'line' => $current_element->get_line_no()];
                    continue;
                }
                $this->clean_hrefs($current_element);
                $this->clean_xlink_hrefs($current_element);
                $this->clean_attributes_on_whitelist($current_element);
                if (strtolower($current_element->tag_name) === 'use') {
                    if ($this->is_use_tag_dirty($current_element) || $this->is_use_tag_exceeding_threshold($current_element)) {
                        $current_element->parent_node->remove_child($current_element);
                        $this->xml_issues[] = ['message' => 'Suspicious \'' . $current_element->tag_name . '\'', 'line' => $current_element->get_line_no()];
                        continue;
                    }
                }
                // Strip out font elements that will break out of foreign content.
                if (strtolower($current_element->tag_name) === 'font') {
                    $breaks_out_of_foreign_content = false;
                    for ($x = $current_element->attributes->length - 1; $x >= 0; $x--) {
                        // get attribute name
                        $attr_name = $current_element->attributes->item($x)->node_name;
                        if (in_array(strtolower($attr_name), ['face', 'color', 'size'])) {
                            $breaks_out_of_foreign_content = true;
                        }
                    }
                    if ($breaks_out_of_foreign_content) {
                        $current_element->parent_node->remove_child($current_element);
                        $this->xml_issues[] = ['message' => 'Suspicious tag \'' . $current_element->tag_name . '\'', 'line' => $current_element->get_line_no()];
                        continue;
                    }
                }
            }
            $this->clean_unsafe_nodes($current_element);
            if ($current_element->has_child_nodes()) {
                $this->start_clean($current_element->child_nodes, $elements_to_remove);
            }
        }
    }
    /**
     * Only allow attributes that are on the whitelist
     */
    protected function clean_attributes_on_whitelist(\Dom_Element $element)
    {
        for ($x = $element->attributes->length - 1; $x >= 0; $x--) {
            // get attribute name
            $attr_name = $element->attributes->item($x)->node_name;
            // Remove attribute if not in whitelist
            if (!in_array(strtolower($attr_name), $this->allowed_attrs) && !$this->is_aria_attribute(strtolower($attr_name)) && !$this->is_data_attribute(strtolower($attr_name))) {
                $element->remove_attribute($attr_name);
                $this->xml_issues[] = ['message' => 'Suspicious attribute \'' . $attr_name . '\'', 'line' => $element->get_line_no()];
            }
            /**
             * This is used for when a namespace isn't imported properly.
             * Such as xlink:href when the xlink namespace isn't imported.
             * We have to do this as the link is still ran in this case.
             */
            if (false !== stripos($attr_name, 'href')) {
                $href = $element->get_attribute($attr_name);
                if (false === $this->is_href_safe_value($href)) {
                    $element->remove_attribute($attr_name);
                    $this->xml_issues[] = ['message' => 'Suspicious attribute \'href\'', 'line' => $element->get_line_no()];
                }
            }
            // Do we want to strip remote references?
            if ($this->remove_remote_references) {
                // Remove attribute if it has a remote reference
                if (isset($element->attributes->item($x)->value) && $this->has_remote_reference($element->attributes->item($x)->value)) {
                    $element->remove_attribute($attr_name);
                    $this->xml_issues[] = ['message' => 'Suspicious attribute \'' . $attr_name . '\'', 'line' => $element->get_line_no()];
                }
            }
        }
    }
    /**
     * Clean the xlink:hrefs of script and data embeds
     */
    protected function clean_xlink_hrefs(\Dom_Element $element)
    {
        foreach ($element->attributes as $attribute) {
            // remove attributes with unexpected namespace prefix, e.g. `XLinK:href` (instead of `xlink:href`)
            if ($attribute->prefix === '' && strtolower($attribute->node_name) === 'xlink:href') {
                $element->remove_attribute($attribute->node_name);
                $this->xml_issues[] = ['message' => sprintf('Unexpected attribute \'%s\'', $attribute->node_name), 'line' => $element->get_line_no()];
            }
        }
        $this->clean_href_attributes($element, 'xlink');
    }
    /**
     * Clean the hrefs of script and data embeds
     */
    protected function clean_hrefs(\Dom_Element $element)
    {
        $this->clean_href_attributes($element);
    }
    protected function clean_href_attributes(\Dom_Element $element, string $prefix = ''): void
    {
        $relevant_attributes = array_filter(iterator_to_array($element->attributes), static function (\Dom_Attr $attr) use ($prefix): bool {
            return strtolower($attr->name) === 'href' && strtolower($attr->prefix) === $prefix;
        });
        foreach ($relevant_attributes as $attribute) {
            if (!$this->is_href_safe_value($attribute->value)) {
                $element->remove_attribute($attribute->node_name);
                $this->xml_issues[] = ['message' => sprintf('Suspicious attribute \'%s\'', $attribute->node_name), 'line' => $element->get_line_no()];
                continue;
            }
            // in case the attribute name is `HrEf`/`xlink:HrEf`, adjust it to `href`/`xlink:href`
            if (!in_array($attribute->node_name, $this->allowed_attrs, true) && in_array(strtolower($attribute->node_name), $this->allowed_attrs, true)) {
                $element->remove_attribute($attribute->node_name);
                $element->set_attribute(strtolower($attribute->node_name), $attribute->value);
            }
        }
    }
    /**
     * Only allow whitelisted starts to be within the href.
     *
     * This will stop scripts etc from being passed through, with or without attempting to hide bypasses.
     * This stops the need for us to use a complicated script regex.
     *
     * @param $value
     */
    protected function is_href_safe_value($value): bool
    {
        // Allow empty values
        if (empty($value)) {
            return true;
        }
        // Allow fragment identifiers.
        if ('#' === substr($value, 0, 1)) {
            return true;
        }
        // Allow relative URIs.
        if ('/' === substr($value, 0, 1)) {
            return true;
        }
        // Allow HTTPS domains.
        if ('https://' === substr($value, 0, 8)) {
            return true;
        }
        // Allow HTTP domains.
        if ('http://' === substr($value, 0, 7)) {
            return true;
        }
        // Allow known data URIs.
        if (in_array(substr($value, 0, 14), [
            'data:image/png',
            // PNG
            'data:image/gif',
            // GIF
            'data:image/jpg',
            // JPG
            'data:image/jpe',
            // JPEG
            'data:image/pjp',
        ])) {
            return true;
        }
        // Allow known short data URIs.
        if (in_array(substr($value, 0, 12), [
            'data:img/png',
            // PNG
            'data:img/gif',
            // GIF
            'data:img/jpg',
            // JPG
            'data:img/jpe',
            // JPEG
            'data:img/pjp',
        ])) {
            return true;
        }
        return false;
    }
    /**
     * Removes non-printable ASCII characters from string & trims it
     *
     * @param string $value
     */
    protected function remove_non_printable_characters($value): string
    {
        return trim(preg_replace('/[^ -~]/xu', '', $value));
    }
    /**
     * Does this attribute value have a remote reference?
     *
     * @param $value
     * @return bool
     */
    protected function has_remote_reference($value)
    {
        $value = $this->remove_non_printable_characters($value);
        $wrapped_in_url = preg_match('~^url\(\s*[\'"]\s*(.*)\s*[\'"]\s*\)$~xi', $value, $match);
        if (!$wrapped_in_url) {
            return false;
        }
        $value = trim($match[1], '\'"');
        return preg_match('~^((https?|ftp|file):)?//~xi', $value);
    }
    /**
     * Should we minify the output?
     *
     * @param bool $shouldMinify
     */
    public function minify($should_minify = false): void
    {
        $this->minify_xml = (bool) $should_minify;
    }
    /**
     * Should we remove the XML tag in the header?
     *
     * @param bool $removeXMLTag
     */
    public function remove_xml_tag($remove_xml_tag = false): void
    {
        $this->remove_xml_tag = (bool) $remove_xml_tag;
    }
    /**
     * Whether `<use ... xlink:href="#identifier">` elements shall be
     * removed in case expansion would exceed this threshold.
     *
     * @param int $useThreshold
     */
    public function use_threshold($use_threshold = 1000): void
    {
        $this->use_threshold = (int) $use_threshold;
    }
    /**
     * Check to see if an attribute is an aria attribute or not
     *
     * @param $attributeName
     */
    protected function is_aria_attribute($attribute_name): bool
    {
        return strpos($attribute_name, 'aria-') === 0;
    }
    /**
     * Check to see if an attribute is an data attribute or not
     *
     * @param $attributeName
     */
    protected function is_data_attribute($attribute_name): bool
    {
        return strpos($attribute_name, 'data-') === 0;
    }
    /**
     * Make sure our use tag is only referencing internal resources
     */
    protected function is_use_tag_dirty(\Dom_Element $element): bool
    {
        $href = Helper::get_element_href($element);
        return $href && strpos($href, '#') !== 0;
    }
    /**
     * Determines whether `<use ... xlink:href="#identifier">` is expanded
     * recursively in order to create DoS scenarios. The amount of a actually
     * used element needs to be below `$this->useThreshold`.
     */
    protected function is_use_tag_exceeding_threshold(\Dom_Element $element): bool
    {
        if ($this->use_threshold <= 0) {
            return false;
        }
        $use_id = Helper::extract_id_reference_from_href(Helper::get_element_href($element));
        if ($use_id === null) {
            return false;
        }
        foreach ($this->element_reference_resolver->find_by_element_id($use_id) as $subject) {
            if ($subject->count_use() >= $this->use_threshold) {
                return true;
            }
        }
        return false;
    }
    /**
     * Set the nesting limit for <use> tags.
     *
     * @param $limit
     */
    public function set_use_nesting_limit($limit): void
    {
        $this->use_nesting_limit = (int) $limit;
    }
    /**
     * Remove nodes that are either invalid or malformed.
     *
     * @param \DOMNode $currentElement The current element.
     */
    protected function clean_unsafe_nodes(\Dom_Node $current_element)
    {
        // Replace CDATA node with encoded text node
        if ($current_element instanceof \Dom_Cdata_Section) {
            $text_node = $current_element->owner_document->create_text_node($current_element->node_value);
            $current_element->parent_node->replace_child($text_node, $current_element);
            // If the element doesn't have a tagname, remove it and continue with next iteration
        } elseif (!$current_element instanceof \Dom_Element && !$current_element instanceof \Dom_Text) {
            $current_element->parent_node->remove_child($current_element);
            $this->xml_issues[] = ['message' => 'Suspicious node \'' . $current_element->node_name . '\'', 'line' => $current_element->get_line_no()];
            return;
        }
        if ($current_element->child_nodes && $current_element->child_nodes->length > 0) {
            for ($j = $current_element->child_nodes->length - 1; $j >= 0; $j--) {
                /** @var \DOMElement $childElement */
                $child_element = $current_element->child_nodes->item($j);
                $this->clean_unsafe_nodes($child_element);
            }
        }
    }
    /**
     * Retrieve array of errors
     */
    private static function get_xml_errors(): array
    {
        $errors = [];
        foreach (libxml_get_errors() as $error) {
            $errors[] = ['message' => trim($error->message), 'line' => $error->line];
        }
        return $errors;
    }
}