<?php

namespace DBTableUsage\Command;

use DBTableUsage\Processor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessCommand extends Command {
    /** @var Processor */
    protected $processor;

    public function __construct() {
        parent::__construct('process');
    }

    protected function getDefaultUser() {
        if (function_exists('posix_getpwuid')) {
            $processUser = posix_getpwuid(posix_geteuid());
            return $processUser['name'];
        } else {
            return getenv('USERNAME') ?: getenv('USER');
        }
    }

    protected function configure() {
        $this->addOption("user", null, InputOption::VALUE_REQUIRED, null, $this->getDefaultUser());
        $this->addOption("password", null, InputOption::VALUE_REQUIRED);
        $this->addOption("host", null, InputOption::VALUE_REQUIRED, null, 'localhost');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        if ($input->getOption("user")) {
            $this->processor->setUsername($input->getOption("user"));
        }
        if ($input->getOption("password")) {
            $this->processor->setPassword($input->getOption("password"));
        }
        if ($input->getOption("host")) {
            $this->processor->setHost($input->getOption("host"));
        }
        $this->processor->process();
    }

    public function setProcessor(Processor $processor) {
        $this->processor = $processor;
    }
}
