<?php

namespace App\Command;

use App\Entity\Login;
use App\Repository\LoginRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Create a login account (for Forge SSH or local setup)',
)]
final class CreateLoginUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoginRepository $loginRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Login username')
            ->addArgument('email', InputArgument::REQUIRED, 'Email address')
            ->addArgument('password', InputArgument::REQUIRED, 'Plain password (will be hashed)')
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'ROLE_USER, ROLE_STAFF, or ROLE_ADMIN', 'ROLE_USER')
            ->addOption('display-name', null, InputOption::VALUE_OPTIONAL, 'Display name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = trim((string) $input->getArgument('username'));
        $email = trim((string) $input->getArgument('email'));
        $password = (string) $input->getArgument('password');
        $role = strtoupper(trim((string) $input->getOption('role')));

        if (!\in_array($role, ['ROLE_USER', 'ROLE_STAFF', 'ROLE_ADMIN'], true)) {
            $io->error('Role must be ROLE_USER, ROLE_STAFF, or ROLE_ADMIN.');

            return Command::FAILURE;
        }

        if ($this->loginRepository->findOneBy(['username' => $username])) {
            $io->error(sprintf('Username "%s" already exists.', $username));

            return Command::FAILURE;
        }

        if ($email !== '' && $this->loginRepository->findOneBy(['email' => $email])) {
            $io->error(sprintf('Email "%s" is already registered.', $email));

            return Command::FAILURE;
        }

        $user = new Login();
        $user->setUsername($username);
        $user->setEmail($email !== '' ? $email : null);
        $user->setRoles([$role]);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setIsVerified(true);
        $user->setIsEnabled(true);

        $displayName = $input->getOption('display-name');
        if (\is_string($displayName) && $displayName !== '') {
            $user->setDisplayName($displayName);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('User "%s" created with %s. You can log in on the website now.', $username, $role));

        return Command::SUCCESS;
    }
}
