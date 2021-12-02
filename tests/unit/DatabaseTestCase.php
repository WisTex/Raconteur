<?php
/* Copyright (c) 2017 Hubzilla
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

namespace Zotlabs\Tests\Unit;

use PDO;
use PHPUnit\DbUnit\Database\Connection;
use PHPUnit\DbUnit\TestCaseTrait;
use PHPUnit\Framework\TestCase;
use function getenv;

/**
 * @brief Base class for our Database Unit Tests.
 *
 * @warning Never run these tests against a production database, because all
 * tables will get truncated and there is no way to recover without a backup.
 *
 * @author Klaus Weidenbach
 */
abstract class DatabaseTestCase extends TestCase {

	use TestCaseTrait;

	/**
	 * Only instantiate PDO once for test clean-up/fixture load.
	 *
	 * @var PDO
	 */
	static private $pdo = null;

	/**
	 * Only instantiate \PHPUnit\DbUnit\Database\Connection once per test.
	 *
	 * @var Connection
	 */
	private $conn = null;


	final public function getConnection() {
		if ($this->conn === null) {
			if (self::$pdo === null) {
				$dsn = getenv('hz_db_scheme') . ':host=' . getenv('hz_db_server')
					. ';port=' . getenv('hz_db_port') . ';dbname=' . getenv('hz_db_database');

				self::$pdo = new PDO($dsn, getenv('hz_db_user'), getenv('hz_db_pass'));
			}
			$this->conn = $this->createDefaultDBConnection(self::$pdo, getenv('hz_db_database'));
		}

		return $this->conn;
	}
}
