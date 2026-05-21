<?php

namespace App\Command;

use App\Service\BookingNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notifications:pickup-reminders',
    description: 'Create in-app pickup reminders for confirmed bookings in the next N hours',
)]
final class SendPickupReminderNotificationsCommand extends Command
{
    public function __construct(
        private readonly BookingNotificationService $bookingNotifications,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('hours', null, InputOption::VALUE_REQUIRED, 'Hours ahead to look for pickups', '24');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $hours = max(1, (int) $input->getOption('hours'));

        $count = $this->bookingNotifications->sendPickupReminders($this->em, $hours);
        $this->em->flush();

        $io->success(sprintf('Created %d pickup reminder notification(s).', $count));

        return Command::SUCCESS;
    }
}
