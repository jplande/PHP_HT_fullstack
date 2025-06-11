<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route('/api/users', name: 'api_user_')]
class UserApiController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(UserRepository $userRepository): JsonResponse
    {
        $users = $userRepository->findAll();

        return $this->json($users, Response::HTTP_OK, [], [
            'groups' => ['user', 'dashboard']
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(User $user): JsonResponse
    {
        return $this->json($user, Response::HTTP_OK, [], [
            'groups' => ['user', 'dashboard']
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Données JSON invalides'], Response::HTTP_BAD_REQUEST);
        }

        // Validation des champs requis
        if (empty($data['username']) || empty($data['password'])) {
            return $this->json([
                'error' => 'Username et password sont requis'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier si l'utilisateur existe déjà
        $existingUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['username' => $data['username']]);

        if ($existingUser) {
            return $this->json([
                'error' => "L'utilisateur '{$data['username']}' existe déjà"
            ], Response::HTTP_CONFLICT);
        }

        // Créer le nouvel utilisateur
        $user = new User();
        $user->setUsername($data['username']);

        // Hasher le mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        // Données optionnelles
        if (isset($data['email'])) {
            $user->setEmail($data['email']);
        }
        if (isset($data['firstName'])) {
            $user->setFirstName($data['firstName']);
        }
        if (isset($data['lastName'])) {
            $user->setLastName($data['lastName']);
        }
        if (isset($data['isAdmin']) && $data['isAdmin']) {
            $user->setRoles(['ROLE_ADMIN']);
        }

        // Validation
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        // Sauvegarder
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json($user, Response::HTTP_CREATED, [], [
            'groups' => ['user', 'dashboard']
        ]);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(User $user, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Données JSON invalides'], Response::HTTP_BAD_REQUEST);
        }

        // Mise à jour des champs
        if (isset($data['username'])) {
            $user->setUsername($data['username']);
        }
        if (isset($data['email'])) {
            $user->setEmail($data['email']);
        }
        if (isset($data['firstName'])) {
            $user->setFirstName($data['firstName']);
        }
        if (isset($data['lastName'])) {
            $user->setLastName($data['lastName']);
        }
        if (isset($data['password'])) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);
        }
        if (isset($data['status'])) {
            $user->setStatus($data['status']);
        }

        // Validation
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json($user, Response::HTTP_OK, [], [
            'groups' => ['user', 'dashboard']
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(User $user): JsonResponse
    {
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return $this->json(['message' => 'Utilisateur supprimé avec succès'], Response::HTTP_OK);
    }

    #[Route('/{id}/stats', name: 'stats', methods: ['GET'])]
    public function stats(User $user): JsonResponse
    {
        return $this->json([
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'fullName' => $user->getFullName(),
                'level' => $user->getLevel(),
                'rank' => $user->getRank(),
                'totalPoints' => $user->getTotalPoints(),
                'currentStreak' => $user->getCurrentStreak(),
                'longestStreak' => $user->getLongestStreak(),
                'isActive' => $user->isActive(),
            ],
            'stats' => $user->getStats(),
            'levelProgress' => [
                'currentLevel' => $user->getLevel(),
                'pointsToNext' => $user->getPointsToNextLevel(),
                'progressPercentage' => $user->getLevelProgressPercentage(),
            ]
        ]);
    }

    #[Route('/{id}/add-points', name: 'add_points', methods: ['POST'])]
    public function addPoints(User $user, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['points']) || !is_numeric($data['points'])) {
            return $this->json(['error' => 'Points requis (nombre)'], Response::HTTP_BAD_REQUEST);
        }

        $points = (int) $data['points'];
        $oldLevel = $user->getLevel();

        $user->addPoints($points);
        $this->entityManager->flush();

        $levelUp = $user->getLevel() > $oldLevel;

        return $this->json([
            'message' => "{$points} points ajoutés",
            'levelUp' => $levelUp,
            'newLevel' => $user->getLevel(),
            'totalPoints' => $user->getTotalPoints(),
        ]);
    }

    #[Route('/{id}/update-streak', name: 'update_streak', methods: ['POST'])]
    public function updateStreak(User $user): JsonResponse
    {
        $oldStreak = $user->getCurrentStreak();
        $user->updateStreak();
        $this->entityManager->flush();

        return $this->json([
            'message' => 'Streak mis à jour',
            'oldStreak' => $oldStreak,
            'newStreak' => $user->getCurrentStreak(),
            'longestStreak' => $user->getLongestStreak(),
        ]);
    }

    #[Route('/logout', name: 'logout_jwt', methods: ['POST'])]
    public function logoutJWT(Request $request): JsonResponse
    {
        $authHeader = $request->headers->get('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->json([
                'error' => 'Token manquant'
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'message' => 'Utilisateur déconnecté avec succès',
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
        ], Response::HTTP_OK);
    }
}
