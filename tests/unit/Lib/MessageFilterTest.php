<?php

namespace Code\Tests\Unit\Lib;

use Code\Tests\Unit\UnitTestCase;
use phpmock\phpunit\PHPMock;
use Code\Lib\MessageFilter;

include 'boot.php';
sys_boot();

/**
 * @brief Unit Test case for HTTPSig class.
 *
 * @covers Code\Web\HTTPSig
 */
class MessageFilterTest extends UnitTestCase
{
    use PHPMock;
    
    /** @test */
    public function languageFilterTests()
    {
        // Check accept language rules
    
        $x = MessageFilter::evaluate([ 'body' => 'abcde' ], 'lang=en', '');
        $this->assertTrue($x);

        $x = MessageFilter::evaluate([ 'body' => 'the quick brown fox jumped over the lazy dog. Therefore the world is flat.' ], 'lang=en', '');
        $this->assertTrue($x);
    
        $x = MessageFilter::evaluate([ 'body' => 'abcde' ], 'lang!=en', '');
        $this->assertTrue($x);

        $x = MessageFilter::evaluate([ 'body' => 'the quick brown fox jumped over the lazy dog. Therefore the world is flat.' ], 'lang!=en', '');
        $this->assertFalse($x);

        // check deny language rules
    
        $x = MessageFilter::evaluate([ 'body' => 'abcde' ], '', 'lang=en');
        $this->assertTrue($x);

        $x = MessageFilter::evaluate([ 'body' => 'the quick brown fox jumped over the lazy dog. Therefore the world is flat.' ], '', 'lang=en');
        $this->assertFalse($x);
    
        $x = MessageFilter::evaluate([ 'body' => 'abcde' ], '', 'lang!=en');
        $this->assertTrue($x);

        $x = MessageFilter::evaluate([ 'body' => 'the quick brown fox jumped over the lazy dog. Therefore the world is flat.' ], '', 'lang!=en');
        $this->assertTrue($x);



    
    
    }   



}
