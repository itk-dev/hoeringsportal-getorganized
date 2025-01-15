<?php

namespace App\Command\ShareFile2GetOrganized;

use App\Command\ArchiverCommand;
use App\Entity\Archiver;
use App\GetOrganized\ArchiveHelper;
use App\Repository\ArchiverRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;

#[AsCommand(
    name: 'app:sharefile2getorganized:archive',
    description: 'Archive files from ShareFile in GetOrganized',
)]
class ArchiveCommand extends ArchiverCommand
{
    protected static string $archiverType = Archiver::TYPE_SHAREFILE2GETORGANIZED;

    public function __construct(ArchiverRepository $archiverRepository, private readonly ArchiveHelper $helper)
    {
        parent::__construct($archiverRepository);
    }

    protected function doConfigure(): void
    {
        $this
            ->addOption('hearing-item-id', null, InputOption::VALUE_REQUIRED, 'Hearing item id to archive')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force archiving');
    }

    protected function doExecute(): int
    {
        $hearingItemId = $this->input->getOption('hearing-item-id');
        $logger = new ConsoleLogger($this->output);
        $this->helper->setLogger($logger);
        $this->helper->archive($this->archiver, $hearingItemId, [
            'force' => $this->input->getOption('force'),
        ]);

        return self::SUCCESS;
    }
}
