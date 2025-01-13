<?php

namespace App\Command\Pdf;

use App\Command\ArchiverCommand;
use App\Entity\Archiver;
use App\Pdf\PdfHelper;
use App\Repository\ArchiverRepository;
use Symfony\Component\Console\Logger\ConsoleLogger;

class CronCommand extends ArchiverCommand
{
    protected static $defaultName = 'app:pdf:cron';
    protected static string $archiverType = Archiver::TYPE_PDF_COMBINE;

    public function __construct(private readonly PdfHelper $helper, ArchiverRepository $archiverRepository)
    {
        parent::__construct($archiverRepository);
    }

    protected function doExecute(): int
    {
        $this->helper->setLogger(new ConsoleLogger($this->output));
        $this->helper->setArchiver($this->archiver);
        $this->helper->process();

        return self::SUCCESS;
    }
}
