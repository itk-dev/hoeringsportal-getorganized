<?php

namespace App\Command\Pdf;

use App\Command\ArchiverCommand;
use App\Entity\Archiver;
use App\Pdf\PdfHelper;
use App\Repository\ArchiverRepository;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Logger\ConsoleLogger;

class CombineCommand extends ArchiverCommand
{
    protected static $defaultName = 'app:pdf:combine';
    protected static string $archiverType = Archiver::TYPE_PDF_COMBINE;

    private $helper;

    private const ACTIONS = [
        'get-data',
        'combine',
        'share',
    ];

    public function __construct(PdfHelper $pdfHelper, ArchiverRepository $archiverRepository)
    {
        parent::__construct($archiverRepository);
        $this->helper = $pdfHelper;
    }

    protected function doConfigure()
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, sprintf('One of %s', implode(', ', self::ACTIONS)))
            ->addArgument('hearing', InputArgument::REQUIRED);
    }

    protected function doExecute(): int
    {
        $this->helper->setLogger(new ConsoleLogger($this->output));

        $action = $this->input->getArgument('action');
        $hearing = $this->input->getArgument('hearing');
        $method = $this->getCommandName($action);

        $this->helper->setArchiver($this->archiver);
        if (!$this->archiver->isEnabled()) {
            throw new RuntimeException(sprintf('Archiver %s is not enabled', $this->archiver->getId()));
        }

        if (!method_exists($this->helper, $method)) {
            throw new InvalidArgumentException(sprintf('Invalid action: %s', $action));
        }

        $result = \call_user_func_array([$this->helper, $method], [$hearing]);

        if ($this->output->isDebug()) {
            $this->output->writeln(json_encode([$action => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return self::SUCCESS;
    }

    private function getCommandName(string $name)
    {
        return lcfirst(str_replace('-', '', ucwords($name, '-')));
    }
}
