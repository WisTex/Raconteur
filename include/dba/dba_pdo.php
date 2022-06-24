<?php

require_once 'include/dba/dba_driver.php';

/**
 * @brief PDO based database driver.
 *
 */
class dba_pdo extends dba_driver
{

    public $driver_dbtype = null;

    /**
     * {@inheritDoc}
     * @see dba_driver::connect()
     */
    public function connect($server, $scheme, $port, $user, $pass, $db)
    {

        $this->driver_dbtype = $scheme;

        if (strpbrk($server, ':;')) {
            $dsn = $server;
            $this->driver_dbtype = substr($dsn,0,strpos($dsn,':'));
        }
        else {
            $dsn = $this->driver_dbtype . ':host=' . $server . (intval($port) ? ';port=' . $port : '');
        }

        $dsn .= ';dbname=' . $db;

        // allow folks to over-ride the client encoding by setting it explicitly
        // in the dsn. By default everything we do is in utf8 and for mysql this
        // requires specifying utf8mb4.
         
        if ($this->driver_dbtype === 'mysql' && !strpos($dsn,'charset=')) {
            $dsn .= ';charset=utf8mb4';
        }

        if ($this->driver_type === 'pgsql' && !strpos($dsn,'client_encoding')) {
            $dsn .= ";options='--client_encoding=UTF8'";
        }
    
        try {
            $this->db = new PDO($dsn, $user, $pass);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        catch (PDOException $e) {
            if (file_exists('dbfail.out')) {
                file_put_contents('dbfail.out', datetime_convert() . "\nConnect: " . $e->getMessage() . "\n", FILE_APPEND);
            }

            return false;
        }

        if ($this->driver_dbtype === 'pgsql') {
            $this->q("SET standard_conforming_strings = 'off'; SET backslash_quote = 'on';");
        }

        $this->connected = true;

        return true;
    }

    /**
     * {@inheritDoc}
     * @return bool|array|PDOStatement
     *   - \b false if not connected or PDOException occured on query
     *   - \b array with results on a SELECT query
     *   - \b PDOStatement on a non SELECT SQL query
     * @see dba_driver::q()
     *
     */
    public function q($sql)
    {
        if ((!$this->db) || (!$this->connected)) {
            return false;
        }

        if ($this->driver_dbtype === 'pgsql') {
            if (substr(rtrim($sql), -1, 1) !== ';') {
                $sql .= ';';
            }
        }

        $result = null;
        $this->error = '';
        $select = ((stripos($sql, 'select') === 0 || stripos($sql, 'show') === 0 || stripos($sql, 'explain') === 0) ? true : false);

        try {
            $result = $this->db->query($sql, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            // Filter error 1062 which happens on race conditions and should be inside a transaction
            // and rolled back. Put the message in the regular logfile just in case it is not; but
            // keep the dbfail.out file clean to discover new development issues and bad sql.
            if ($this->error) {
                db_logger('dba_pdo: ERROR: ' . printable($sql) . "\n" . $this->error, LOGGER_NORMAL, LOG_ERR);
                if (file_exists('dbfail.out') && strpos($this->error, 'Duplicate entry') === false) {
                    file_put_contents('dbfail.out', datetime_convert() . "\n" . printable($sql) . "\n" . $this->error . "\n", FILE_APPEND);
                }
            }
        }

        if (!($select)) {
            if ($this->debug) {
                db_logger('dba_pdo: DEBUG: ' . printable($sql) . ' returns ' . (($result) ? 'true' : 'false'), LOGGER_NORMAL, (($result) ? LOG_INFO : LOG_ERR));
            }
            return $result;
        }

        $r = [];
        if ($result) {
            foreach ($result as $x) {
                $r[] = $x;
            }
        }

        if ($this->debug) {
            db_logger('dba_pdo: DEBUG: ' . printable($sql) . ' returned ' . count($r) . ' results.', LOGGER_NORMAL, LOG_INFO);
            if (intval($this->debug) > 1) {
                db_logger('dba_pdo: ' . printable(print_r($r, true)), LOGGER_NORMAL, LOG_INFO);
            }
        }

        return (($this->error) ? false : $r);
    }

    public function escape($str)
    {
        if ($this->db && $this->connected) {
            return substr(substr(@$this->db->quote($str), 1), 0, -1);
        }
    }

    public function close()
    {
        if ($this->db) {
            $this->db = null;
        }

        $this->connected = false;
    }

    public function concat($fld, $sep)
    {
        if ($this->driver_dbtype === 'pgsql') {
            return 'string_agg(' . $fld . ',\'' . $sep . '\')';
        } else {
            return 'GROUP_CONCAT(DISTINCT ' . $fld . ' SEPARATOR \'' . $sep . '\')';
        }
    }

    public function use_index($str)
    {
        if ($this->driver_dbtype === 'pgsql') {
            return '';
        } else {
            return 'USE INDEX( ' . $str . ')';
        }
    }

    public function quote_interval($txt)
    {
        if ($this->driver_dbtype === 'pgsql') {
            return "'$txt'";
        } else {
            return $txt;
        }
    }

    public function escapebin($str)
    {
        return $this->escape($str);
    }

    public function unescapebin($str)
    {
        return $str;
    }


    public function getdriver()
    {
        return 'pdo';
    }
}
