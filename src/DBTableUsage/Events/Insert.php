<?php
namespace DBTableUsage\Events;

use PhpMyAdmin\SqlParser\Statements\InsertStatement;

class Insert extends DataModificationEvent {

    protected $fields;

    /**
     * Insert constructor.
     */
    public function __construct(InsertStatement $statement) {
        $this->setDB($statement->into->dest->database);
        $this->setTable($statement->into->dest->table);
    }

    function __toString() {
        return sprintf("%s.%s (%s)", $this->getDB(), $this->getTable(), $this->fields);
    }
}
