<?php

namespace App\Command\Archiver;

use App\Entity\Archiver;
use App\Repository\ArchiverRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;

#[AsCommand(
    name: 'app:archiver:list',
    description: 'List archivers',
)]
class ListCommand extends Command
{
    public function __construct(private readonly ArchiverRepository $archiverRepository)
    {
        parent::__construct();
    }

    public function configure(): void
    {
        $this->setName('app:archiver:list')
            ->addOption('type', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'The types to list')
            ->addOption('field', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'The fields to list')
            ->addOption('enabled', null, InputOption::VALUE_REQUIRED, 'If not set, all archivers will be listed.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $types = $input->getOption('type');
        $fields = $input->getOption('field');
        $enabled = $input->getOption('enabled');

        if (empty($fields)) {
            $fields = array_map(fn (\ReflectionProperty $property) => $property->name, (new \ReflectionClass(Archiver::class))->getProperties());
        }

        $criteria = [];
        if (!empty($types)) {
            $criteria['type'] = $types;
        }
        if (null !== $enabled) {
            $criteria['enabled'] = \in_array($enabled, ['yes', 1, 'true'], true);
        }

        $archivers = $this->archiverRepository->findBy($criteria);

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        if (1 === \count($fields)) {
            foreach ($archivers as $archiver) {
                foreach ($fields as $field) {
                    $value = $propertyAccessor->getValue($archiver, $field);
                    $output->writeln($value);
                }
            }
        } else {
            $table = new Table($output);
            $table->setHorizontal();
            $first = true;
            foreach ($archivers as $archiver) {
                $values = array_map(fn ($field) => $propertyAccessor->getValue($archiver, $field), $fields);

                if ($first) {
                    $table->setHeaders($fields);
                    $first = false;
                }

                $table->addRow(array_map(fn ($value) => is_scalar($value) ? $value : json_encode($value), $values));
            }
            $table->render();
        }

        return self::SUCCESS;
    }
}
