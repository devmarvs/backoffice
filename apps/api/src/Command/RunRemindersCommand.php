<?php

declare(strict_types=1);

namespace App\Command;

use App\Application\Reminders\ReminderService;
use App\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:reminders:run')]
final class RunRemindersCommand extends Command
{
    public function __construct(
        private ReminderService $reminders,
        private UserRepositoryInterface $users
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = $this->users->findAll();
        $total = 0;

        foreach ($rows as $user) {
            $total += $this->reminders->runForUser((int) $user['id']);
        }

        $output->writeln(sprintf('Created %d reminder follow-ups.', $total));

        return Command::SUCCESS;
    }
}
