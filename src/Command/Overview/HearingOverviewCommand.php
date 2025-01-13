<?php

namespace App\Command\Overview;

use App\Command\ArchiverCommand;
use App\Entity\Archiver;
use App\Overview\HearingOverviewHelper;
use App\Repository\ArchiverRepository;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Logger\ConsoleLogger;

class HearingOverviewCommand extends ArchiverCommand
{
    protected static $defaultName = 'app:overview:hearing';
    protected static string $archiverType = Archiver::TYPE_HEARING_OVERVIEW;

    public function __construct(private readonly HearingOverviewHelper $helper, ArchiverRepository $archiverRepository)
    {
        parent::__construct($archiverRepository);
    }

    public function doConfigure()
    {
        $this->addArgument('hearing-id', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'The hearing id');
    }

    protected function doExecute(): int
    {
        $this->helper->setArchiver($this->archiver);
        $this->helper->setLogger(new ConsoleLogger($this->output));

        // Convert hearing ids to integers.
        $hearingIds = array_map(static function ($value) {
            if (preg_match('/H?(?<id>[0-9]+)$/', $value, $matches)) {
                return intval($matches['id']);
            }
            throw new InvalidArgumentException(sprintf('Invalid hearing id: %s', $value));
        }, $this->input->getArgument('hearing-id'));

        $this->helper->process($hearingIds);

        return self::SUCCESS;
    }
}
