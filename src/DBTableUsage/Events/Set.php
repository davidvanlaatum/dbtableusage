<?php
namespace DBTableUsage\Events;

use PhpMyAdmin\SqlParser\Components\SetOperation;

class Set extends AbstractEvent {

    protected $name;
    protected $value;

    public function __construct(SetOperation $data) {
        $this->name = $data->column;
        $this->value = $data->value;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getValue() {
        return $this->value;
    }

    public function getInt() {
        return (int)$this->value;
    }
}
