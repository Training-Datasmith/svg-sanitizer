<?php

declare(strict_types=1);

namespace enshrined\Svg_Sanitize\Tests;

use enshrined\Svg_Sanitize\Sanitizer;
use enshrined\Svg_Sanitize\Tests\Fixtures\Test_Allowed_Attributes;
use enshrined\Svg_Sanitize\Tests\Fixtures\Test_Allowed_Tags;
use PHPUnit\Framework\TestCase;

/**
 * Sanitizer integration tests
 */
class Sanitizer_Test extends TestCase
{
    /**
     * Make sure the initial tags are loaded
     */
    public function test_load_default_tags()
    {
        $sanitizer = new Sanitizer();
        $tags = $sanitizer->get_allowed_tags();

        self::assertSame('array', gettype($tags));
    }

    /**
     * Make sure the initial attributes are loaded
     */
    public function test_load_default_attributes()
    {
        $sanitizer = new Sanitizer();
        $attributes = $sanitizer->get_allowed_attrs();

        self::assertSame('array', gettype($attributes));
    }

    /**
     * Test the custom tag setters and getters
     */
    public function test_set_custom_tags()
    {
        $sanitizer = new Sanitizer();
        $sanitizer->set_allowed_tags(new Test_Allowed_Tags());
        $tags = $sanitizer->get_allowed_tags();

        self::assertSame('array', gettype($tags));
        self::assertSame(array_map('strtolower', Test_Allowed_Tags::get_tags()), $tags);
    }

    /**
     * Test the custom attribute setters and getters
     */
    public function test_set_custom_attributes()
    {
        $sanitizer = new Sanitizer();
        $sanitizer->set_allowed_attrs(new Test_Allowed_Attributes());
        $attributes = $sanitizer->get_allowed_attrs();

        self::assertSame('array', gettype($attributes));
        self::assertSame(array_map('strtolower', Test_Allowed_Attributes::get_attributes()), $attributes);
    }

    /**
     * Test that malicious elements and attributes are removed from standard XML
     */
    public function test_sanitize_xml_doc()
    {
        $data_directory = __DIR__ . '/data';
        $initial_data = file_get_contents($data_directory . '/xml_test_one.xml');
        $expected = file_get_contents($data_directory . '/xml_clean_one.xml');

        $sanitizer = new Sanitizer();
        $clean_data = $sanitizer->sanitize($initial_data);

        self::assertXmlStringEqualsXmlString($expected, $clean_data);
    }

    /**
     * Test that malicious elements and attributes are removed from an SVG
     */
    public function test_sanitize_svg_doc()
    {
        $data_directory = __DIR__ . '/data';
        $initial_data = file_get_contents($data_directory . '/svg_test_one.svg');
        $expected = file_get_contents($data_directory . '/svg_clean_one.svg');

        $sanitizer = new Sanitizer();
        $clean_data = $sanitizer->sanitize($initial_data);

        self::assertXmlStringEqualsXmlString($expected, $clean_data);
    }

    /**
     * Test that a badly formatted XML document returns false
     */
    public function test_bad_xml_returns_false()
    {
        $data_directory = __DIR__ . '/data';
        $initial_data = file_get_contents($data_directory . '/bad_xml_test_one.svg');

        $sanitizer = new Sanitizer();
        $clean_data = $sanitizer->sanitize($initial_data);

        self::assertFalse($clean_data);
        self::assertEquals(
            [
                [
                    'message' => 'Opening and ending tag mismatch: line line 8 and svg',
                    'line' => 15,
                ],
                [
                    'message' => 'Premature end of data in tag svg line 4',
                    'line' => 16,
                ],
            ],
            $sanitizer->get_xml_issues()
        );
    }

    /**
     * Make sure that hrefs get sanitized correctly
     */
    public function test_sanitize_hrefs()
    {
        $data_directory = __DIR__ . '/data';
        $initial_data = file_get_contents($data_directory . '/href_test_one.svg');
        $expected = file_get_contents($data_directory . '/href_clean_one.svg');

        $sanitizer = new Sanitizer();
        $clean_data = $sanitizer->sanitize($initial_data);

        self::assertXmlStringEqualsXmlString($expected, $clean_data);
    }

    /**
     * Make sure that hrefs get sanitized correctly when the xlink namespace is omitted.
     */
    public function test_sanitize_hrefsNoXlinkNamespace()
    {
        $data_directory = __DIR__ . '/data';
        $initial_data = file_get_contents($data_directory . '/href_test_two.svg');
        $expected = file_get_contents($data_directory . '/href_clean_two.svg');

        $sanitizer = new Sanitizer();
        $clean_data = $sanitizer->sanitize($initial_data);

        self::assertXmlStringEqualsXmlString($expected, $clean_data);
    }

