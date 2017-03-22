<?php
namespace DBTableUsage\Events;


interface Event {

    /**
     * @param mixed $logfile
     */
    public function setLogfile($logfile);

    /**
     * @param mixed $logpos
     */
    public function setLogpos($logpos);

    /**
     * @return mixed
     */
    public function getLogfile();

    /**
     * @return mixed
     */
    public function getLogpos();
}
