<?php
namespace DBTableUsage\Events;


abstract class DataModificationEvent extends AbstractEvent {

    private $table;
    private $db;

    /**
     * @return mixed
     */
    public function getTable() {
        return $this->table;
    }

    /**
     * @return mixed
     */
    public function getDB() {
        return $this->db;
    }

    /**
     * @param mixed $table
     */
    public function setTable($table) {
        $this->table = trim($table, '``');
    }

    /**
     * @param mixed $db
     */
    public function setDB($db) {
        $this->db = trim($db, '``');
    }
}
