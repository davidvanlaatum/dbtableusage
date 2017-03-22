<?php
namespace DBTableUsage\Events;

class Set extends AbstractEvent {

    protected $name;
    protected $value;

    public function __construct($data) {
        preg_match('/SET (\S+)=(.*)/', $data, $matches);
        $this->name = $matches[1];
        $this->value = $matches[2];
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
}