    /**
     * Make sure that external references get sanitized correctly
     */
    public function test_sanitize_external()
    {
        $data_directory = __DIR__ . '/data';
        $initial_data = file_get_contents($data_directory . '/external_test.svg');
        $expected = file_get_contents($data_directory . '/external_clean.svg');

        $sanitizer = new Sanitizer();
        $sanitizer->remove_remote_references(true);
        $clean_data = $sanitizer->sanitize($initial_data);

        self::assertXmlStringEqualsXmlString($expected, $clean_data);
    }

    /**
     * Test that minification of an SVG works
     */
    public function test_sanitize_and_minifiy_svg_doc()
    {
        $data_directory = __DIR__ . '/data';
        $initial_data = file_get_contents($data_directory . '/svg_test_one.svg');
        $expected = file_get_contents($data_directory . '/svg_clean_one_minified.svg');

        $sanitizer = new Sanitizer();
        $sanitizer->minify(true);
        $clean_data = $sanitizer->sanitize($initial_data);

        self::assertXmlStringEqualsXmlString($expected, $clean_data);
    }

    /**
     * Test that ARIA and Data Attributes are allowed
     */
    public function test_that_aria_and_data_attributes_are_allowed()
    {
        $data_directory = __DIR__ . '/data';
        $initial_data = file_get_contents($data_directory . '/aria_data_test.svg');
        $expected = file_get_contents($data_directory . '/aria_data_clean.svg');

        $sanitizer = new Sanitizer();
        $sanitizer->minify(false);
        $clean_data = $sanitizer->sanitize($initial_data);

        self::assertXmlStringEqualsXmlString($expected, $clean_data);
    }

    /**
     * Test that ARIA and Data Attributes are allowed
     */
    public function test_that_external_use_elements_are_stripped()
    {
        $data_directory = __DIR__ . '/data';
        $initial_data = file_get_contents($data_directory . '/use_test.svg');
        $expected = file_get_contents($data_directory . '/use_clean.svg');

        $sanitizer = new Sanitizer();
        $sanitizer->minify(false);
        $clean_data = $sanitizer->sanitize($initial_data);

        self::assertXmlStringEqualsXmlString($expected, $clean_data);
    }

    /**
     * Test set_xml_options and minifying works as expected
     */
    public function test_minified_options()
    {
        $sanitizer = new Sanitizer();
        $sanitizer->minify(true);
        $sanitizer->remove_xml_tag(true);
        $sanitizer->set_xml_options(0);

        $input = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><title>chevron-double-down</title><path d="M4 11.73l.68-.73L12 17.82 19.32 11l.68.73-7.66 7.13a.5.5 0 0 1-.68 0z"/><path d="M4 5.73L4.68 5 12 11.82 19.32 5l.68.73-7.66 7.13a.5.5 0 0 1-.68 0z"/></svg>';
        $output = $sanitizer->sanitize($input);

        self::assertSame($input, $output);
    }

    /**
     * @test
     */
    public function use_recursions_are_detected()
    {
        $data_directory = __DIR__ . '/data';
        $initial_data = file_get_contents($data_directory . '/xlink_laughs_test.svg');
        $expected = file_get_contents($data_directory . '/xlink_laughs_clean.svg');

        $sanitizer = new Sanitizer();
        $sanitizer->minify(false);
        $clean_data = $sanitizer->sanitize($initial_data);

        self::assertXmlStringEqualsXmlString($expected, $clean_data);
    }

    /**
     * @test
     */
    public function infinite_use_loops_are_detected()
    {
        $data_directory = __DIR__ . '/data';
        $initial_data = file_get_contents($data_directory . '/xlink_loop_test.svg');
        $expected = file_get_contents($data_directory . '/xlink_loop_clean.svg');

        $sanitizer = new Sanitizer();
        $sanitizer->minify(false);
        $clean_data = $sanitizer->sanitize($initial_data);

        self::assertXmlStringEqualsXmlString($expected, $clean_data);
    }

    /**
     * @test
     */
    public function doctype_and_entity_are_removed()
    {
        $data_directory = __DIR__ . '/data';
        $initial_data = file_get_contents($data_directory . '/entity_test.svg');
        $expected = file_get_contents($data_directory . '/entity_clean.svg');

        $sanitizer = new Sanitizer();
        $sanitizer->minify(false);
        $sanitizer->remove_remote_references(true);
        $clean_data = $sanitizer->sanitize($initial_data);

        self::assertSame($expected, $clean_data);
    }

