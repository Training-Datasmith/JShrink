<?php

/*
 * This file is part of the JShrink package.
 *
 * (c) Robert Hafner <tedivm@tedivm.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace J_Shrink\Test;

use J_Shrink\Minifier;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class J_Shrink_Test extends TestCase
{
    public function test_unclosed_comment_exception()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unclosed multiline comment at position: 1');
        Minifier::minify('/* This comment is hanging out.');
    }

    public function test_unclosed_string_exception()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unclosed string at position: 14');
        Minifier::minify('var string = "This string is hanging out.');
    }

    public function test_unclosed_regex_exception()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unclosed regex pattern at position: 23');
        Minifier::minify('var re = /[^A-Za-z0-9_
        var string = "Another Filler"');
    }

    /**
     * @dataProvider j_shrink_provider
     */
    public function test_j_shrink($test_name, $input, $output)
    {
        $this->assertEquals($output, Minifier::minify($input), 'Running JShrink Test: ' . $test_name);
    }

    /**
     * @dataProvider uglify_provider
     */
    public function test_uglify($test_name, $input, $output)
    {
        $this->assertEquals($output, Minifier::minify($input), 'Running Uglify Test: ' . $test_name);
    }

    /**
     * @group requests
     * @dataProvider request_provider
     */
    public function test_requests($test_name, $input, $output)
    {
        $this->assertEquals($output, Minifier::minify($input), 'Running User Requested Test: ' . $test_name);
    }

    /**
     * @group development
     * @dataProvider development_provider
     */
    public function test_development($test_name, $input, $output)
    {
        $this->assertEquals($output, Minifier::minify($input), 'Running Development Test: ' . $test_name);
    }

    /**
     * @param  string     $group
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    public function get_test_files($group)
    {
        $base_dir = __DIR__ . '/../../Resources/' . $group . '/';
        $test_dir = $base_dir . 'input/';
        $expect_dir = $base_dir . 'output/';

        $return_data = array();

        $test_files = scandir($test_dir);
        foreach ($test_files as $test_file) {
            if (substr($test_file, -3) !== '.js' || !file_exists($expect_dir . $test_file)) {
                continue;
            }

            $test_input = file_get_contents($test_dir . $test_file);
            $test_output = file_get_contents($expect_dir . $test_file);

            $return_data[] = array($test_file, $test_input, $test_output);
        }

        return $return_data;
    }

    public function uglify_provider()
    {
        return $this->get_test_files('uglify');
    }

    public function j_shrink_provider()
    {
        return $this->get_test_files('jshrink');
    }

    public function request_provider()
    {
        return $this->get_test_files('requests');
    }

    public function development_provider()
    {
        return $this->get_test_files('development');
    }
}
