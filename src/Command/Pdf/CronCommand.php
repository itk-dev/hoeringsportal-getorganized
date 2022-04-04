<?php

/*
 * This file is part of hoeringsportal-sync-files.
 *
 * (c) 2018–2019 ITK Development
 *
 * This source file is subject to the MIT license.
 */

namespace App\Command\Pdf;

use App\Command\ArchiverCommand;
use App\Entity\Archiver;
use App\Pdf\PdfHelper;
use App\Repository\ArchiverRepository;
use Symfony\Component\Console\Logger\ConsoleLogger;

class CronCommand extends ArchiverCommand
{
    protected static $defaultName = 'app:pdf:cron';
    protected string $archiverType = Archiver::TYPE_PDF_COMBINE;

    private PdfHelper $helper;

    public function __construct(PdfHelper $pdfHelper, ArchiverRepository $archiverRepository)
    {
        parent::__construct($archiverRepository);
        $this->helper = $pdfHelper;
    }

    protected function doExecute(): int
    {
        $this->helper->setLogger(new ConsoleLogger($this->output));
        $this->helper->setArchiver($this->archiver);
        $this->helper->process();

        return self::SUCCESS;
    }
}
