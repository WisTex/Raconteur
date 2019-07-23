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
use Zotlabs\Access\Permissions;

/**
 * @brief Unit Test case for Permissions class.
 *
 * @covers Zotlabs\Access\Permissions
 */
class PermissionsTest extends UnitTestCase {

	use PHPMock;

	public function testVersion() {
		$expectedVersion = 3;

		// static call
		$this->assertEquals($expectedVersion, Permissions::version());

		// instance call
		$p = new Permissions();
		$this->assertEquals($expectedVersion, $p->version());
	}

	/**
	 * @coversNothing
	 */
	public function testVersionEqualsPermissionRoles() {
		$p = new Permissions();
		$pr = new \Zotlabs\Access\PermissionRoles();
		$this->assertEquals($p->version(), $pr->version());
	}

	/**
	 * @uses ::call_hooks
	 */
	public function testPerms() {
		// There are 18 default perms
		$permsCount = 18;

		// Create a stub for global function t() with expectation
		$t = $this->getFunctionMock('Zotlabs\Access', 't');
		$t->expects($this->exactly(2*$permsCount))->willReturnCallback(
				function ($string) {
					return $string;
				}
		);

		// static method Perms()
		$perms = Permissions::Perms();

		$p = new Permissions();
		$this->assertEquals($perms, $p->Perms());

		$this->assertEquals($permsCount, count($perms), "There should be $permsCount permissions.");

		$this->assertEquals('Can view my channel stream and posts', $perms['view_stream']);

		// non existent perm should not be set
		$this->assertFalse(isset($perms['invalid_perm']));
	}

	/**
	 * filter parmeter is only used in hook \b permissions_list. So the result
	 * in this test should be the same as if there was no filter parameter.
	 *
	 * @todo Stub call_hooks() function and also test filter
	 *
	 * @uses ::call_hooks
	 */
	public function testPermsFilter() {
		// There are 18 default perms
		$permsCount = 18;

		// Create a stub for global function t() with expectation
		$t = $this->getFunctionMock('Zotlabs\Access', 't');
		$t->expects($this->exactly(2*$permsCount))->willReturnCallback(
				function ($string) {
					return $string;
				}
		);

		$perms = Permissions::Perms('view_');
		$this->assertEquals($permsCount, count($perms));

		$this->assertEquals('Can view my channel stream and posts', $perms['view_stream']);

		$perms = Permissions::Perms('invalid_perm');
		$this->assertEquals($permsCount, count($perms));
	}

