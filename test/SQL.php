<?php

use COREPOS\common\sql\CharSets;

class SQL extends PHPUnit_Framework_TestCase
{
    public function testSQL()
    {
        $dbc = new COREPOS\common\SQLManager('localhost', 'PDO_MYSQL', 'test', 'root', '');
        // this test is only going to work under CI
        if (!$dbc->isConnected()) {
            return;
        }
        $dbc->throwOnFailure(true);
        $this->assertEquals('test', $dbc->defaultDatabase());

        $this->assertEquals(false, $dbc->addConnection('127.0.0.1:3306', 'PDO_MYSQL', 'test', 'notRoot', ''));
        try {
            $dbc->query('not connected', array(1, 2));
        } catch (Exception $ex) {
            echo $ex->getMessage();
        }
        $this->assertEquals(false, $dbc->addConnection('localhost', '', 'test', 'root', '', true));
        $this->assertEquals(true, $dbc->addConnection('localhost', 'MYSQLI', 'test', 'root', ''));

        $this->assertEquals(true, $dbc->isConnected());
        $this->assertEquals(true, $dbc->isConnected('test'));
        $this->assertEquals(false, $dbc->isConnected('foo'));

        $this->assertEquals(true, $dbc->selectDB('test'));
        $this->assertEquals("'foo'", $dbc->escape('foo'));
        $this->assertEquals('CURDATE()', $dbc->curdate());
        $this->assertEquals('datediff(foo,bar)', $dbc->datediff('foo', 'bar'));
        $this->assertEquals("period_diff(date_format(foo, '%Y%m'), date_format(bar, '%Y%m'))", $dbc->monthdiff('foo', 'bar'));
        $this->assertEquals("DATE_FORMAT(FROM_DAYS(DATEDIFF(foo,bar)), '%Y')+0", $dbc->yeardiff('foo', 'bar'));
        $this->assertEquals('TIMESTAMPDIFF(SECOND,foo,bar)', $dbc->seconddiff('foo', 'bar'));
        $this->assertEquals('week(foo) - week(bar)', $dbc->weekdiff('foo', 'bar'));
        $this->assertEquals("DATE_FORMAT(foo,'%Y%m%d')", $dbc->dateymd('foo'));
        $this->assertEquals("CONVERT(foo,SIGNED)", $dbc->convert('foo', 'int'));
        $this->assertEquals("LOCATE(foo,f)", $dbc->locate('foo', 'f'));
        $this->assertEquals("CONCAT(foo,bar)", $dbc->concat('foo','bar','test'));
        $this->assertEquals("(foo BETWEEN '2000-01-01 00:00:00' AND '2000-01-01 23:59:59')", $dbc->dateEquals('foo', '2000-01-01'));
        $this->assertEquals("DATE_FORMAT(foo,'%w')+1", $dbc->dayofweek('foo'));
        $this->assertEquals("DATE_FORMAT(foo,'%H')", $dbc->hour('foo'));
        $this->assertEquals('decimal(10,2)', $dbc->currency());
        $this->assertEquals('SELECT 1 LIMIT 1', $dbc->addSelectLimit('SELECT 1', 1));

        list($in, $args) = $dbc->safeInClause(array());
        $this->assertEquals('?', $in);
        $this->assertEquals(array(-999999), $args);

        $this->assertNotEquals('unknown', $dbc->connectionType());
        $this->assertEquals('unknown', $dbc->connectionType('foo'));

        $this->assertEquals(false, $dbc->setDefaultDB('foo'));
        $this->assertEquals(true, $dbc->setDefaultDB('test'));

        $res = $dbc->queryAll('SELECT 1 AS one');
        $this->assertNotEquals(false, $res);
        $this->assertEquals(1, $dbc->numRows($res));
        $this->assertEquals(1, $dbc->numFields($res));
        $this->assertEquals(false, $dbc->numRows(false));
        $this->assertEquals(true, $dbc->dataSeek($res, 0));

        $res = $dbc->query('SELECT ' . $dbc->curtime() . ' AS val');
        $this->assertNotEquals(false, $res);

        $dbc->startTransaction();
        $dbc->query('SELECT 1 AS one');
        $dbc->commitTransaction();
        $dbc->startTransaction();
        $dbc->query('SELECT 1 AS one');
        $dbc->rollbackTransaction();

        $query = 'SELECT * FROM mock';
        $arg_sets = array(array(), array(), array());
        $this->assertEquals(true, $dbc->executeAsTransaction($query, $arg_sets));

        $res = $dbc->query('SELECT ' . $dbc->week($dbc->now()) . ' AS val');
        $this->assertNotEquals(false, $res);

        $this->assertEquals(false, $dbc->tableDefinition('not_real_table'));
        $this->assertEquals(false, $dbc->detailedDefinition('not_real_table'));
        $this->assertEquals(false, $dbc->isView('not_real_table'));
        $this->assertEquals(false, $dbc->isView('mock'));
        $this->assertEquals(true, $dbc->isView('vmock'));
        $this->assertInternalType('string', $dbc->getViewDefinition('vmock'));
        $this->assertEquals(false, $dbc->getViewDefinition('mock'));

        $tables = $dbc->getTables();
        $this->assertInternalType('array', $tables);

        $this->assertEquals('test', $dbc->defaultDatabase());

        $prep = $dbc->prepare('SELECT 1 AS one');
        $this->assertEquals(1, $dbc->getValue($prep));
        $this->assertNotEquals(0, count($dbc->getRow($prep)));
        $this->assertNotEquals(0, count($dbc->matchingColumns('mock', 'mock')));

        $r = $dbc->query('SELECT 1 AS one');
        $dbc->fetch_array($r);
        $r = $dbc->query('SELECT 1 AS one');
        $dbc->fetchObject($r);

        $badDef = array('not'=>'real');
        $this->assertEquals(true, $dbc->cacheTableDefinition('mock', $badDef));
        $this->assertEquals($badDef, $dbc->tableDefinition('mock'));
        $this->assertEquals(true, $dbc->clearTableCache());
        $this->assertNotEquals($badDef, $dbc->tableDefinition('mock'));

        $this->assertNotEquals(false, $dbc->getMatchingColumns('mock', 'test', 'mock', 'test'));

        $this->assertNotEquals(false, $dbc->smartInsert('mock', array(
            'val' => 'row2',
            'nonColumn' => 'foo',
        )));
        $this->assertNotEquals(false, $dbc->smartUpdate('mock', array(
            'val' => 'row2',
            'nonColumn' => 'foo',
        ), 'id=2'));
        $this->assertEquals(true, $dbc->transfer('test', 'select val from mock', 'test', 'insert into mock (val)'));
        $dbc->query('TRUNCATE TABLE mock');
        $dbc->affectedRows();
        $dbc->error();

        $prep = $dbc->prepare('SELECT val FROM mock WHERE id=?');
        $this->assertEquals(false, $dbc->getValue($prep, 1));
        $this->assertEquals(false, $dbc->getRow($prep, 1));

        $this->assertEquals('tmock', $dbc->temporaryTable('tmock', 'mock'));

        $this->assertEquals(true, $dbc->close());
        $dbc->close('', true);

        $dbc->setQueryLog(null);
        $this->assertEquals(false, $dbc->logger('foo'));
        $dbc->setQueryLog(new COREPOS\common\BaseLogger());
        $this->assertEquals(true, $dbc->logger('foo'));
    }

