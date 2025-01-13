<?php

namespace App\Command\User;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:create',
    description: 'Create user',
)]
class CreateCommand extends Command
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher, private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'User email')
            ->addOption('role', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'User role(s)')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'User password')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $email = $input->getArgument('email');
        $question = new Question('Email: ', null);
        while (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = $helper->ask($input, $output, $question);
        }

        /** @var ?User $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if (null !== $user) {
            throw new InvalidArgumentException(sprintf('User %s already exists', $email));
        }

        $password = $input->getOption('password');
        $question = new Question('Password: ', null);
        $question->setHidden(true);
        $question->setHiddenFallback(false);

        while (empty($password)) {
            $password = $helper->ask($input, $output, $question);
        }

        $roles = $input->getOption('role');
        $user = (new User())
              ->setEmail($email)
              ->setRoles($roles);
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('User %s (%s) created', $user->getEmail(), $user->getId()));

        return Command::SUCCESS;
    }
}
