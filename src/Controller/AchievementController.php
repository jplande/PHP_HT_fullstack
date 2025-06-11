<?php

namespace App\Controller;

use App\Entity\Achievement;
use App\Entity\UserAchievement;
use App\Service\AchievementService;
use App\Repository\AchievementRepository;
use App\Repository\UserAchievementRepository;
use OpenApi\Attributes as OA;
use Nelmio\ApiDocBundle\Attribute\Model;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/api/v1/achievements')]
#[OA\Tag(name: 'Achievements')]
final class AchievementController extends AbstractController
{
    public function __construct(
        private readonly AchievementService        $achievementService,
        private readonly AchievementRepository     $achievementRepository,
        private readonly UserAchievementRepository $userAchievementRepository,
        private readonly SerializerInterface       $serializer,
        private readonly TagAwareCacheInterface    $cache
    ) {}

    #[Route('', name: 'api_achievements_list', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Liste de tous les badges disponibles',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Achievement::class, groups: ['achievement']))
        )
    )]
    #[OA\Parameter(name: 'level', description: 'Filtrer par niveau', in: 'query', required: false)]
    #[OA\Parameter(name: 'category', description: 'Filtrer par catégorie', in: 'query', required: false)]
    #[OA\Parameter(name: 'include_secret', description: 'Inclure les badges secrets', in: 'query', required: false)]
    public function list(Request $request): JsonResponse
    {
        $level = $request->query->get('level');
        $category = $request->query->get('category');
        $includeSecret = $request->query->getBoolean('include_secret', false);

        $cacheKey = "achievements_list_{$level}_{$category}_{$includeSecret}";

        $achievements = $this->cache->get($cacheKey, function() use ($level, $category, $includeSecret) {
            if ($level) {
                return $this->achievementRepository->findByLevel($level);
            }

            if ($category) {
                return $this->achievementRepository->findByCategory($category);
            }

            return $includeSecret ?
                $this->achievementRepository->findActive() :
                $this->achievementRepository->findPublic();
        });

        $jsonData = $this->serializer->serialize($achievements, 'json', ['groups' => ['achievement']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'api_achievements_get', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Détails d\'un badge',
        content: new OA\JsonContent(ref: new Model(type: Achievement::class, groups: ['achievement']))
    )]
    public function get(Achievement $achievement): JsonResponse
    {
        $jsonData = $this->serializer->serialize($achievement, 'json', ['groups' => ['achievement']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/my', name: 'api_achievements_my', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Badges débloqués par l\'utilisateur',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: UserAchievement::class, groups: ['user_achievement']))
        )
    )]
    #[OA\Parameter(name: 'level', description: 'Filtrer par niveau', in: 'query', required: false)]
    #[OA\Parameter(name: 'category', description: 'Filtrer par catégorie', in: 'query', required: false)]
    public function myAchievements(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $level = $request->query->get('level');
        $category = $request->query->get('category');

        if ($level) {
            $userAchievements = $this->userAchievementRepository->findByUserAndLevel($user, $level);
        } elseif ($category) {
            $userAchievements = $this->userAchievementRepository->findByUserAndCategory($user, $category);
        } else {
            $userAchievements = $this->userAchievementRepository->findByUser($user);
        }

        $jsonData = $this->serializer->serialize($userAchievements, 'json', ['groups' => ['user_achievement']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/available', name: 'api_achievements_available', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Badges disponibles pour l\'utilisateur (non encore obtenus)',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Achievement::class, groups: ['achievement']))
        )
    )]
    public function available(): JsonResponse
    {
        $user = $this->getUser();

        $cacheKey = "available_achievements_{$user->getId()}";

        $achievements = $this->cache->get($cacheKey, function() use ($user) {
            return $this->achievementRepository->findAvailableForUser($user);
        });

        $jsonData = $this->serializer->serialize($achievements, 'json', ['groups' => ['achievement']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/recommendations', name: 'api_achievements_recommendations', methods: ['GET'])]
    #[OA\Parameter(name: 'limit', description: 'Nombre de recommandations', in: 'query', required: false)]
    #[OA\Response(
        response: 200,
        description: 'Badges recommandés pour l\'utilisateur',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'achievement', ref: new Model(type: Achievement::class, groups: ['achievement'])),
                    new OA\Property(property: 'progress', type: 'object'),
                    new OA\Property(property: 'priority', type: 'integer')
                ]
            )
        )
    )]
    public function recommendations(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $limit = $request->query->getInt('limit', 5);

        $cacheKey = "achievement_recommendations_{$user->getId()}_{$limit}";

        $recommendations = $this->cache->get($cacheKey, function() use ($user, $limit) {
            return $this->achievementService->getRecommendedAchievements($user, $limit);
        });

        return new JsonResponse($recommendations);
    }

    #[Route('/progress/{id}', name: 'api_achievements_progress', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Progression vers un badge spécifique',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'percentage', type: 'number'),
                new OA\Property(property: 'current', type: 'number'),
                new OA\Property(property: 'target', type: 'number')
            ]
        )
    )]
    public function progress(Achievement $achievement): JsonResponse
    {
        $user = $this->getUser();

        // Vérifier si l'utilisateur possède déjà ce badge
        if ($this->userAchievementRepository->userHasAchievement($user, $achievement)) {
            return new JsonResponse([
                'percentage' => 100,
                'current' => 1,
                'target' => 1,
                'completed' => true
            ]);
        }

        $progress = $this->achievementService->calculateAchievementProgress($user, $achievement);

        return new JsonResponse($progress);
    }

    #[Route('/check', name: 'api_achievements_check', methods: ['POST'])]
    #[OA\Response(
        response: 200,
        description: 'Vérifie et débloque les nouveaux badges',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'unlocked_achievements', type: 'array'),
                new OA\Property(property: 'count', type: 'integer')
            ]
        )
    )]
    public function check(): JsonResponse
    {
        $user = $this->getUser();

        try {
            $unlockedAchievements = $this->achievementService->checkAndUnlockAchievements($user);

            // Invalider le cache
            $this->cache->invalidateTags(['achievements_cache', "user_{$user->getId()}_achievements"]);

            $jsonData = $this->serializer->serialize($unlockedAchievements, 'json', ['groups' => ['user_achievement']]);

            return new JsonResponse([
                'unlocked_achievements' => json_decode($jsonData, true),
                'count' => count($unlockedAchievements)
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/statistics', name: 'api_achievements_statistics', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Statistiques des badges de l\'utilisateur',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'total_achievements', type: 'integer'),
                new OA\Property(property: 'total_points', type: 'integer'),
                new OA\Property(property: 'completion_percentage', type: 'number'),
                new OA\Property(property: 'stats_by_level', type: 'object'),
                new OA\Property(property: 'recent_achievements', type: 'integer'),
                new OA\Property(property: 'next_milestone', type: 'object'),
                new OA\Property(property: 'rarest_achievement', type: 'object')
            ]
        )
    )]
    public function statistics(): JsonResponse
    {
        $user = $this->getUser();

        $cacheKey = "achievement_stats_{$user->getId()}";

        $statistics = $this->cache->get($cacheKey, function() use ($user) {
            return $this->achievementService->getUserAchievementStats($user);
        });

        return new JsonResponse($statistics);
    }

    #[Route('/leaderboard', name: 'api_achievements_leaderboard', methods: ['GET'])]
    #[OA\Parameter(name: 'type', description: 'Type de classement (points, achievements, rare)', in: 'query', required: false)]
    #[OA\Parameter(name: 'limit', description: 'Nombre d\'utilisateurs à retourner', in: 'query', required: false)]
    #[OA\Response(
        response: 200,
        description: 'Classement des utilisateurs par badges',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer'),
                    new OA\Property(property: 'username', type: 'string'),
                    new OA\Property(property: 'total_points', type: 'integer'),
                    new OA\Property(property: 'total_achievements', type: 'integer')
                ]
            )
        )
    )]
    public function leaderboard(Request $request): JsonResponse
    {
        $type = $request->query->get('type', 'points');
        $limit = $request->query->getInt('limit', 10);

        $cacheKey = "achievement_leaderboard_{$type}_{$limit}";

        $leaderboard = $this->cache->get($cacheKey, function() use ($type, $limit) {
            return $this->achievementService->getLeaderboard($type, $limit);
        });

        return new JsonResponse($leaderboard);
    }

    #[Route('/recent', name: 'api_achievements_recent', methods: ['GET'])]
    #[OA\Parameter(name: 'days', description: 'Nombre de jours à considérer', in: 'query', required: false)]
    #[OA\Response(
        response: 200,
        description: 'Badges récemment débloqués par l\'utilisateur',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: UserAchievement::class, groups: ['user_achievement']))
        )
    )]
    public function recent(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $days = $request->query->getInt('days', 30);

        $recentAchievements = $this->userAchievementRepository->findRecentByUser($user, $days);

        $jsonData = $this->serializer->serialize($recentAchievements, 'json', ['groups' => ['user_achievement']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/unnotified', name: 'api_achievements_unnotified', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Badges non encore notifiés à l\'utilisateur',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: UserAchievement::class, groups: ['user_achievement']))
        )
    )]
    public function unnotified(): JsonResponse
    {
        $user = $this->getUser();

        $unnotifiedAchievements = $this->achievementService->getUnnotifiedAchievements($user);

        $jsonData = $this->serializer->serialize($unnotifiedAchievements, 'json', ['groups' => ['user_achievement']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/mark-notified', name: 'api_achievements_mark_notified', methods: ['PATCH'])]
    #[OA\RequestBody(
        description: 'IDs des badges à marquer comme notifiés',
        required: true,
        content: new OA\JsonContent(
            required: ['achievement_ids'],
            properties: [
                new OA\Property(
                    property: 'achievement_ids',
                    type: 'array',
                    items: new OA\Items(type: 'integer'),
                    example: [1, 2, 3]
                )
            ]
        )
    )]
    public function markNotified(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = $request->toArray();

        if (!isset($data['achievement_ids']) || !is_array($data['achievement_ids'])) {
            return new JsonResponse(['error' => 'achievement_ids requis (tableau)'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $userAchievements = [];
            foreach ($data['achievement_ids'] as $achievementId) {
                $userAchievement = $this->userAchievementRepository->findOneBy([
                    'user' => $user,
                    'achievement' => $achievementId
                ]);

                if ($userAchievement) {
                    $userAchievements[] = $userAchievement;
                }
            }

            $this->achievementService->markAchievementsAsNotified($userAchievements);

            return new JsonResponse(['message' => 'Badges marqués comme notifiés']);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/global-statistics', name: 'api_achievements_global_statistics', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Statistiques globales des badges',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'level', type: 'string'),
                    new OA\Property(property: 'total_achievements', type: 'integer'),
                    new OA\Property(property: 'total_points', type: 'integer'),
                    new OA\Property(property: 'avg_points', type: 'number')
                ]
            )
        )
    )]
    public function globalStatistics(): JsonResponse
    {
        $cacheKey = "global_achievement_statistics";

        $statistics = $this->cache->get($cacheKey, function() {
            return $this->achievementRepository->getStatistics();
        });

        return new JsonResponse($statistics);
    }

    #[Route('/popular', name: 'api_achievements_popular', methods: ['GET'])]
    #[OA\Parameter(name: 'limit', description: 'Nombre de badges à retourner', in: 'query', required: false)]
    #[OA\Response(
        response: 200,
        description: 'Badges les plus populaires (les plus obtenus)',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'achievement', ref: new Model(type: Achievement::class, groups: ['achievement'])),
                    new OA\Property(property: 'unlock_count', type: 'integer')
                ]
            )
        )
    )]
    public function popular(Request $request): JsonResponse
    {
        $limit = $request->query->getInt('limit', 10);

        $cacheKey = "popular_achievements_{$limit}";

        $popularAchievements = $this->cache->get($cacheKey, function() use ($limit) {
            return $this->achievementRepository->findMostPopular($limit);
        });

        return new JsonResponse($popularAchievements);
    }

    #[Route('/rare', name: 'api_achievements_rare', methods: ['GET'])]
    #[OA\Parameter(name: 'limit', description: 'Nombre de badges à retourner', in: 'query', required: false)]
    #[OA\Response(
        response: 200,
        description: 'Badges les plus rares (les moins obtenus)',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'achievement', ref: new Model(type: Achievement::class, groups: ['achievement'])),
                    new OA\Property(property: 'unlock_count', type: 'integer')
                ]
            )
        )
    )]
    public function rare(Request $request): JsonResponse
    {
        $limit = $request->query->getInt('limit', 10);

        $cacheKey = "rare_achievements_{$limit}";

        $rareAchievements = $this->cache->get($cacheKey, function() use ($limit) {
            return $this->achievementRepository->findRarest($limit);
        });

        return new JsonResponse($rareAchievements);
    }

    #[Route('/search', name: 'api_achievements_search', methods: ['GET'])]
    #[OA\Parameter(name: 'q', description: 'Terme de recherche', in: 'query', required: true)]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q');

        if (strlen($query) < 2) {
            return new JsonResponse(['error' => 'Le terme de recherche doit faire au moins 2 caractères'], Response::HTTP_BAD_REQUEST);
        }

        $achievements = $this->achievementRepository->searchByName($query);

        $jsonData = $this->serializer->serialize($achievements, 'json', ['groups' => ['achievement']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/by-points-range', name: 'api_achievements_by_points_range', methods: ['GET'])]
    #[OA\Parameter(name: 'min_points', description: 'Points minimum', in: 'query', required: true)]
    #[OA\Parameter(name: 'max_points', description: 'Points maximum', in: 'query', required: true)]
    public function byPointsRange(Request $request): JsonResponse
    {
        $minPoints = $request->query->getInt('min_points');
        $maxPoints = $request->query->getInt('max_points');

        if ($minPoints < 0 || $maxPoints < $minPoints) {
            return new JsonResponse(['error' => 'Plage de points invalide'], Response::HTTP_BAD_REQUEST);
        }

        $achievements = $this->achievementRepository->findByPointsRange($minPoints, $maxPoints);

        $jsonData = $this->serializer->serialize($achievements, 'json', ['groups' => ['achievement']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/compare-users/{userId}', name: 'api_achievements_compare_users', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Comparaison des badges entre deux utilisateurs',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'user1_stats', type: 'array'),
                new OA\Property(property: 'user2_stats', type: 'array'),
                new OA\Property(property: 'common_achievements', type: 'array')
            ]
        )
    )]
    public function compareUsers(int $userId): JsonResponse
    {
        $currentUser = $this->getUser();

        // Vérifier que l'utilisateur cible existe
        $targetUser = $this->userAchievementRepository->find($userId)?->getUser();
        if (!$targetUser) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $comparison = $this->userAchievementRepository->compareUsers($currentUser, $targetUser);

        return new JsonResponse($comparison);
    }

    #[Route('/monthly-progress', name: 'api_achievements_monthly_progress', methods: ['GET'])]
    #[OA\Parameter(name: 'months', description: 'Nombre de mois à analyser', in: 'query', required: false)]
    #[OA\Response(
        response: 200,
        description: 'Progression mensuelle des badges',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'year', type: 'integer'),
                    new OA\Property(property: 'month', type: 'integer'),
                    new OA\Property(property: 'achievements_count', type: 'integer'),
                    new OA\Property(property: 'points_earned', type: 'integer')
                ]
            )
        )
    )]
    public function monthlyProgress(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $months = $request->query->getInt('months', 12);

        $cacheKey = "monthly_achievement_progress_{$user->getId()}_{$months}";

        $progress = $this->cache->get($cacheKey, function() use ($user, $months) {
            return $this->userAchievementRepository->getMonthlyProgress($user, $months);
        });

        return new JsonResponse($progress);
    }

    #[Route('/latest-unlocked', name: 'api_achievements_latest_unlocked', methods: ['GET'])]
    #[OA\Parameter(name: 'limit', description: 'Nombre de badges à retourner', in: 'query', required: false)]
    #[OA\Response(
        response: 200,
        description: 'Derniers badges débloqués (tous utilisateurs)',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: UserAchievement::class, groups: ['user_achievement']))
        )
    )]
    public function latestUnlocked(Request $request): JsonResponse
    {
        $limit = $request->query->getInt('limit', 20);

        $cacheKey = "latest_unlocked_achievements_{$limit}";

        $latestAchievements = $this->cache->get($cacheKey, function() use ($limit) {
            return $this->userAchievementRepository->findLatestUnlocked($limit);
        });

        $jsonData = $this->serializer->serialize($latestAchievements, 'json', ['groups' => ['user_achievement']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/by-period', name: 'api_achievements_by_period', methods: ['GET'])]
    #[OA\Parameter(name: 'start_date', description: 'Date de début (YYYY-MM-DD)', in: 'query', required: true)]
    #[OA\Parameter(name: 'end_date', description: 'Date de fin (YYYY-MM-DD)', in: 'query', required: true)]
    public function byPeriod(Request $request): JsonResponse
    {
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        if (!$startDate || !$endDate) {
            return new JsonResponse(['error' => 'start_date et end_date sont requis'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $achievements = $this->userAchievementRepository->findByPeriod(
                new \DateTime($startDate),
                new \DateTime($endDate)
            );

            $jsonData = $this->serializer->serialize($achievements, 'json', ['groups' => ['user_achievement']]);

            return new JsonResponse($jsonData, Response::HTTP_OK, [], true);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Dates invalides'], Response::HTTP_BAD_REQUEST);
        }
    }
}
