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

use Zotlabs\Tests\Unit\UnitTestCase;
use Zotlabs\Access\PermissionRoles;
use phpmock\phpunit\PHPMock;

/**
 * @brief Unit Test case for PermissionRoles class.
 *
 * @TODO Work around dependencies to static PermissionLimits methods.
 *
 * @covers Zotlabs\Access\PermissionRoles
 */
class PermissionRolesTest extends UnitTestCase {

	use PHPMock;

	public function testVersion() {
		$expectedVersion = 2;

		$this->assertEquals($expectedVersion, PermissionRoles::version());

		$pr = new PermissionRoles();
		$this->assertEquals($expectedVersion, $pr->version());
	}


	public function testRoles() {
		// Create a stub for global function t() with expectation
		$t = $this->getFunctionMock('Zotlabs\Access', 't');
		$t->expects($this->atLeastOnce())->willReturnCallback(
				function ($string) {
					return $string;
				}
		);

		$roles = PermissionRoles::roles();
		$r = new PermissionRoles();
		$this->assertEquals($roles, $r->roles());

		$socialNetworking = [
				'social_federation' => 'Social - Federation',
				'social' => 'Social - Mostly Public',
				'social_restricted' => 'Social - Restricted',
				'social_private' => 'Social - Private'
		];

		$this->assertArraySubset(['Social Networking' => $socialNetworking], $roles);
		$this->assertEquals($socialNetworking, $roles['Social Networking']);

		$this->assertCount(5, $roles, 'There should be 5 permission groups.');

		$this->assertCount(1, $roles['Other'], "In the 'Other' group should be just one permission role");
	}


	/**
	 * @uses ::call_hooks
	 * @uses Zotlabs\Access\PermissionLimits::Std_Limits
	 * @uses Zotlabs\Access\Permissions::Perms
	 */
	public function testRole_perms() {
		// Create a stub for global function t()
		$t = $this->getFunctionMock('Zotlabs\Access', 't');
		$t = $this->getFunctionMock('Zotlabs\Access', 'get_config');

		$rp_social = PermissionRoles::role_perms('social');
		$this->assertEquals('social', $rp_social['role']);


		$rp_custom = PermissionRoles::role_perms('custom');
		$this->assertEquals(['role' => 'custom'], $rp_custom);

		$rp_nonexistent = PermissionRoles::role_perms('nonexistent');
		$this->assertEquals(['role' => 'nonexistent'], $rp_nonexistent);
	}

}