    public function testSqlLib()
    {
        $this->assertInternalType('array', COREPOS\common\sql\Lib::getDrivers());
    }

    public function testAdapters()
    {
        $adapters = array('Mssql', 'Mysql', 'Pgsql', 'Sqlite');
        $con = new MockSQL();
        foreach ($adapters as $adapter) {
            $class = 'COREPOS\\common\\sql\\' . $adapter . 'Adapter';
            $obj = new $class();
            $this->assertInternalType('string', $obj->createNamedDB('foo'));
            $this->assertInternalType('string', $obj->useNamedDB('foo'));
            $this->assertInternalType('string', $obj->identifierEscape('foo'));
            $this->assertInternalType('string', $obj->defaultDatabase());
            $this->assertInternalType('string', $obj->temporaryTable('foo','bar'));
            $this->assertInternalType('string', $obj->sep());
            $this->assertInternalType('string', $obj->addSelectLimit('SELECT * FROM table', 5));
            $this->assertInternalType('string', $obj->currency());
            $this->assertInternalType('string', $obj->curtime());
            $this->assertInternalType('string', $obj->datediff('date1', 'date2'));
            $this->assertInternalType('string', $obj->monthdiff('date1', 'date2'));
            $this->assertInternalType('string', $obj->yeardiff('date1', 'date2'));
            $this->assertInternalType('string', $obj->weekdiff('date1', 'date2'));
            $this->assertInternalType('string', $obj->seconddiff('date1', 'date2'));
            $this->assertInternalType('string', $obj->dateymd('date1'));
            $this->assertInternalType('string', $obj->dayofweek('date1'));
            $this->assertInternalType('string', $obj->convert('date1','int'));
            $this->assertInternalType('string', $obj->locate('date1','te'));
            $this->assertInternalType('string', $obj->concat(array('1','2','3')));
            $this->assertInternalType('string', $obj->setLockTimeout(5));
        }
        
        MockSQL::clear();

        $obj = new COREPOS\common\sql\MssqlAdapter();
        $this->assertEquals(false, $obj->getViewDefinition('foo', $con, 'foo')); 
        MockSQL::addResult(array(0=>'foo'));
        $this->assertEquals('foo', $obj->getViewDefinition('foo', $con, 'foo')); 
        MockSQL::clear();

        $obj = new COREPOS\common\sql\MysqlAdapter();
        $this->assertEquals(false, $obj->getViewDefinition('foo', $con, 'foo')); 

        $obj = new COREPOS\common\sql\PgsqlAdapter();
        $this->assertEquals(false, $obj->getViewDefinition('foo', $con, 'foo')); 
        MockSQL::addResult(array('oid'=>'foo'));
        $this->assertEquals(false, $obj->getViewDefinition('foo', $con, 'foo')); 
        MockSQL::addResult(array('oid'=>'foo'));
        MockSQL::addResult(array(0=>'foo'));
        $this->assertEquals('foo', $obj->getViewDefinition('foo', $con, 'foo')); 
        MockSQL::clear();

        $obj = new COREPOS\common\sql\SqliteAdapter();
        MockSQL::addResult(array('sql'=>'foo'));
        $this->assertEquals('foo', $obj->getViewDefinition('foo', $con, 'foo')); 
        MockSQL::clear();
    }

    public function testCharsets()
    {
        $this->assertEquals('latin1', CharSets::get('mysql', 'iso-8859-1'));
        $this->assertEquals(false, CharSets::get('pgsql', 'invalid-encoding'));
    }

    function testWrapper()
    {
        $con = new MockSQL();
        $w = new COREPOS\common\sql\ConnectionWrapper($con, 'foo');
        $this->assertEquals(true, $w->query('SELECT 1'));
        $this->assertEquals(true, $w->prepare('SELECT 1'));
        try {
            $w->asdf();
        } catch (Exception $e) {}
    }
}

