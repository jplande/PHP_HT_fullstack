<?php

namespace App\Controller;

use App\Entity\Progress;
use App\Entity\Goal;
use App\Entity\Metric;
use App\Service\ProgressService;
use App\Repository\ProgressRepository;
use App\Repository\GoalRepository;
use App\Repository\MetricRepository;
use OpenApi\Attributes as OA;
use Nelmio\ApiDocBundle\Attribute\Model;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/api/v1/progress')]
#[OA\Tag(name: 'Progress')]
final class ProgressController extends AbstractController
{
    public function __construct(
        private ProgressService $progressService,
        private ProgressRepository $progressRepository,
        private GoalRepository $goalRepository,
        private MetricRepository $metricRepository,
        private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator,
        private TagAwareCacheInterface $cache
    ) {}

    #[Route('', name: 'api_progress_list', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Liste des progressions de l\'utilisateur',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Progress::class, groups: ['progress']))
        )
    )]
    #[OA\Parameter(name: 'goal_id', description: 'Filtrer par objectif', in: 'query', required: false)]
    #[OA\Parameter(name: 'date_from', description: 'Date de début', in: 'query', required: false)]
    #[OA\Parameter(name: 'date_to', description: 'Date de fin', in: 'query', required: false)]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $goalId = $request->query->get('goal_id');
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');

        if ($goalId) {
            $goal = $this->goalRepository->find($goalId);
            if (!$goal || $goal->getUser() !== $user) {
                return new JsonResponse(['error' => 'Objectif non trouvé'], Response::HTTP_NOT_FOUND);
            }

            if ($dateFrom && $dateTo) {
                $progressEntries = $this->progressRepository->findByGoalAndPeriod(
                    $goal,
                    new \DateTime($dateFrom),
                    new \DateTime($dateTo)
                );
            } else {
                $progressEntries = $this->progressRepository->findByGoal($goal);
            }
        } else {
            if ($dateFrom && $dateTo) {
                $progressEntries = $this->progressRepository->findCompletedInPeriod(
                    $user,
                    new \DateTime($dateFrom),
                    new \DateTime($dateTo)
                );
            } else {
                $progressEntries = $this->progressRepository->getTodayProgress($user);
            }
        }

        $jsonData = $this->serializer->serialize($progressEntries, 'json', ['groups' => ['progress']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'api_progress_get', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Détails d\'une progression',
        content: new OA\JsonContent(ref: new Model(type: Progress::class, groups: ['progress']))
    )]
    public function get(Progress $progress): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $progress);

        $jsonData = $this->serializer->serialize($progress, 'json', ['groups' => ['progress']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('', name: 'api_progress_create', methods: ['POST'])]
    #[OA\RequestBody(
        description: 'Données pour créer une progression',
        required: true,
        content: new OA\JsonContent(
            required: ['goal_id', 'metric_id', 'value'],
            properties: [
                new OA\Property(property: 'goal_id', type: 'integer', example: 1),
                new OA\Property(property: 'metric_id', type: 'integer', example: 1),
                new OA\Property(property: 'value', type: 'number', example: 25.5),
                new OA\Property(property: 'date', type: 'string', format: 'date', example: '2025-06-10'),
                new OA\Property(property: 'notes', type: 'string', example: 'Très bonne session !'),
                new OA\Property(property: 'difficulty_rating', type: 'integer', minimum: 1, maximum: 10),
                new OA\Property(property: 'satisfaction_rating', type: 'integer', minimum: 1, maximum: 10),
                new OA\Property(property: 'metadata', type: 'object')
            ]
        )
    )]
    public function create(Request $request): JsonResponse
    {
        $data = $request->toArray();

        // Validation des données requises
        if (!isset($data['goal_id'], $data['metric_id'], $data['value'])) {
            return new JsonResponse(['error' => 'Données manquantes (goal_id, metric_id, value)'], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier l'objectif
        $goal = $this->goalRepository->find($data['goal_id']);
        if (!$goal || $goal->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Objectif non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier la métrique
        $metric = $this->metricRepository->find($data['metric_id']);
        if (!$metric || $metric->getGoal() !== $goal) {
            return new JsonResponse(['error' => 'Métrique non trouvée ou non associée à cet objectif'], Response::HTTP_NOT_FOUND);
        }

        // Préparer les données de progression
        $progressData = [
            'value' => floatval($data['value']),
            'date' => isset($data['date']) ? new \DateTime($data['date']) : new \DateTime(),
            'notes' => $data['notes'] ?? null,
            'difficultyRating' => $data['difficulty_rating'] ?? null,
            'satisfactionRating' => $data['satisfaction_rating'] ?? null,
            'metadata' => $data['metadata'] ?? null
        ];

        try {
            $progress = $this->progressService->recordProgress($goal, $metric, $progressData);

            // Invalider le cache
            $this->cache->invalidateTags(['progress_cache', "goal_{$goal->getId()}_progress"]);

            $jsonData = $this->serializer->serialize($progress, 'json', ['groups' => ['progress']]);
            $location = $this->urlGenerator->generate('api_progress_get', ['id' => $progress->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

            return new JsonResponse($jsonData, Response::HTTP_CREATED, ['Location' => $location], true);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'api_progress_update', methods: ['PATCH'])]
    #[OA\RequestBody(
        description: 'Données à mettre à jour',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'value', type: 'number'),
                new OA\Property(property: 'notes', type: 'string'),
                new OA\Property(property: 'difficulty_rating', type: 'integer', minimum: 1, maximum: 10),
                new OA\Property(property: 'satisfaction_rating', type: 'integer', minimum: 1, maximum: 10),
                new OA\Property(property: 'metadata', type: 'object')
            ]
        )
    )]
    public function update(Progress $progress, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $progress);

        $data = $request->toArray();

        try {
            $this->progressService->updateProgress($progress, $data);

            // Invalider le cache
            $this->cache->invalidateTags(['progress_cache', "progress_{$progress->getId()}"]);

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'api_progress_delete', methods: ['DELETE'])]
    public function delete(Progress $progress): JsonResponse
    {
        $this->denyAccessUnlessGranted('delete', $progress);

        try {
            $this->progressService->deleteProgress($progress);

            // Invalider le cache
            $this->cache->invalidateTags(['progress_cache', "progress_{$progress->getId()}"]);

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/bulk', name: 'api_progress_bulk_create', methods: ['POST'])]
    #[OA\RequestBody(
        description: 'Créer plusieurs progressions en une fois',
        required: true,
        content: new OA\JsonContent(
            required: ['goal_id', 'progressions'],
            properties: [
                new OA\Property(property: 'goal_id', type: 'integer'),
                new OA\Property(
                    property: 'progressions',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'metric_id', type: 'integer'),
                            new OA\Property(property: 'value', type: 'number'),
                            new OA\Property(property: 'notes', type: 'string')
                        ]
                    )
                )
            ]
        )
    )]
    public function bulkCreate(Request $request): JsonResponse
    {
        $data = $request->toArray();

        if (!isset($data['goal_id'], $data['progressions'])) {
            return new JsonResponse(['error' => 'Données manquantes'], Response::HTTP_BAD_REQUEST);
        }

        $goal = $this->goalRepository->find($data['goal_id']);
        if (!$goal || $goal->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Objectif non trouvé'], Response::HTTP_NOT_FOUND);
        }

        try {
            $progressEntries = $this->progressService->recordMultipleProgress($goal, $data['progressions']);

            // Invalider le cache
            $this->cache->invalidateTags(['progress_cache', "goal_{$goal->getId()}_progress"]);

            $jsonData = $this->serializer->serialize($progressEntries, 'json', ['groups' => ['progress']]);

            return new JsonResponse($jsonData, Response::HTTP_CREATED, [], true);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/import-csv', name: 'api_progress_import_csv', methods: ['POST'])]
    #[OA\RequestBody(
        description: 'Importer des progressions depuis un fichier CSV',
        required: true,
        content: new OA\JsonContent(
            required: ['goal_id', 'csv_content'],
            properties: [
                new OA\Property(property: 'goal_id', type: 'integer'),
                new OA\Property(property: 'csv_content', type: 'string', description: 'Contenu du fichier CSV')
            ]
        )
    )]
    public function importCsv(Request $request): JsonResponse
    {
        $data = $request->toArray();

        if (!isset($data['goal_id'], $data['csv_content'])) {
            return new JsonResponse(['error' => 'Données manquantes'], Response::HTTP_BAD_REQUEST);
        }

        $goal = $this->goalRepository->find($data['goal_id']);
        if (!$goal || $goal->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Objectif non trouvé'], Response::HTTP_NOT_FOUND);
        }

        try {
            $importResult = $this->progressService->importProgressFromCsv($goal, $data['csv_content']);

            // Invalider le cache
            $this->cache->invalidateTags(['progress_cache', "goal_{$goal->getId()}_progress"]);

            return new JsonResponse($importResult);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/today', name: 'api_progress_today', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Progressions d\'aujourd\'hui pour l\'utilisateur',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Progress::class, groups: ['progress', 'dashboard']))
        )
    )]
    public function today(): JsonResponse
    {
        $user = $this->getUser();

        $cacheKey = "progress_today_{$user->getId()}_" . date('Y-m-d');

        $todayProgress = $this->cache->get($cacheKey, function() use ($user) {
            return $this->progressRepository->getTodayProgress($user);
        });

        $jsonData = $this->serializer->serialize($todayProgress, 'json', ['groups' => ['progress', 'dashboard']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/week', name: 'api_progress_week', methods: ['GET'])]
    #[OA\Parameter(name: 'week_start', description: 'Date de début de semaine (YYYY-MM-DD)', in: 'query', required: false)]
    public function week(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $weekStart = $request->query->get('week_start');

        $weekStartDate = $weekStart ? new \DateTime($weekStart) : null;

        $weekProgress = $this->progressRepository->getWeekProgress($user, $weekStartDate);

        $jsonData = $this->serializer->serialize($weekProgress, 'json', ['groups' => ['progress', 'dashboard']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/statistics', name: 'api_progress_statistics', methods: ['GET'])]
    #[OA\Parameter(name: 'days', description: 'Nombre de jours à analyser', in: 'query', required: false)]
    #[OA\Response(
        response: 200,
        description: 'Statistiques de progression de l\'utilisateur',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'period_stats', type: 'object'),
                new OA\Property(property: 'streak_info', type: 'object'),
                new OA\Property(property: 'today', type: 'object'),
                new OA\Property(property: 'week', type: 'object'),
                new OA\Property(property: 'performance_trends', type: 'array')
            ]
        )
    )]
    public function statistics(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $days = $request->query->getInt('days', 30);

        $cacheKey = "progress_stats_{$user->getId()}_{$days}";

        $statistics = $this->cache->get($cacheKey, function() use ($user, $days) {
            return $this->progressService->getUserProgressStatistics($user, $days);
        });

        return new JsonResponse($statistics);
    }

    #[Route('/chart-data/{goalId}', name: 'api_progress_chart_data', methods: ['GET'])]
    #[OA\Parameter(name: 'chart_type', description: 'Type de graphique', in: 'query', required: false)]
    #[OA\Parameter(name: 'days', description: 'Nombre de jours', in: 'query', required: false)]
    #[OA\Parameter(name: 'metrics', description: 'IDs des métriques (séparés par virgule)', in: 'query', required: false)]
    public function chartData(int $goalId, Request $request): JsonResponse
    {
        $goal = $this->goalRepository->find($goalId);
        if (!$goal || $goal->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Objectif non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $chartType = $request->query->get('chart_type', 'line');
        $days = $request->query->getInt('days', 30);
        $metricsParam = $request->query->get('metrics');

        $options = ['days' => $days];
        if ($metricsParam) {
            $options['metrics'] = explode(',', $metricsParam);
        }

        $cacheKey = "chart_data_{$goalId}_{$chartType}_{$days}_" . md5($metricsParam ?: '');

        $chartData = $this->cache->get($cacheKey, function() use ($goal, $chartType, $options) {
            return $this->progressService->generateChartData($goal, $chartType, $options);
        });

        return new JsonResponse($chartData);
    }

    #[Route('/predictions/{metricId}', name: 'api_progress_predictions', methods: ['GET'])]
    #[OA\Parameter(name: 'days_ahead', description: 'Nombre de jours à prédire', in: 'query', required: false)]
    public function predictions(int $metricId, Request $request): JsonResponse
    {
        $metric = $this->metricRepository->find($metricId);
        if (!$metric || $metric->getGoal()->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Métrique non trouvée'], Response::HTTP_NOT_FOUND);
        }

        $daysAhead = $request->query->getInt('days_ahead', 7);

        $predictions = $this->progressService->predictNextValues($metric, $daysAhead);

        return new JsonResponse($predictions);
    }

    #[Route('/patterns/{goalId}', name: 'api_progress_patterns', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Analyse des patterns de progression',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'weekly_patterns', type: 'object'),
                new OA\Property(property: 'best_day', type: 'string'),
                new OA\Property(property: 'time_patterns', type: 'object'),
                new OA\Property(property: 'consistency_score', type: 'number'),
                new OA\Property(property: 'recommendations', type: 'array')
            ]
        )
    )]
    public function patterns(int $goalId): JsonResponse
    {
        $goal = $this->goalRepository->find($goalId);
        if (!$goal || $goal->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Objectif non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $cacheKey = "progress_patterns_{$goalId}";

        $patterns = $this->cache->get($cacheKey, function() use ($goal) {
            return $this->progressService->analyzeProgressPatterns($goal);
        });

        return new JsonResponse($patterns);
    }

    #[Route('/compare/{goalId}', name: 'api_progress_compare', methods: ['GET'])]
    #[OA\Parameter(name: 'period1_start', description: 'Début période 1 (YYYY-MM-DD)', in: 'query', required: true)]
    #[OA\Parameter(name: 'period1_end', description: 'Fin période 1 (YYYY-MM-DD)', in: 'query', required: true)]
    #[OA\Parameter(name: 'period2_start', description: 'Début période 2 (YYYY-MM-DD)', in: 'query', required: true)]
    #[OA\Parameter(name: 'period2_end', description: 'Fin période 2 (YYYY-MM-DD)', in: 'query', required: true)]
    public function compare(int $goalId, Request $request): JsonResponse
    {
        $goal = $this->goalRepository->find($goalId);
        if (!$goal || $goal->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Objectif non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $period1Start = $request->query->get('period1_start');
        $period1End = $request->query->get('period1_end');
        $period2Start = $request->query->get('period2_start');
        $period2End = $request->query->get('period2_end');

        if (!$period1Start || !$period1End || !$period2Start || !$period2End) {
            return new JsonResponse(['error' => 'Toutes les dates sont requises'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $comparison = $this->progressService->comparePerformancePeriods(
                $goal,
                new \DateTime($period1Start),
                new \DateTime($period1End),
                new \DateTime($period2Start),
                new \DateTime($period2End)
            );

            return new JsonResponse($comparison);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Dates invalides'], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/search', name: 'api_progress_search', methods: ['GET'])]
    #[OA\Parameter(name: 'goal_id', description: 'ID de l\'objectif', in: 'query', required: true)]
    #[OA\Parameter(name: 'metric_id', description: 'ID de la métrique', in: 'query', required: false)]
    #[OA\Parameter(name: 'min_value', description: 'Valeur minimale', in: 'query', required: false)]
    #[OA\Parameter(name: 'max_value', description: 'Valeur maximale', in: 'query', required: false)]
    #[OA\Parameter(name: 'start_date', description: 'Date de début', in: 'query', required: false)]
    #[OA\Parameter(name: 'end_date', description: 'Date de fin', in: 'query', required: false)]
    public function search(Request $request): JsonResponse
    {
        $goalId = $request->query->get('goal_id');
        if (!$goalId) {
            return new JsonResponse(['error' => 'goal_id est requis'], Response::HTTP_BAD_REQUEST);
        }

        $goal = $this->goalRepository->find($goalId);
        if (!$goal || $goal->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Objectif non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $filters = [];
        if ($metricId = $request->query->get('metric_id')) {
            $filters['metric_id'] = $metricId;
        }
        if ($minValue = $request->query->get('min_value')) {
            $filters['min_value'] = floatval($minValue);
        }
        if ($maxValue = $request->query->get('max_value')) {
            $filters['max_value'] = floatval($maxValue);
        }
        if ($startDate = $request->query->get('start_date')) {
            $filters['start_date'] = new \DateTime($startDate);
        }
        if ($endDate = $request->query->get('end_date')) {
            $filters['end_date'] = new \DateTime($endDate);
        }

        $progressEntries = $this->progressRepository->searchProgress($goal, $filters);

        $jsonData = $this->serializer->serialize($progressEntries, 'json', ['groups' => ['progress']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/export/{goalId}', name: 'api_progress_export', methods: ['GET'])]
    #[OA\Parameter(name: 'format', description: 'Format d\'export (json, csv)', in: 'query', required: false)]
    #[OA\Parameter(name: 'start_date', description: 'Date de début', in: 'query', required: false)]
    #[OA\Parameter(name: 'end_date', description: 'Date de fin', in: 'query', required: false)]
    public function export(int $goalId, Request $request): JsonResponse
    {
        $goal = $this->goalRepository->find($goalId);
        if (!$goal || $goal->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Objectif non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $format = $request->query->get('format', 'json');
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        if ($startDate && $endDate) {
            $progressEntries = $this->progressRepository->findByGoalAndPeriod(
                $goal,
                new \DateTime($startDate),
                new \DateTime($endDate)
            );
        } else {
            $progressEntries = $this->progressRepository->findByGoal($goal);
        }

        if ($format === 'csv') {
            // TODO: Implémenter l'export CSV
            return new JsonResponse(['error' => 'Export CSV non encore implémenté'], Response::HTTP_NOT_IMPLEMENTED);
        }

        $jsonData = $this->serializer->serialize($progressEntries, 'json', ['groups' => ['progress']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }
}
