<?php

namespace App\Command\ShareFile2GetOrganized;

use App\Command\ArchiverCommand;
use Symfony\Component\Console\Input\InputOption;

class ArchiveCommand extends ArchiverCommand
{
    protected static $defaultName = 'app:sharefile2getorganized:archive';
    protected static $defaultDescription = 'Archive files from ShareFile in GetOrganized';

    protected function doConfigure()
    {
        $this->addOption('hearing-item-id', null, InputOption::VALUE_REQUIRED, 'Hearing item id to archive');
    }

    protected function doExecute(): int
    {
        $hearingItemId = $this->input->getOption('hearing-item-id');
        $logger = new ConsoleLogger($this->output);
        $this->helper->setLogger($logger);
        $this->helper->archive($this->archiver, $hearingItemId);
    }
}