    /**
     * Make sure that DOS attacks using the <use> element are detected.
     */
    public function test_use_dos_attacks_are_nullified()
    {
        $data_directory = __DIR__ . '/data';
        $initial_data = file_get_contents($data_directory . '/use_dos_test.svg');
        $expected = file_get_contents($data_directory . '/use_dos_clean.svg');

        $sanitizer = new Sanitizer();
        $sanitizer->minify(false);
        $clean_data = $sanitizer->sanitize($initial_data);

        self::assertXmlStringEqualsXmlString($expected, $clean_data);
    }

    /**
     * Make sure that DOS attacks using the <use> element are detected,
     * especially when the SVG is extremely large.
     */
    public function test_large_use_dos_attacks_are_nullified()
    {
        $data_directory = __DIR__ . '/data';
        $initial_data = file_get_contents($data_directory . '/use_dos_test_two.svg');
        $expected = file_get_contents($data_directory . '/use_dos_clean_two.svg');

        $sanitizer = new Sanitizer();
        $sanitizer->minify(false);
        $clean_data = $sanitizer->sanitize($initial_data);

        self::assertXmlStringEqualsXmlString($expected, $clean_data);
    }

    public function test_invalid_nodes_are_handled()
    {
        $data_directory = __DIR__ . '/data';
        $initial_data = file_get_contents($data_directory . '/html_test.svg');
        $expected = file_get_contents($data_directory . '/html_clean.svg');

        $sanitizer = new Sanitizer();
        $sanitizer->minify(false);
        $clean_data = $sanitizer->sanitize($initial_data);

        self::assertXmlStringEqualsXmlString($expected, $clean_data);
    }

    /**
     * @test
     */
    public function cdata_section_is_sanitized()
    {
        $data_directory = __DIR__ . '/data';
        $initial_data = file_get_contents($data_directory . '/cdata_test.svg');
        $expected = file_get_contents($data_directory . '/cdata_clean.svg');

        $sanitizer = new Sanitizer();
        $sanitizer->minify(false);
        $clean_data = $sanitizer->sanitize($initial_data);

        self::assertXmlStringEqualsXmlString($expected, $clean_data);
    }

    /**
     * @test
     */
    public function cdata_background_section_is_sanitized()
    {
        $data_directory = __DIR__ . '/data';
        $initial_data = file_get_contents($data_directory . '/cdata_two_test.svg');
        $expected = file_get_contents($data_directory . '/cdata_two_clean.svg');

        $sanitizer = new Sanitizer();
        $sanitizer->minify(false);
        $clean_data = $sanitizer->sanitize($initial_data);

        self::assertXmlStringEqualsXmlString($expected, $clean_data);
    }

    /**
     * @test
     */
    public function form_data_is_sanitized()
    {
        $data_directory = __DIR__ . '/data';
        $initial_data = file_get_contents($data_directory . '/form_data_test.svg');
        $expected = file_get_contents($data_directory . '/form_data_clean.svg');

        $sanitizer = new Sanitizer();
        $sanitizer->minify(false);
        $clean_data = $sanitizer->sanitize($initial_data);

        self::assertXmlStringEqualsXmlString($expected, $clean_data);
    }

    /**
     * @test
     */
    public function malicious_svg_js_sanitized()
    {
        $data_directory = __DIR__ . '/data';
        $initial_data = file_get_contents($data_directory . '/malicious_js_and_php_test.svg');
        $expected = file_get_contents($data_directory . '/malicious_js_and_php_clean.svg');

        $sanitizer = new Sanitizer();
        $sanitizer->minify(false);
        $clean_data = $sanitizer->sanitize($initial_data);

        self::assertXmlStringEqualsXmlString($expected, $clean_data);
    }

    /**
     * @test
     */
    public function malicious_svg_php_tags_stripped()
    {
        $data_directory = __DIR__ . '/data';
        $initial_data = file_get_contents($data_directory . '/malicious_js_and_php_test.svg');

        $sanitizer = new Sanitizer();
        $sanitizer->minify(false);
        $clean_data = $sanitizer->sanitize($initial_data);

        $use_new_method = true;

        if (!method_exists($this, 'assertStringNotContainsStringIgnoringCase')) {
            $use_new_method = false;
        }

        foreach (['<?php', '<?='] as $value) {
            if ($use_new_method) {
                self::assertStringNotContainsStringIgnoringCase($value, $clean_data);
            } else {
                self::assertThat($clean_data, $this->logicalNot($this->stringContains($value, true)));
            }
        }
    }
}
