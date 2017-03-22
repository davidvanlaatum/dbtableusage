<?php
namespace DBTableUsage\Entity;


use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="`column`")
 */
class Column {
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=100)
     */
    protected $name;

    /**
     * @ORM\ManyToOne(targetEntity="DBTableUsage\Entity\Table")
     */
    protected $table;

    /**
     * @ORM\Column(type="datetime",nullable=true)
     */
    protected $lastUsed;

    /**
     * @ORM\Column(type="integer")
     */
    protected $ordinalPosition;

    /**
     * @return mixed
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name) {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getTable() {
        return $this->table;
    }

    /**
     * @param mixed $table
     */
    public function setTable($table) {
        $this->table = $table;
    }

    /**
     * @return mixed
     */
    public function getOrdinalPosition() {
        return $this->ordinalPosition;
    }

    /**
     * @param mixed $ordinalPosition
     */
    public function setOrdinalPosition($ordinalPosition) {
        $this->ordinalPosition = $ordinalPosition;
    }

    /**
     * @return mixed
     */
    public function getLastUsed() {
        return $this->lastUsed;
    }

    /**
     * @param mixed $lastUsed
     */
    public function setLastUsed($lastUsed) {
        $this->lastUsed = $lastUsed;
    }
}
