<?php
/*
 * Copyright (c) 2018 Hubzilla
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace Code\Tests\Unit\Web;

use phpmock\phpunit\PHPMock;
use Code\Tests\Unit\UnitTestCase;

use Code\Web\HTTPSig;

/**
 * @brief Unit Test case for HTTPSig class.
 *
 * @covers Code\Web\HTTPSig
 */
class HttpSigTest extends UnitTestCase
{

    use PHPMock;

    /**
     * @dataProvider generate_digestProvider
     */
    public function testGenerate_digest($text, $digest)
    {
        $this->assertSame(
            $digest,
            HTTPSig::generate_digest_header($text, 'sha256')
        );
    }

    public function generate_digestProvider()
    {
        return [
            'empty body text' => [
                '',
                'SHA-256=47DEQpj8HBSa+/TImW+5JCeuQeRkm5NMpJWZG3hSuFU='
            ],
            'sample body text' => [
                'body text',
                'SHA-256=2fu8kUkvuzuo5XyhWwORNOcJgDColXgxWkw1T5EXzPI='
            ],
            'NULL body text' => [
                null,
                'SHA-256=47DEQpj8HBSa+/TImW+5JCeuQeRkm5NMpJWZG3hSuFU='
            ],
        ];
    }

    public function testGeneratedDigestsOfDifferentTextShouldNotBeEqual()
    {
        $this->assertNotSame(
            HTTPSig::generate_digest_header('text1'),
            HTTPSig::generate_digest_header('text2')
        );
    }


}
