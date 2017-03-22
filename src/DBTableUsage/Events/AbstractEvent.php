<?php
namespace DBTableUsage\Events;


abstract class AbstractEvent implements Event {
    protected $logfile;
    protected $logpos;

    /**
     * @param mixed $logfile
     */
    public function setLogfile($logfile) {
        $this->logfile = $logfile;
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
    public function getLogfile() {
        return $this->logfile;
    }

    /**
     * @return mixed
     */
    public function getLogpos() {
        return $this->logpos;
    }
}
