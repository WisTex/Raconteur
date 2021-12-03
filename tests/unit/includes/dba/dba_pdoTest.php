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

namespace Zotlabs\Tests\Unit\includes;

use dba_driver;
use dba_pdo;
use PHPUnit\DbUnit\DataSet\IDataSet;
use Zotlabs\Tests\Unit\DatabaseTestCase;
use PHPUnit\DbUnit\TestCaseTrait;
use PHPUnit\DbUnit\DataSet\YamlDataSet;
use function getenv;

require_once 'include/dba/dba_pdo.php';

/**
 * @brief Unit Test case for include/dba/dba_pdo.php file.
 *
 * Some very basic tests to see if our database layer can connect to a real
 * database.
 */
class dba_pdoTest extends DatabaseTestCase {

	use TestCaseTrait;

	/**
	 * @var dba_driver
	 */
	protected $dba;


	/**
	 * Set initial state of the database before each test is executed.
	 * Load database fixtures.
	 *
	 * @return IDataSet
	 */
	public function getDataSet() {
		return new YamlDataSet(dirname(__FILE__) . '/_files/account.yml');
	}

	protected function setUp() {
		// Will invoke getDataSet() to load fixtures into DB
		parent::setUp();

		$this->dba = new dba_pdo(
				getenv('hz_db_server'),
				getenv('hz_db_scheme'),
				getenv('hz_db_port'),
				getenv('hz_db_user'),
				getenv('hz_db_pass'),
				getenv('hz_db_database')
		);
	}
	protected function assertPreConditions() {
		$this->assertSame('pdo', $this->dba->getdriver(), "Driver is expected to be 'pdo'.");
		$this->assertInstanceOf('dba_driver', $this->dba);
		$this->assertTrue($this->dba->connected, 'Pre condition failed, DB is not connected.');
		$this->assertInstanceOf('PDO', $this->dba->db);
	}
	protected function tearDown() {
		$this->dba = null;
	}


	/**
	 * @group mysql
	 */
	public function testQuoteintervalOnMysql() {
		$this->assertSame('value', $this->dba->quote_interval('value'));
	}
	/**
	 * @group postgresql
	 */
	public function testQuoteintervalOnPostgresql() {
		$this->assertSame("'value'", $this->dba->quote_interval('value'));
	}

	/**
	 * @group mysql
	 */
	public function testGenerateMysqlConcatSql() {
		$this->assertSame('GROUP_CONCAT(DISTINCT field SEPARATOR \';\')', $this->dba->concat('field', ';'));
		$this->assertSame('GROUP_CONCAT(DISTINCT field2 SEPARATOR \' \')', $this->dba->concat('field2', ' '));
	}
	/**
	 * @group postgresql
	 */
	public function testGeneratePostgresqlConcatSql() {
		$this->assertSame('string_agg(field,\';\')', $this->dba->concat('field', ';'));
		$this->assertSame('string_agg(field2,\' \')', $this->dba->concat('field2', ' '));
	}


	public function testConnectToSqlServer() {
		// connect() is done in dba_pdo constructor which is called in setUp()
		$this->assertTrue($this->dba->connected);
	}

	/**
	 * @depends testConnectToSqlServer
	 */
	public function testCloseSqlServerConnection() {
		$this->dba->close();

		$this->assertNull($this->dba->db);
		$this->assertFalse($this->dba->connected);
	}

	/**
	 * @depends testConnectToSqlServer
	 */
	public function testSelectQueryShouldReturnArray() {
		$ret = $this->dba->q('SELECT * FROM account');

		$this->assertTrue(is_array($ret));
	}

	/**
	 * @depends testConnectToSqlServer
	 */
	public function testInsertQueryShouldReturnPdostatement() {
		// Fixture account.yml adds two entries to account table
		$this->assertEquals(2, $this->getConnection()->getRowCount('account'), 'Pre-Condition');

		$ret = $this->dba->q('INSERT INTO account
				(account_id, account_email, account_language)
				 VALUES (100, \'insert@example.com\', \'de\')
		');
		$this->assertInstanceOf('PDOStatement', $ret);

		$this->assertEquals(3, $this->getConnection()->getRowCount('account'), 'Inserting failed');
	}


	public function testConnectToWrongSqlServer() {
		$nodba = new dba_pdo('wrongserver',
				getenv('hz_db_scheme'), getenv('hz_db_port'),
				getenv('hz_db_user'), getenv('hz_db_pass'),
				getenv('hz_db_database')
		);

		$this->assertSame('pdo', $nodba->getdriver());
		$this->assertInstanceOf('dba_pdo', $nodba);
		$this->assertFalse($nodba->connected);
		$this->assertNull($nodba->db);

		$this->assertFalse($nodba->q('SELECT * FROM account'));
	}

	/**
	 * @depends testConnectToSqlServer
	 */
	public function testSelectQueryToNonExistentTableShouldReturnFalse() {
		$ret = $this->dba->q('SELECT * FROM non_existent_table');

		$this->assertFalse($ret);
	}

	/**
	 * @depends testConnectToSqlServer
	 */
	public function testInsertQueryToNonExistentTableShouldReturnEmptyArray() {
		$ret = $this->dba->q('INSERT INTO non_existent_table
				(account_email, account_language)
				 VALUES (\'email@example.com\', \'en\')
		');

		$this->assertNotInstanceOf('PDOStatement', $ret);
		$this->isEmpty($ret);
	}

}
