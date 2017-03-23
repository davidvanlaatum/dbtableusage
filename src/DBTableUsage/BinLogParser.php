<?php
namespace DBTableUsage;

use DBTableUsage\Events\Event;
use DBTableUsage\Events\Insert;
use DBTableUsage\Events\Set;
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
    }

    protected function processEvent($data, $logpos, BinLogParserCallback $callback) {
        $data = preg_replace(['/^###(.*?)$/m', '/^#.*?$/m', '/\/\*.*?\*\n*\//s', '/BINLOG \'.*?\'\;/s'], ['$1', '', '', ''], $data);
        $data = explode(";", trim($data));
        $data = array_filter($data, function ($v) {
            return !empty(trim($v));
        });
        $data = array_map(function ($v) {
            return trim($v);
        }, $data);
        /** @var Event[] $events */
        $events = [];
        foreach ($data as $d) {
            if (strncmp("INSERT", $d, 6) == 0) {
                $events[] = $this->doInsert($d);
            } else if (strncmp("SET ", $d, 4) == 0) {
                $events[] = $this->doSet($d);
            } else if (in_array($d, ["COMMIT", 'BEGIN', 'DELIMITER'])) {
                ;
            } else {
//                $this->log->warning('Unknown command', [$logpos, $d]);
            }
        }
        $events = array_filter($events, function ($v) {
            return $v != null;
        });
        if (!empty($events)) {
            $this->log->debug('Events', $events);
        }
        foreach ($events as $event) {
            $event->setLogfile($this->logfile);
            $event->setLogpos($logpos);
            $callback->processEvent($event);
        }
    }

    private function doInsert($data) {
        return new Insert($data);
    }

    private function doSet($data) {
        return new Set($data);
    }
}
