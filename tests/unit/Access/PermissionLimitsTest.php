<?php
/*
 * Copyright (c) 2017 Hubzilla
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

namespace Zotlabs\Tests\Unit\Access;

use phpmock\phpunit\PHPMock;
use Zotlabs\Tests\Unit\UnitTestCase;
use Zotlabs\Access\PermissionLimits;

/**
 * @brief Unit Test case for PermissionLimits class.
 *
 * @covers Zotlabs\Access\PermissionLimits
 */
class PermissionLimitsTest extends UnitTestCase {

	use PHPMock;

	/**
	 * @todo If we could replace static call to Permissions::Perms() in
	 * Std_Limits() we could better unit test this method, now we test the
	 * result of Permissions::Perms() mostly.
	 *
	 * @uses Zotlabs\Access\Permissions::Perms
	 * @uses ::call_hooks
	 */
	public function testStd_Limits() {
		// There are 18 default perms
		$permsCount = 18;

		// Create a stub for global function t() with expectation
		$t = $this->getFunctionMock('Zotlabs\Access', 't');
		$t->expects($this->exactly($permsCount));

		$stdlimits = PermissionLimits::Std_Limits();
		$this->assertCount($permsCount, $stdlimits, "There should be $permsCount permissions.");

		$this->assertEquals(PERMS_PUBLIC,   $stdlimits['view_stream']);
		$this->assertEquals(PERMS_SPECIFIC, $stdlimits['send_stream']);
		$this->assertEquals(PERMS_PUBLIC,   $stdlimits['view_profile']);
		$this->assertEquals(PERMS_PUBLIC,   $stdlimits['view_contacts']);
		$this->assertEquals(PERMS_PUBLIC,   $stdlimits['view_storage']);
		$this->assertEquals(PERMS_SPECIFIC, $stdlimits['write_storage']);
		$this->assertEquals(PERMS_PUBLIC,   $stdlimits['view_pages']);
		$this->assertEquals(PERMS_PUBLIC,   $stdlimits['view_wiki']);
		$this->assertEquals(PERMS_SPECIFIC, $stdlimits['write_pages']);
		$this->assertEquals(PERMS_SPECIFIC, $stdlimits['write_wiki']);
		$this->assertEquals(PERMS_SPECIFIC, $stdlimits['post_wall']);
		$this->assertEquals(PERMS_SPECIFIC, $stdlimits['post_comments']);
		$this->assertEquals(PERMS_SPECIFIC, $stdlimits['post_mail']);
		$this->assertEquals(PERMS_SPECIFIC, $stdlimits['post_like']);
		$this->assertEquals(PERMS_SPECIFIC, $stdlimits['tag_deliver']);
		$this->assertEquals(PERMS_SPECIFIC, $stdlimits['chat']);
		$this->assertEquals(PERMS_SPECIFIC, $stdlimits['republish']);
		$this->assertEquals(PERMS_SPECIFIC, $stdlimits['delegate']);
	}

}