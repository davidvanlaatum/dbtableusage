<?php
namespace DBTableUsage\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="db")
 */
class Database {
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
     * @ORM\ManyToOne(targetEntity="DBTableUsage\Entity\Host")
     */
    protected $host;

    /**
     * @ORM\OneToMany(targetEntity="DBTableUsage\Entity\Table",mappedBy="database",cascade={"ALL"})
     * @var Table[]
     */
    protected $tables = [];

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name) {
        $this->name = $name;
    }

    /**
     * @param mixed $host
     */
    public function setHost($host) {
        $this->host = $host;
    }

    /**
     * @return mixed
     */
    public function getHost() {
        return $this->host;
    }

    /** @return Table */
    public function getTable($name) {
        $rt = null;
        foreach ($this->tables as $table) {
            if ($table->getName() == $name) {
                $rt = $table;
                break;
            }
        }
        if ($rt == null) {
            $rt = new Table();
            $rt->setName($name);
            $rt->setDatabase($this);
            $this->tables[] = $rt;
        }
        return $rt;
    }
}
