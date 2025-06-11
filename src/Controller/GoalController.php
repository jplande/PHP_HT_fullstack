<?php

namespace App\Controller;

use App\Entity\Goal;
use App\Service\GoalService;
use App\Service\AnalyticsService;
use App\Repository\GoalRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Nelmio\ApiDocBundle\Attribute\Model;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/api/v1/goals')]
#[OA\Tag(name: 'Goals')]
final class GoalController extends AbstractController
{
    public function __construct(
        private GoalService $goalService,
        private AnalyticsService $analyticsService,
        private GoalRepository $goalRepository,
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private UrlGeneratorInterface $urlGenerator,
        private TagAwareCacheInterface $cache
    ) {}

    #[Route('', name: 'api_goals_list', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Liste des objectifs de l\'utilisateur',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Goal::class, groups: ['goal']))
        )
    )]
    #[OA\Parameter(name: 'status', description: 'Filtrer par statut', in: 'query', required: false)]
    #[OA\Parameter(name: 'category', description: 'Filtrer par catégorie', in: 'query', required: false)]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $status = $request->query->get('status');
        $categoryId = $request->query->get('category');

        $cacheKey = "goals_user_{$user->getId()}_{$status}_{$categoryId}";

        $goals = $this->cache->get($cacheKey, function() use ($user, $status, $categoryId) {
            if ($status) {
                return $this->goalRepository->findByUserAndStatus($user, $status);
            }

            if ($categoryId) {
                $category = $this->categoryRepository->find($categoryId);
                return $category ? $this->goalRepository->findByUserAndCategory($user, $category, null) : [];
            }

            return $this->goalRepository->findActiveByUser($user);
        });

        $jsonData = $this->serializer->serialize($goals, 'json', ['groups' => ['goal']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'api_goals_get', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Détails d\'un objectif',
        content: new OA\JsonContent(ref: new Model(type: Goal::class, groups: ['goal']))
    )]
    public function get(Goal $goal): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $goal);

        $jsonData = $this->serializer->serialize($goal, 'json', ['groups' => ['goal']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('', name: 'api_goals_create', methods: ['POST'])]
    #[OA\RequestBody(
        description: 'Données pour créer un objectif',
        required: true,
        content: new OA\JsonContent(
            required: ['title', 'frequencyType', 'categoryId', 'metrics'],
            properties: [
                new OA\Property(property: 'title', type: 'string', example: 'Faire 30 pompes par jour'),
                new OA\Property(property: 'description', type: 'string', example: 'Améliorer ma condition physique'),
                new OA\Property(property: 'frequencyType', type: 'string', enum: ['daily', 'weekly', 'monthly']),
                new OA\Property(property: 'categoryId', type: 'integer', example: 1),
                new OA\Property(property: 'startDate', type: 'string', format: 'date'),
                new OA\Property(property: 'endDate', type: 'string', format: 'date'),
                new OA\Property(
                    property: 'metrics',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'name', type: 'string', example: 'Pompes'),
                            new OA\Property(property: 'unit', type: 'string', example: 'répétitions'),
                            new OA\Property(property: 'evolutionType', type: 'string', enum: ['increase', 'decrease', 'maintain']),
                            new OA\Property(property: 'initialValue', type: 'number', example: 10),
                            new OA\Property(property: 'targetValue', type: 'number', example: 30),
                            new OA\Property(property: 'isPrimary', type: 'boolean', example: true)
                        ]
                    )
                )
            ]
        )
    )]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = $request->toArray();

        // Validation des données
        if (empty($data['title']) || empty($data['frequencyType']) || empty($data['metrics'])) {
            return new JsonResponse(['error' => 'Données manquantes'], Response::HTTP_BAD_REQUEST);
        }

        // Préparer les données d'objectif
        $goalData = [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'frequencyType' => $data['frequencyType'],
            'categoryId' => $data['categoryId'] ?? null,
            'startDate' => isset($data['startDate']) ? new \DateTime($data['startDate']) : new \DateTime(),
            'endDate' => isset($data['endDate']) ? new \DateTime($data['endDate']) : null
        ];

        try {
            $goal = $this->goalService->createGoal($user, $goalData, $data['metrics']);

            // Invalider le cache
            $this->cache->invalidateTags(['goals_cache', "user_{$user->getId()}_goals"]);

            $jsonData = $this->serializer->serialize($goal, 'json', ['groups' => ['goal']]);
            $location = $this->urlGenerator->generate('api_goals_get', ['id' => $goal->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

            return new JsonResponse($jsonData, Response::HTTP_CREATED, ['Location' => $location], true);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'api_goals_update', methods: ['PATCH'])]
    #[OA\RequestBody(
        description: 'Données à mettre à jour',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'title', type: 'string'),
                new OA\Property(property: 'description', type: 'string'),
                new OA\Property(property: 'status', type: 'string', enum: ['active', 'completed', 'paused', 'archived']),
                new OA\Property(property: 'endDate', type: 'string', format: 'date')
            ]
        )
    )]
    public function update(Goal $goal, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $goal);

        $data = $request->toArray();

        try {
            $this->goalService->updateGoal($goal, $data);

            // Invalider le cache
            $this->cache->invalidateTags(['goals_cache', "goal_{$goal->getId()}"]);

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'api_goals_delete', methods: ['DELETE'])]
    #[OA\Parameter(name: 'hard', description: 'Suppression définitive', in: 'query', required: false)]
    public function delete(Goal $goal, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('delete', $goal);

        $hardDelete = $request->query->getBoolean('hard', false);

        try {
            $this->goalService->deleteGoal($goal, $hardDelete);

            // Invalider le cache
            $this->cache->invalidateTags(['goals_cache', "goal_{$goal->getId()}"]);

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/duplicate', name: 'api_goals_duplicate', methods: ['POST'])]
    #[OA\Response(
        response: 201,
        description: 'Objectif dupliqué avec succès',
        content: new OA\JsonContent(ref: new Model(type: Goal::class, groups: ['goal']))
    )]
    public function duplicate(Goal $goal): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $goal);

        try {
            $duplicatedGoal = $this->goalService->duplicateGoal($goal, $this->getUser());

            $jsonData = $this->serializer->serialize($duplicatedGoal, 'json', ['groups' => ['goal']]);
            $location = $this->urlGenerator->generate('api_goals_get', ['id' => $duplicatedGoal->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

            return new JsonResponse($jsonData, Response::HTTP_CREATED, ['Location' => $location], true);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/statistics', name: 'api_goals_statistics', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Statistiques détaillées de l\'objectif',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'overall_completion', type: 'number'),
                new OA\Property(property: 'completed_metrics', type: 'integer'),
                new OA\Property(property: 'total_progress_entries', type: 'integer'),
                new OA\Property(property: 'days_since_last_progress', type: 'integer')
            ]
        )
    )]
    public function statistics(Goal $goal): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $goal);

        $statistics = $this->goalService->getGoalStatistics($goal);

        return new JsonResponse($statistics);
    }

    #[Route('/{id}/analytics', name: 'api_goals_analytics', methods: ['GET'])]
    #[OA\Parameter(name: 'days', description: 'Nombre de jours à analyser', in: 'query', required: false)]
    #[OA\Parameter(name: 'chart_type', description: 'Type de graphique', in: 'query', required: false)]
    public function analytics(Goal $goal, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $goal);

        $days = $request->query->getInt('days', 30);
        $chartType = $request->query->get('chart_type', 'line');

        $cacheKey = "goal_analytics_{$goal->getId()}_{$days}_{$chartType}";

        $analytics = $this->cache->get($cacheKey, function() use ($goal, $chartType, $days) {
            return [
                'chart_data' => $this->analyticsService->generateChartData($goal, $chartType, $days),
                'trend_analysis' => $this->analyticsService->calculateProgressTrend($goal, $days),
                'completion_rate' => $this->analyticsService->getCompletionRate($goal),
                'prediction' => $this->analyticsService->predictGoalCompletion($goal)
            ];
        });

        return new JsonResponse($analytics);
    }

    #[Route('/{id}/progress-report', name: 'api_goals_progress_report', methods: ['GET'])]
    #[OA\Parameter(name: 'days', description: 'Période du rapport en jours', in: 'query', required: false)]
    public function progressReport(Goal $goal, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $goal);

        $days = $request->query->getInt('days', 30);

        $report = $this->goalService->generateProgressReport($goal, $days);

        return new JsonResponse($report);
    }

    #[Route('/recommendations', name: 'api_goals_recommendations', methods: ['GET'])]
    #[OA\Parameter(name: 'limit', description: 'Nombre de recommandations', in: 'query', required: false)]
    public function recommendations(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $limit = $request->query->getInt('limit', 5);

        $recommendations = $this->goalService->getRecommendedGoals($user, $limit);

        return new JsonResponse($recommendations);
    }

    #[Route('/dashboard', name: 'api_goals_dashboard', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Données pour le dashboard des objectifs',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'active_goals', type: 'array'),
                new OA\Property(property: 'statistics', type: 'object'),
                new OA\Property(property: 'recent_progress', type: 'array')
            ]
        )
    )]
    public function dashboard(): JsonResponse
    {
        $user = $this->getUser();

        $cacheKey = "dashboard_goals_{$user->getId()}";

        $dashboardData = $this->cache->get($cacheKey, function() use ($user) {
            return [
                'active_goals' => $this->goalRepository->findForDashboard($user),
                'statistics' => $this->goalRepository->getStatsForUser($user),
                'recent_progress' => $this->goalRepository->findRecentlyUpdated($user, 10),
                'ending_soon' => $this->goalRepository->findEndingSoon($user, 7),
                'needing_update' => $this->goalRepository->findNeedingUpdate($user, 3)
            ];
        });

        $jsonData = $this->serializer->serialize($dashboardData, 'json', ['groups' => ['goal', 'dashboard']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/search', name: 'api_goals_search', methods: ['GET'])]
    #[OA\Parameter(name: 'q', description: 'Terme de recherche', in: 'query', required: true)]
    public function search(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $query = $request->query->get('q');

        if (strlen($query) < 2) {
            return new JsonResponse(['error' => 'Le terme de recherche doit faire au moins 2 caractères'], Response::HTTP_BAD_REQUEST);
        }

        $goals = $this->goalRepository->searchByText($user, $query);

        $jsonData = $this->serializer->serialize($goals, 'json', ['groups' => ['goal']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}/similar', name: 'api_goals_similar', methods: ['GET'])]
    #[OA\Parameter(name: 'limit', description: 'Nombre d\'objectifs similaires', in: 'query', required: false)]
    public function similar(Goal $goal, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $goal);

        $limit = $request->query->getInt('limit', 5);

        $similarGoals = $this->goalRepository->findSimilar($goal, $limit);

        $jsonData = $this->serializer->serialize($similarGoals, 'json', ['groups' => ['goal']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/export', name: 'api_goals_export', methods: ['GET'])]
    #[OA\Parameter(name: 'format', description: 'Format d\'export', in: 'query', required: false)]
    public function export(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $format = $request->query->get('format', 'json');

        $goals = $this->goalRepository->findBy(['user' => $user]);

        if ($format === 'csv') {
            // TODO: Implémenter l'export CSV
            return new JsonResponse(['error' => 'Export CSV non encore implémenté'], Response::HTTP_NOT_IMPLEMENTED);
        }

        $jsonData = $this->serializer->serialize($goals, 'json', ['groups' => ['goal']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }
}
