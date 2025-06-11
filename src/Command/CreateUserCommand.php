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
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Créer un nouvel utilisateur',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Nom d\'utilisateur')
            ->addArgument('password', InputArgument::REQUIRED, 'Mot de passe')
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Email de l\'utilisateur')
            ->addOption('first-name', null, InputOption::VALUE_OPTIONAL, 'Prénom')
            ->addOption('last-name', null, InputOption::VALUE_OPTIONAL, 'Nom de famille')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Créer un administrateur')
            ->setHelp('Cette commande permet de créer un nouvel utilisateur...')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $username = $input->getArgument('username');
        $password = $input->getArgument('password');
        $email = $input->getOption('email');
        $firstName = $input->getOption('first-name');
        $lastName = $input->getOption('last-name');
        $isAdmin = $input->getOption('admin');

        // Vérifier si l'utilisateur existe déjà
        $existingUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['username' => $username]);

        if ($existingUser) {
            $io->error("L'utilisateur '{$username}' existe déjà !");
            return Command::FAILURE;
        }

        // Créer le nouvel utilisateur
        $user = new User();
        $user->setUsername($username);

        // Hasher le mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Définir les données optionnelles
        if ($email) {
            $user->setEmail($email);
        }

        if ($firstName) {
            $user->setFirstName($firstName);
        }

        if ($lastName) {
            $user->setLastName($lastName);
        }

        // Définir les rôles
        if ($isAdmin) {
            $user->setRoles(['ROLE_ADMIN']);
        }

        // Sauvegarder en base
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success("Utilisateur '{$username}' créé avec succès !");

        // Afficher les informations
        $io->table(
            ['Propriété', 'Valeur'],
            [
                ['ID', $user->getId()],
                ['Username', $user->getUsername()],
                ['Email', $user->getEmail() ?: 'Non défini'],
                ['Nom complet', $user->getFullName()],
                ['Rôles', implode(', ', $user->getRoles())],
                ['Niveau', $user->getLevel()],
                ['Points', $user->getTotalPoints()],
                ['Créé le', $user->getCreatedAt()->format('Y-m-d H:i:s')],
            ]
        );

        return Command::SUCCESS;
    }
}
