<?php
namespace DBTableUsage;

use DBTableUsage\Entity\Host;
use DBTableUsage\Events\DataModificationEvent;
use DBTableUsage\Events\Event;
use DBTableUsage\Events\Set;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class Processor implements BinLogParserCallback {

    protected $em;
    protected $host;
    protected $username;
    protected $password;
    /** @var \PDO */
    protected $db;
    /** @var Host */
    protected $hostObject;
    /** @var BinLogParser */
    protected $parser;
    protected $logs;
    /** @var LoggerInterface */
    protected $log;
    protected $logPos;
    protected $events = 0;
    protected $now;

    public function __construct(EntityManagerInterface $em, BinLogParser $parser) {
        $this->em = $em;
        $this->parser = $parser;
    }

    /**
     * @param LoggerInterface $log
     */
    public function setLog(LoggerInterface $log) {
        $this->log = $log;
    }

    /**
     * @param mixed $host
     */
    public function setHost($host) {
        $this->host = $host;
    }

    /**
     * @param mixed $username
     */
    public function setUsername($username) {
        $this->username = $username;
    }

    /**
     * @param mixed $password
     */
    public function setPassword($password) {
        $this->password = $password;
    }

    public function process() {
        $this->db = new \PDO('mysql:host=' . $this->host, $this->username, $this->password);
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->loadHost();
        $this->loadColumns();
        $this->determineLogs();
        foreach ($this->logs as $log) {
            $this->log->notice(sprintf('Processing %s', $log));
            $this->parser->connect($this->host, $this->username, $this->password, $log, $this->logPos);
            try {
                $this->parser->process($this);
            } finally {
                $this->parser->disconnect();
            }
            $this->logPos = null;
        }
    }

    protected function loadHost() {
        $this->hostObject = $this->em->getRepository(Host::class)->findOneBy(['name' => $this->host]);
        if (!$this->hostObject) {
            $this->hostObject = new Host();
            $this->hostObject->setName($this->host);
            $this->em->persist($this->hostObject);
            $this->em->flush();
        }
    }

    protected function loadColumns() {
        $res = $this->db->prepare("SELECT table_schema,table_name,column_name,ordinal_position FROM information_schema.columns WHERE table_schema NOT IN ('information_schema','mysql')");
        $res->execute();

        $this->em->transactional(function () use ($res) {
            $this->hostObject = $this->em->merge($this->hostObject);
            foreach ($res->fetchAll() as $row) {
                $db = $this->hostObject->getDatabase($row['table_schema']);
                $table = $db->getTable($row['table_name']);
                $column = $table->getColumn($row['column_name']);
                $column->setOrdinalPosition($row['ordinal_position']);
            }
            $this->em->persist($this->hostObject);
        });
    }

    private function determineLogs() {
        $res = $this->db->prepare("SHOW MASTER LOGS");
        $res->execute();
        $this->logs = $res->fetchAll(\PDO::FETCH_COLUMN);
        $this->log->notice(sprintf('Found %d logs', count($this->logs)));
        if (in_array($this->hostObject->getLogfile(), $this->logs)) {
            $this->log->notice(sprintf('Skipping ahead to %s', $this->hostObject->getLogfile()));
            $this->logs = array_splice($this->logs, array_search($this->hostObject->getLogfile(), $this->logs));
            $this->logPos = $this->hostObject->getLogpos();
        }
    }

    public function processEvent(Event $event) {
        $this->events++;
        if ($event instanceof DataModificationEvent) {
            $db = $this->hostObject->getDatabase($event->getDB());
            $table = $db->getTable($event->getTable());
            $table->setLastUsed($this->now);
        } else if ($event instanceof Set && $event->getName() == "TIMESTAMP") {
            $this->now = new \DateTime();
            $this->now->setTimestamp($event->getValue());
        }

        $this->hostObject->setLogfile($event->getLogfile());
        $this->hostObject->setLogpos($event->getLogpos());
        if ($this->events % 1000 == 0) {
            $this->log->notice('Commit to db', ['events' => $this->events, 'log' => $event->getLogfile(), 'now' => $this->now, 'logPos' => $event->getLogpos()]);
            $this->em->transactional(function () {
                $this->hostObject = $this->em->merge($this->hostObject);
                $this->em->persist($this->hostObject);
            });
        }
    }
}
