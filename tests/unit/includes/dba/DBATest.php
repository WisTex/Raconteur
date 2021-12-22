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

use DBA;
use Zotlabs\Tests\Unit\UnitTestCase;

// required because of process isolation and no autoloading
require_once 'include/dba/dba_driver.php';

/**
 * @brief Unit Test case for include/dba/DBA.php file.
 *
 * This test needs process isolation because of static \DBA.
 * @runTestsInSeparateProcesses
 */
class DBATest extends UnitTestCase
{

    public function testDbaFactoryMysql()
    {
        $this->assertNull(DBA::$dba);

        $ret = DBA::dba_factory('server', 'port', 'user', 'pass', 'db', '0');
        $this->assertInstanceOf('dba_pdo', $ret);
        $this->assertFalse($ret->connected);

        $this->assertSame('mysql', DBA::$scheme);
        $this->assertSame('schema_mysql.sql', DBA::$install_script);
        $this->assertSame('0001-01-01 00:00:00', DBA::$null_date);
        $this->assertSame('UTC_TIMESTAMP()', DBA::$utc_now);
        $this->assertSame('`', DBA::$tquot);
    }

    public function testDbaFactoryPostgresql()
    {
        $this->assertNull(DBA::$dba);

        $ret = DBA::dba_factory('server', 'port', 'user', 'pass', 'db', '1');
        $this->assertInstanceOf('dba_pdo', $ret);
        $this->assertFalse($ret->connected);

        $this->assertSame('pgsql', DBA::$scheme);
        $this->assertSame('schema_postgres.sql', DBA::$install_script);
        $this->assertSame('0001-01-01 00:00:00', DBA::$null_date);
        $this->assertSame("now() at time zone 'UTC'", DBA::$utc_now);
        $this->assertSame('"', DBA::$tquot);
    }
}
