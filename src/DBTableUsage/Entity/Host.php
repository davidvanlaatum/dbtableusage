<?php
namespace DBTableUsage\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="host")
 */
class Host {
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
     * @ORM\OneToMany(targetEntity="DBTableUsage\Entity\Database",mappedBy="host",cascade={"ALL"})
     * @var Database[]
     */
    protected $databases;


    /**
     * @ORM\Column(type="string", length=100,nullable=true)
     */
    protected $logfile;

    /**
     * @ORM\Column(type="integer",nullable=true)
     */
    protected $logpos;

    /**
     * @param string $name
     */
    public function setName($name) {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @return Database[]
     */
    public function getDatabases() {
        return $this->databases;
    }

    public function addDatabase(Database $db) {
        $this->databases[] = $db;
    }


    /** @return Database */
    public function getDatabase($name) {
        $rt = null;
        foreach ($this->databases as $db) {
            if ($db->getName() == $name) {
                $rt = $db;
                break;
            }
        }
        if ($rt == null) {
            $rt = new Database();
            $rt->setName($name);
            $rt->setHost($this);
            $this->databases[] = $rt;
        }
        return $rt;
    }

    /**
     * @param mixed $logfile
     */
    public function setLogfile($logfile) {
        $this->logfile = $logfile;
    }

    /**
     * @return mixed
     */
    public function getLogfile() {
        return $this->logfile;
    }

    /**
     * @param mixed $logpos
     */
    public function setLogpos($logpos) {
        $this->logpos = $logpos;
    }

    /**
     * @return mixed
     */
    public function getLogpos() {
        return $this->logpos;
    }
}
