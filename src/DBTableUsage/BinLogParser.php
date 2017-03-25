<?php
namespace DBTableUsage;

use DBTableUsage\Events\Event;
use DBTableUsage\Events\Insert;
use DBTableUsage\Events\Set;
use Exception;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use PhpMyAdmin\SqlParser\Statements\ReplaceStatement;
use PhpMyAdmin\SqlParser\Statements\SetStatement;
use PhpMyAdmin\SqlParser\Statements\TransactionStatement;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;

class BinLogParser {
    /** @var Process */
    protected $process;
    protected $binpath;
    /** @var LoggerInterface */
    protected $log;
    protected $logfile;

    /**
     * BinLogParser constructor.
     * @param $binpath
     */
    public function __construct($binpath, LoggerInterface $logger) {
        if (!$binpath) {
            $binpath = 'mysqlbinlog';
        }
        $this->binpath = $binpath;
        $this->log = $logger;
    }


    public function connect($host, $username, $password, $logfile, $logpos) {
        $builder = ProcessBuilder::create([$this->binpath])->add('-R')
            ->add('-h' . $host)
            ->add('-u' . $username)
            ->add('-p' . $password)
            ->add('-v')
            ->add($logfile)
            ->setTimeout(null);

        if ($logpos) {
            $builder->add('--start-position=' . $logpos);
        }

        $this->process = $builder->getProcess();
        $this->log->info('Command line is', [$this->process->getCommandLine()]);
        $this->logfile = $logfile;
        $this->process->start();
    }

    public function process(BinLogParserCallback $callback) {
        $data = null;
        foreach ($this->process->getIterator() as $type => $row) {
            if ($type == 'err') {
                $this->log->warning($row);
            } else {
                $data .= $row;
                while (preg_match('/.*end_log_pos (\d+).*\n/m', $data, $matches, PREG_OFFSET_CAPTURE)) {
                    $this->processEvent(substr($data, 0, $matches[0][1]), $matches[1][0], $callback);
                    $data = substr($data, $matches[0][1] + strlen($matches[0][0]));
                }
            }
        }
    }

    public function disconnect() {
        $this->log->notice('Shutting down', [$this->process->isRunning()]);
        if ($this->process->isRunning()) {
            $this->process->stop();
        }
        if (!in_array($this->process->getExitCode(), [0, 143])) {
            throw new \Exception('mysqlbinlog errored ' . $this->process->getExitCodeText());
        } else {
            $this->log->notice('Exited', [$this->process->getExitCode(), $this->process->getExitCodeText()]);
        }
    }

    protected function processEvent($data, $logPos, BinLogParserCallback $callback) {
        $len = strlen($data);
        if ($len > 1024 * 1024) {
            $this->log->warning('Long event at', [$this->logfile, $logPos, $len]);
            var_dump($data);
        }
        $data = explode("\n", $data);
        $data = array_map(function ($v) {
            if (strncmp($v, "###", 3) == 0) {
                $v = substr($v, 4);
            }
            if (strncmp($v, "#", 1) == 0) {
                $v = null;
            }
            return $v;
        }, $data);
        $data = implode("\n", $data);
        while (($pos = strpos($data, '/*')) !== false) {
            $pos2 = strpos($data, '*/', $pos);
            if ($pos2) {
                $data = substr($data, 0, $pos) . substr($data, $pos2 + 2);
            } else {
                break;
            }
        }
        try {
            /** @var Event[] $events */
            $events = [];
            $parser = new Parser($data);
            foreach ($parser->statements as $statement) {
                try {
                    if (!($statement instanceof TransactionStatement)) {
                        $this->log->debug($statement->build(), ['type' => get_class($statement)]);
                    }
                } catch (Exception $e) {
                    $this->log->error($e->getMessage(), ['type' => get_class($statement)]);
                }
                if ($statement instanceof SetStatement) {
                    foreach ($statement->set as $set) {
                        $events[] = new Set($set);
                    }
                } else if ($statement instanceof InsertStatement) {
                    $events[] = new Insert($statement);
                } else if ($statement instanceof ReplaceStatement) {
                } else if ($statement instanceof DeleteStatement) {
                } else if ($statement instanceof UpdateStatement) {
                } else if ($statement instanceof TransactionStatement) {
                    ;
                } else {
                    $this->log->warning('Unhandled type', ['type' => get_class($statement)]);
                }
            }
            foreach ($events as $event) {
                $event->setLogfile($this->logfile);
                $event->setLogpos($logPos);
                $callback->processEvent($event);
            }
        } catch (Exception $e) {
            $this->log->error(sprintf("Failed to parse event at %s:%d\n", $this->logfile, $logPos) . $data);
        }
        if ($len > 1024 * 1024) {
            $this->log->warning('Long event at done', [$this->logfile, $logPos, $len]);
        }
    }
}
