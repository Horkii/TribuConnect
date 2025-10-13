<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-admin', description: 'Create an administrator account')]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher
    ) { parent::__construct(); }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Admin email')
            ->addOption('phone', null, InputOption::VALUE_OPTIONAL, 'Admin phone number for 2FA (e.g. +33612345678)')
            ->addOption('enable-2fa', null, InputOption::VALUE_NONE, 'Enable 2FA on the account')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = strtolower((string)$input->getArgument('email'));
        $phone = $input->getOption('phone');
        $enable2fa = (bool)$input->getOption('enable-2fa');

        $repo = $this->em->getRepository(User::class);
        $existing = $repo->findOneBy(['email' => $email]);
        if ($existing) {
            $output->writeln('<error>Un utilisateur avec cet email existe déjà.</error>');
            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Admin');
        $user->setLastName('User');
        $password = self::generateStrongPassword();
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $user->setRoles(['ROLE_ADMIN']);
        if ($phone) { $user->setPhoneNumber($phone); }
        if ($enable2fa) { $user->setTwoFactorEnabled(true); }

        $this->em->persist($user);
        $this->em->flush();

        $output->writeln('<info>Administrateur créé</info>');
        $output->writeln('Email: '.$email);
        $output->writeln('Mot de passe: '.$password);
        if ($phone) { $output->writeln('Téléphone: '.$phone); }
        if ($enable2fa) { $output->writeln('2FA: activée'); }
        return Command::SUCCESS;
    }

    private static function generateStrongPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnopqrstuvwxyz';
        $digits = '23456789';
        $symbols = '!@#$%^&*()-_=+[]{};:,.?';
        $all = $alphabet.$lower.$digits.$symbols;
        $len = 24;
        $pw = '';
        for ($i=0;$i<$len;$i++) { $pw .= $all[random_int(0, strlen($all)-1)]; }
        // ensure classes
        $pw[ random_int(0,$len-1) ] = $alphabet[random_int(0, strlen($alphabet)-1)];
        $pw[ random_int(0,$len-1) ] = $lower[random_int(0, strlen($lower)-1)];
        $pw[ random_int(0,$len-1) ] = $digits[random_int(0, strlen($digits)-1)];
        $pw[ random_int(0,$len-1) ] = $symbols[random_int(0, strlen($symbols)-1)];
        return $pw;
    }
}

