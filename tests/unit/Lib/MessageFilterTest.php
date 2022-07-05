<?php

namespace Code\Tests\Unit\Lib;

use Code\Tests\Unit\UnitTestCase;
use Code\Lib\MessageFilter;

/**
 * @brief Unit Test case for HTTPSig class.
 *
 * @covers Code\Web\HTTPSig
 */
class MessageFilterTest extends UnitTestCase
{

    public function testLanguageFilter()
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

		$x = MessageFilter::evaluate(['body' => 'Die Botschafter der 30 Nato-Länder bringen den Beitritt von zwei jahrzehntelang neutralen Staaten auf den Weg. Schweden und Finnland können nun an Treffen des Militärbündnisses teilnehmen, aber noch nicht abstimmen.' ], 'lang=de', '');
    	$this->assertTrue($x);
            
    }   
}
