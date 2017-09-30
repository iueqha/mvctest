<?php
namespace app\model;
class Base {
    protected $_medoo;
    protected $_db;
    protected $_table;
    protected $_primary_key;

    protected $_operatorId=0;
    const MASTER = false; //master database
    const SLAVE = true;   //slave  database

    const STATUS_OK = 1;
    const STATUS_DELETED = 14;
    protected static $medooPool = [];

    const MAX_IDLE_TIME = 7200;

    public static function getConnection($dbName)
    {
        global $dbConfig;
        $config = $dbConfig;
        $key = $config['server'] . ':' . $config['port'] . ':' . $dbName;

        if (isset(static::$medooPool[$key]) && static::$medooPool[$key]['medoo'] !== NULL) {
            $medoo = static::$medooPool[$key]['medoo'];
            if ((time() - static::MAX_IDLE_TIME >= static::$medooPool[$key]['last_access_time']) or !is_object($medoo->pdo)) {
                $medoo->pdo = NULL;  // close the connection
                $medoo = NULL;
                unset(static::$medooPool[$key]);
                return static::createConnection($dbName, $key, $config);
            }
            static::$medooPool[$key]['last_access_time'] = time();
            return $medoo;
        } else {
            return static::createConnection($dbName, $key, $config);
        }
    }
    private static function createConnection($dbName, $key, $config)
    {
        $config['database_type'] = 'mysql';
        $config['database_name'] = empty($config['db_name']) ? $dbName : $config['db_name'];
        $config['option'] = [
            \PDO::ATTR_PERSISTENT => false,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_ORACLE_NULLS => \PDO::NULL_TO_STRING
        ];

        $medoo = new \Medoo($config);

        static::$medooPool[$key]['medoo'] = $medoo;
        static::$medooPool[$key]['last_access_time'] = time();
        return $medoo;
    }
    /**
     * fetch one row with all fields by primary key
     * @param int $id primary key value
     * @return mixed
     *     @example array('key'=>value) when succeed
     *     @example false when data not exists or fail
     */
    public function find($id) {
        $this->_medoo = static::getConnection($this->_db);
        return $this->_medoo->get($this->_table, '*', [$this->_primary_key => $id]);
    }

    /**
     * fetch one row with given fields by condition
     *
     * @param array $where   condition
     * @param mixed $fields  string/array
     * @return mixed
     *     @example array('key'=>value) when succeed
     *     @example false when data not exists or fail
     */
    public function fetchRow($where, $fields = '*') {
        $this->_medoo = static::getConnection($this->_db);
        return $this->_medoo->get($this->_table, $fields, $where);
    }

    /**
     * fetch all rows with given fields by condition
     *
     * @param array $where   condition
     * @param mixed $fields  string/array
     * @return mixed
     *     @example [['key'=>value1],['key'=>value2]] when succeed
     *     @example array() when data not exists
     *     @example false when query fail
     */
    public function fetchAll($where, $fields = '*') {

        $this->_medoo = static::getConnection($this->_db);

        return $this->_medoo->select($this->_table, $fields, $where);
    }

    /**
     * insert data.support batch insert,but carry out with multi sql statements,
     * so batchInsert method is recommended with batch insert.
     *
     * @param array $data
     *     @example insert one row once:['key' => value]
     *     @example insert multi rows once: [['key'=>value1],['key'=>value2]]
     *
     * @return mixed
     *     @example int reutrn last insert id if succeed,0 when fail
     *     @example array the return array dimension is equal with the $data
     *         [1(last insert id), 0(fail)]
     */
    public function insert($data) {
        $this->_medoo = static::getConnection($this->_db);
        return $this->_medoo->insert($this->_table, $data);
    }

    /**
     * @param array $data
     * @param array $where
     * @return mixed  return the number of affected rows when succeed,false when fail
     */
    public function update($data, $where) {
        $this->_medoo = static::getConnection($this->_db);
        return $this->_medoo->update($this->_table, $data, $where);
    }

    /**
     * @param array $where
     * @return mixed  return the number of affected rows when succeed,false when fail
     */
    public function delete($where) {
        $this->_medoo = static::getConnection($this->_db);
        return $this->_medoo->delete($this->_table, $where);
    }

    /**
     * use PDO::query()
     *
     * @param string $sql  @example 'select * from activity;'
     * @return mixed  return object PDOStatement when succeed,false when fail
     */
    public function query($sql) {
        $this->_medoo = static::getConnection($this->_db);
        return $this->_medoo->query($sql);
    }


    public function begin() {
        if (empty($this->_medoo)) {
            $this->_medoo = static::getConnection($this->_db);
        }
        $this->_medoo->pdo->beginTransaction();
    }

    public function commit() {
        if (empty($this->_medoo)) {
            $this->_medoo = static::getConnection($this->_db);
        }
        if ( ! empty($this->_medoo) && $this->_medoo->pdo->inTransaction()) {
            $this->_medoo->pdo->commit();
        }
    }

    public function rollback() {
        if (empty($this->_medoo)) {
            $this->_medoo = static::getConnection($this->_db);
        }
        if ( ! empty($this->_medoo) && $this->_medoo->pdo->inTransaction()) {
            $this->_medoo->pdo->rollback();
        }
    }

    public function getErrors(){
        return $this->_medoo->error();
    }
    public function count($where){
        $this->_medoo = static::getConnection($this->_db);
        return $this->_medoo->count($this->_table,$where);
    }


    public function getPrimaryKeyField(){
        return $this->_primary_key;
    }

    public function getById($id) {
        $where[$this->_primary_key] = $id;
        return $this->fetchRow($where);
    }
}
