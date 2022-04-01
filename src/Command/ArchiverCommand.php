<?php

namespace App\Command;

use App\Entity\Archiver;
use App\Repository\ArchiverRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class ArchiverCommand extends Command
{
    protected string $archiverType;

    protected InputInterface $input;

    protected OutputInterface $output;

    private ArchiverRepository $archiverRepository;

    protected ?Archiver $archiver;

    public function __construct(ArchiverRepository $archiverRepository)
    {
        parent::__construct();
        $this->archiverRepository = $archiverRepository;
    }

    abstract protected function doExecute(): int;

    protected function doConfigure()
    {
    }

    protected function configure()
    {
        $this
            ->addArgument('archiver', InputArgument::REQUIRED, 'Archiver to run (name or id)')
            ->addOption('last-run-at', null, InputOption::VALUE_REQUIRED, 'Use this time as value of Archiver.lastRunAt');
        $this->doConfigure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        $archiverId = $input->getArgument('archiver');
        if (empty($this->archiverRepository)) {
            throw new RuntimeException('Archiver repository not set in '.static::class);
        }

        $this->archiver = $this->archiverRepository->findOneByNameOrId($archiverId);
        if (null === $this->archiver) {
            throw new RuntimeException(sprintf('Invalid archiver: %s', $archiverId));
        }

        if ($this->archiver->getType() !== $this->archiverType) {
            throw new RuntimeException(sprintf('Invalid archiver type: %s (%s expected)', $this->archiver->getType(), $this->archiverType));
        }

        if ($lastRunAt = $input->getOption('last-run-at')) {
            try {
                $this->archiver->setLastRunAt(new \DateTime($lastRunAt));
            } catch (\Exception $ex) {
                throw new RuntimeException('Invalid last-run-at value: '.$lastRunAt);
            }
        }

        return $this->doExecute();
    }

    protected function writeTable($data, $vertical = false)
    {
        $isAssoc = function (array $arr) {
            if ([] === $arr) {
                return false;
            }

            return array_keys($arr) !== range(0, \count($arr) - 1);
        };

        if (!\is_array($data) || $isAssoc($data)) {
            $data = [$data];
        }

        $table = new Table($this->output);
        $rowCount = 0;

        foreach ($data as $item) {
            // Clean up item.
            $item = array_map(function ($value) {
                return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }, json_decode(json_encode($item ?? []), true));

            if ($vertical) {
                if ($rowCount > 0) {
                    $table->addRow(new TableSeparator());
                }
                foreach ($item as $key => $value) {
                    $table->addRow([$key, $value]);
                }
            } else {
                if (0 === $rowCount) {
                    $table->setHeaders(array_keys($item));
                }
                $table->addRow($item);
            }
            ++$rowCount;
        }

        $table->render();
        $this->output->writeln('#rows: '.$rowCount);
    }
}
