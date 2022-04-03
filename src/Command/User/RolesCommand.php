<?php

namespace App\Command\User;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RolesCommand extends Command
{
    protected static $defaultName = 'app:user:roles';
    protected static $defaultDescription = 'Edit user roles';

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email')
            ->addOption('add', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Add role')
            ->addOption('remove', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Remove role')
            ->addOption('list', null, InputOption::VALUE_NONE, 'List user roles')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email');

        /** @var ?User $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if (null === $user) {
            throw new InvalidArgumentException(sprintf('User %s does not exist', $email));
        }

        if ($input->getOption('list')) {
            $this->listRoles($io, $user, sprintf('Roles for user %s:', $user->getEmail()));

            return Command::SUCCESS;
        }

        $roles = $user->getRoles();
        $add = $input->getOption('add');
        $roles = array_merge($add);

        $remove = $input->getOption('remove');
        $roles = array_diff($roles, $remove);

        $roles = array_unique($roles);
        $user->setRoles($roles);

        $this->entityManager->flush();

        $this->listRoles($io, $user, sprintf('Roles for user %s updated: ', $user->getEmail()));

        return Command::SUCCESS;
    }

    private function listRoles(SymfonyStyle $io, User $user, string $header = null)
    {
        if (null !== $header) {
            $io->writeln($header);
            $io->newLine();
        }

        $roles = $user->getRoles();
        foreach ($roles as $role) {
            $io->writeln(sprintf('* %s', $role));
        }
        $io->newLine();
    }
}
