<?php
namespace DBTableUsage;

use DBTableUsage\Entity\Host;
use DBTableUsage\Events\DataModificationEvent;
use DBTableUsage\Events\Event;
use DBTableUsage\Events\Set;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

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
    /** @var ProgressBar */
    protected $progressBar;
    protected $lastCommitPos;
    protected $lastCommitTime;
    /** @var \DateTime */
    protected $lastCommitTimestamp;
    protected $speedBytes;
    protected $speedTime;

    public function __construct(EntityManagerInterface $em, BinLogParser $parser) {
        $this->em = $em;
        $this->parser = $parser;
        $this->now = new \DateTime();
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

    public function process(OutputInterface $output) {
        $this->db = new \PDO('mysql:host=' . $this->host, $this->username, $this->password);
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->loadHost();
//        $this->loadColumns();
        $this->determineLogs();
        foreach ($this->logs as $log => $size) {
            $this->log->notice(sprintf('Processing %s', $log));
            $this->progressBar = new ProgressBar($output, $size);
            $this->progressBar->setFormat('[%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s% %current% %events:6s% %logfile% %time% %speedbytes% %speedtime%');
            $this->progressBar->setRedrawFrequency(100000);
            $this->parser->connect($this->host, $this->username, $this->password, $log, $this->logPos);
            try {
                $this->parser->process($this);
            } finally {
                $this->parser->disconnect();
            }
            $this->logPos = null;
            $this->commit();
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
        $this->logs = $res->fetchAll(\PDO::FETCH_KEY_PAIR);
        $index = array_search($this->hostObject->getLogfile(), array_keys($this->logs));
        $this->log->notice(sprintf('Found %d logs', count($this->logs)), ['file' => $this->hostObject->getLogfile()]);
        if ($index !== false) {
            $this->log->notice(sprintf('Skipping ahead to %s', $this->hostObject->getLogfile()));
            $this->logs = array_splice($this->logs, $index);
            $this->logPos = $this->hostObject->getLogpos();
        } else if ($this->hostObject->getLogfile()) {
            $this->log->warning('Have log file but not found so starting from scratch', [array_keys($this->logs)]);
        }
    }

    public function processEvent(Event $event) {
        $this->events++;
        if ($event instanceof DataModificationEvent) {
            $db = $this->hostObject->getDatabase($event->getDB());
            $table = $db->getTable($event->getTable());
            $table->setLastUsed($this->now);
        } else if ($event instanceof Set && $event->getName() == "TIMESTAMP") {
            $this->now->setTimestamp($event->getInt());
        }

        $this->hostObject->setLogfile($event->getLogfile());
        $this->hostObject->setLogpos($event->getLogpos());
        if ($this->events % 10000 == 0) {
            $this->commit();
        }
        $this->progressBar->setMessage($event->getLogfile(), 'logfile');
        $this->progressBar->setMessage($this->now->format(DATE_W3C), 'time');
        $this->progressBar->setMessage($this->events, 'events');
        $this->progressBar->setMessage(sprintf("%.2fKb/s", $this->speedBytes), 'speedbytes');
        $this->progressBar->setMessage(sprintf("%.2fs/s", $this->speedTime), 'speedtime');
        $this->progressBar->setProgress($event->getLogpos());
    }

    protected function commit() {
        $this->log->debug('Committing', [$this->hostObject->getLogfile(), $this->hostObject->getLogpos()]);
        $this->em->transactional(function () {
            $this->hostObject = $this->em->merge($this->hostObject);
            $this->em->persist($this->hostObject);
        });
        $now = gettimeofday(true);
        $timediff = $now - $this->lastCommitTime;

        if ($this->lastCommitPos < $this->hostObject->getLogpos()) {
            $this->speedBytes = ($this->hostObject->getLogpos() - $this->lastCommitPos) / $timediff / 1024.0;
        }
        if ($this->lastCommitTimestamp) {
            $this->speedTime = ($this->now->getTimestamp() - $this->lastCommitTimestamp->getTimestamp()) / $timediff;
        }

        $this->lastCommitPos = $this->hostObject->getLogpos();
        $this->lastCommitTime = $now;
        $this->lastCommitTimestamp = clone $this->now;
        gc_collect_cycles();
    }
}
