<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:test:mail',
    description: 'Send email',
)]
class TestMailCommand extends Command
{
    public function __construct(private readonly MailerInterface $mailer)
    {
        parent::__construct(null);
    }

    protected function configure(): void
    {
        $this
            ->addOption('subject', null, InputOption::VALUE_REQUIRED, 'Subject')
            ->addOption('text', null, InputOption::VALUE_REQUIRED, 'Text')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'To')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'From')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $subject = $input->getOption('subject');
        while (empty($subject)) {
            $subject = $io->askQuestion(new Question('Subject'));
        }
        $text = $input->getOption('text');
        while (empty($text)) {
            $text = $io->askQuestion(new Question('Message'));
        }
        $to = $input->getOption('to');
        while (empty($to)) {
            $to = $io->askQuestion(new Question('To'));
        }
        $from = $input->getOption('from');
        while (empty($from)) {
            $from = $io->askQuestion(new Question('From'));
        }

        $email = (new Email())
            ->from($from)
            ->to(...[$to])
            ->subject($subject)
            ->text($text);

        $this->mailer->send($email);

        $io->success(sprintf('Mail sent to %s', $to));

        return Command::SUCCESS;
    }
}
