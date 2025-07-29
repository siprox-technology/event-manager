<?php

namespace App\Command;

use App\Entity\User;
use App\Service\UserEmailService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-email',
    description: 'Send a test email to verify email configuration'
)]
class TestEmailCommand extends Command
{
    public function __construct(
        private UserEmailService $emailService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email address to send test email to')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Email type (welcome|registration)', 'registration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $email = $input->getOption('email');
        $type = $input->getOption('type');

        if (!$email) {
            $email = $io->ask('Enter email address to send test email to');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Invalid email address provided.');
            return Command::FAILURE;
        }

        // Create a test user
        $testUser = new User();
        $testUser->setEmail($email);
        $testUser->setFirstName('Test');
        $testUser->setLastName('User');
        $testUser->setCreatedAt(new \DateTimeImmutable());

        try {
            $io->info("Sending {$type} email to {$email}...");

            if ($type === 'welcome') {
                $this->emailService->sendWelcomeEmail($testUser);
            } else {
                $this->emailService->sendRegistrationConfirmation($testUser);
            }

            $io->success('Test email sent successfully!');
            $io->note('Check Mailpit at https://event-manager.ddev.site:8026 to view the email.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to send email: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
