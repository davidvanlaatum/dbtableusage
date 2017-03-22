<?php
namespace DBTableUsage\Events;

class Insert extends DataModificationEvent {

    protected $fields;

    /**
     * Insert constructor.
     */
    public function __construct($statement) {
        preg_match('/INSERT INTO (\S+)\.(\S+)\s+SET\s+(.*)/s', $statement, $matches);
        $this->setDB($matches[1]);
        $this->setTable($matches[2]);
        $this->fields = $matches[3];
    }

    function __toString() {
        return sprintf("%s.%s (%s)", $this->getDB(), $this->getTable(), $this->fields);
    }
}