	/**
	 * Better should mock Permissions::Perms, but not possible with static methods.
	 *
	 * @uses ::call_hooks
	 *
	 * @dataProvider FilledPermsProvider
	 *
	 * @param array $permarr An indexed permissions array to pass
	 * @param array $expected The expected result perms array
	 */
	public function testFilledPerms($permarr, $expected) {
		// Create a stub for global function t()
		$t = $this->getFunctionMock('Zotlabs\Access', 't');

		$this->assertEquals($expected, Permissions::FilledPerms($permarr));
	}
	/**
	 * @return array An associative array with test values for FilledPerms()
	 *   * \e array Indexed array which is passed as parameter to FilledPerms()
	 *   * \e array Expected associative result array with filled perms
	 */
	public function FilledPermsProvider() {
		return [
				'Empty param array' => [
						[],
						[
								'view_stream' => 0,
								'send_stream' => 0,
								'view_profile' => 0,
								'view_contacts' => 0,
								'view_storage' => 0,
								'write_storage' => 0,
								'view_pages' => 0,
								'view_wiki' => 0,
								'write_pages' => 0,
								'write_wiki' => 0,
								'post_wall' => 0,
								'post_comments' => 0,
								'post_mail' => 0,
								'post_like' => 0,
								'tag_deliver' => 0,
								'chat' => 0,
								'republish' => 0,
								'delegate' => 0
						]
				],
				'provide view_stream and view_pages as param' => [
						['view_stream', 'view_pages'],
						[
								'view_stream' => 1,
								'send_stream' => 0,
								'view_profile' => 0,
								'view_contacts' => 0,
								'view_storage' => 0,
								'write_storage' => 0,
								'view_pages' => 1,
								'view_wiki' => 0,
								'write_pages' => 0,
								'write_wiki' => 0,
								'post_wall' => 0,
								'post_comments' => 0,
								'post_mail' => 0,
								'post_like' => 0,
								'tag_deliver' => 0,
								'chat' => 0,
								'republish' => 0,
								'delegate' => 0
						]
				],
				'provide an unknown param' => [
						['view_stream', 'unknown_perm'],
						[
								'view_stream' => 1,
								'send_stream' => 0,
								'view_profile' => 0,
								'view_contacts' => 0,
								'view_storage' => 0,
								'write_storage' => 0,
								'view_pages' => 0,
								'view_wiki' => 0,
								'write_pages' => 0,
								'write_wiki' => 0,
								'post_wall' => 0,
								'post_comments' => 0,
								'post_mail' => 0,
								'post_like' => 0,
								'tag_deliver' => 0,
								'chat' => 0,
								'republish' => 0,
								'delegate' => 0
						]
				]
		];
	}
	/**
	 * @uses ::call_hooks
	 */
	public function testFilledPermsNull() {
		// Create a stub for global function t() with expectation
		$t = $this->getFunctionMock('Zotlabs\Access', 't');
		$t->expects($this->atLeastOnce());
		// Create a stub for global function bt() with expectations
		$bt = $this->getFunctionMock('Zotlabs\Access', 'btlogger');
		$bt->expects($this->once())->with($this->equalTo('FilledPerms: null'));

		$result = [
				'view_stream' => 0,
				'send_stream' => 0,
				'view_profile' => 0,
				'view_contacts' => 0,
				'view_storage' => 0,
				'write_storage' => 0,
				'view_pages' => 0,
				'view_wiki' => 0,
				'write_pages' => 0,
				'write_wiki' => 0,
				'post_wall' => 0,
				'post_comments' => 0,
				'post_mail' => 0,
				'post_like' => 0,
				'tag_deliver' => 0,
				'chat' => 0,
				'republish' => 0,
				'delegate' => 0
		];

		$this->assertEquals($result, Permissions::FilledPerms(null));
	}

	/**
	 * @dataProvider OPermsProvider
	 *
	 * @param array $permarr The params to pass to the OPerms method
	 * @param array $expected The expected result
	 */
	public function testOPerms($permarr, $expected) {
		$this->assertEquals($expected, Permissions::OPerms($permarr));
	}
	/**
	 * @return array An associative array with test values for OPerms()
	 *   * \e array Array with perms to test
	 *   * \e array Expected result array
	 */
	public function OPermsProvider() {
		return [
				'empty' => [
						[],
						[]
				],
				'valid' => [
						['perm1' => 1, 'perm2' => 0],
						[['name' => 'perm1', 'value' => 1], ['name' => 'perm2', 'value' => 0]]
				],
				'null array' => [
						null,
						[]
				]
		];
	}

	/**
	 * @dataProvider permsCompareProvider
	 *
	 * @param array $p1 The first permission
	 * @param array $p2 The second permission
	 * @param boolean $expectedresult The expected result of the tested method
	 */
	public function testPermsCompare($p1, $p2, $expectedresult) {
		$this->assertEquals($expectedresult, Permissions::PermsCompare($p1, $p2));
	}
	/**
	 * @return array An associative array with test values for PermsCompare()
	 *   * \e array 1st array with perms
	 *   * \e array 2nd array with perms
	 *   * \e boolean expected result for the perms comparison
	 */
	public function permsCompareProvider() {
		return [
				'equal' => [
						['perm1' => 1, 'perm2' => 0],
						['perm1' => 1, 'perm2' => 0],
						true
				],
				'different values' => [
						['perm1' => 1, 'perm2' => 0],
						['perm1' => 0, 'perm2' => 1],
						false
				],
				'different order' => [
						['perm1' => 1, 'perm2' => 0],
						['perm2' => 0, 'perm1' => 1],
						true
				],
				'partial first in second' => [
						['perm1' => 1],
						['perm1' => 1, 'perm2' => 0],
						true
				],
				'partial second in first' => [
						['perm1' => 1, 'perm2' => 0],
						['perm1' => 1],
						false
				]
		];
	}
}
