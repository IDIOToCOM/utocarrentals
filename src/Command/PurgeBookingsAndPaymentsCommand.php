<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:purge-bookings-payments',
    description: 'Delete all bookings, payments, and booking-linked notifications (irreversible)',
)]
final class PurgeBookingsAndPaymentsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', null, InputOption::VALUE_NONE, 'Required to run the purge')
            ->addOption(
                'keep-notifications',
                null,
                InputOption::VALUE_NONE,
                'Keep app_notification rows (default: delete booking-linked notifications too)',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$input->getOption('force')) {
            $io->error('This permanently deletes ALL bookings and payments. Re-run with --force to confirm.');

            return Command::FAILURE;
        }

        $connection = $this->entityManager->getConnection();

        $paymentCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM payment');
        $bookingCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM booking');
        $orphanPayments = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM payment WHERE booking_id IS NULL',
        );

        $io->warning([
            'About to delete:',
            sprintf('- %d payment(s) (%d without a booking)', $paymentCount, $orphanPayments),
            sprintf('- %d booking(s)', $bookingCount),
        ]);

        if (!$input->getOption('no-interaction') && !$io->confirm('Continue?', false)) {
            $io->note('Cancelled.');

            return Command::SUCCESS;
        }

        $connection->beginTransaction();

        try {
            $deletedPayments = $connection->executeStatement('DELETE FROM payment');
            $deletedNotifications = 0;
            if (!$input->getOption('keep-notifications')) {
                $deletedNotifications = $connection->executeStatement(
                    'DELETE FROM app_notification WHERE booking_id IS NOT NULL',
                );
            }
            $deletedBookings = $connection->executeStatement('DELETE FROM booking');

            $connection->commit();

            $io->success([
                sprintf('Deleted %d payment row(s).', $deletedPayments),
                sprintf('Deleted %d booking row(s).', $deletedBookings),
                sprintf('Deleted %d booking-linked notification(s).', $deletedNotifications),
            ]);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $connection->rollBack();
            $io->error('Purge failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
