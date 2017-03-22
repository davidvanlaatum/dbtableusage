<?php
namespace DBTableUsage\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="`table`")
 */
class Table {
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
     * @ORM\ManyToOne(targetEntity="DBTableUsage\Entity\Database")
     */
    protected $database;

    /**
     * @ORM\Column(type="datetime",nullable=true)
     */
    protected $lastUsed;

    /**
     * @ORM\OneToMany(targetEntity="DBTableUsage\Entity\Column",mappedBy="table",cascade={"ALL"})
     * @var Column[]
     */
    protected $columns = [];

    /**
     * @param mixed $name
     */
    public function setName($name) {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @param mixed $database
     */
    public function setDatabase($database) {
        $this->database = $database;
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

    /** @return Column */
    public function getColumn($name) {
        $rt = null;
        foreach ($this->columns as $column) {
            if ($column->getName() == $name) {
                $rt = $column;
                break;
            }
        }
        if ($rt == null) {
            $rt = new Column();
            $rt->setName($name);
            $rt->setTable($this);
            $this->columns[] = $rt;
        }
        return $rt;
    }
}
