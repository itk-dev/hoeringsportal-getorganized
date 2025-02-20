<?php

namespace App\Command\GetOrganized;

use App\Command\ArchiverCommand;
use App\Entity\Archiver;
use App\GetOrganized\GetOrganizedService;
use App\Repository\ArchiverRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:getorganized:documents',
    description: 'app:getorganized:documents',
)]
class DocumentsCommand extends ArchiverCommand
{
    protected static string $archiverType = Archiver::TYPE_SHAREFILE2GETORGANIZED;

    public function __construct(ArchiverRepository $archiverRepository, private readonly GetOrganizedService $getOrganized)
    {
        parent::__construct($archiverRepository);
    }

    protected function doConfigure(): void
    {
        $this
           ->addArgument('action', InputArgument::REQUIRED, 'The action')
           ->addOption('case-id', null, InputOption::VALUE_REQUIRED, 'The case id')
           ->addOption('field', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Field to include in list');
    }

    protected function doExecute(): int
    {
        $action = $this->input->getArgument('action');
        $this->getOrganized->setArchiver($this->archiver);
        $logger = new ConsoleLogger($this->output);

        return $this->{$action}();
    }

    private function list(): int
    {
        $io = new SymfonyStyle($this->input, $this->output);

        $caseId = $this->input->getOption('case-id');
        $fields = $this->input->getOption('field');
        if (null === $caseId) {
            throw new InvalidOptionException('Mission options --case-id');
        }
        $documents = $this->getOrganized->getDocumentsByCaseId($caseId);
        foreach ($documents as $document) {
            $list = [];
            foreach ($document as $name => $value) {
                if (empty($fields) || in_array($name, $fields)) {
                    $list[] = [$name => is_scalar($value) ? $value : json_encode($value)];
                }
            }
            $io->definitionList(...$list);
            //            call_user_func_array([$io, 'definitionList'], $list);
        }
        //        $this->writeTable($documents, true);

        return self::SUCCESS;
    }
}
