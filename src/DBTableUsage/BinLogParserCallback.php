<?php
namespace DBTableUsage;


use DBTableUsage\Events\Event;

interface BinLogParserCallback {
    public function processEvent(Event $event);
}
