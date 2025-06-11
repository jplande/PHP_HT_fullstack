<?php

namespace App\Controller;

use App\Entity\Session;
use App\Entity\Goal;
use App\Service\SessionService;
use App\Repository\SessionRepository;
use App\Repository\GoalRepository;
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

#[Route('/api/v1/sessions')]
#[OA\Tag(name: 'Sessions')]
final class SessionController extends AbstractController
{
    public function __construct(
        private SessionService $sessionService,
        private SessionRepository $sessionRepository,
        private GoalRepository $goalRepository,
        private SerializerInterface $serializer,
        private UrlGeneratorInterface $urlGenerator,
        private TagAwareCacheInterface $cache
    ) {}

    #[Route('', name: 'api_sessions_list', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Liste des sessions de l\'utilisateur',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Session::class, groups: ['session']))
        )
    )]
    #[OA\Parameter(name: 'goal_id', description: 'Filtrer par objectif', in: 'query', required: false)]
    #[OA\Parameter(name: 'completed', description: 'Filtrer par statut de completion', in: 'query', required: false)]
    #[OA\Parameter(name: 'limit', description: 'Nombre de sessions à retourner', in: 'query', required: false)]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $goalId = $request->query->get('goal_id');
        $completed = $request->query->get('completed');
        $limit = $request->query->getInt('limit', 50);

        if ($goalId) {
            $goal = $this->goalRepository->find($goalId);
            if (!$goal || $goal->getUser() !== $user) {
                return new JsonResponse(['error' => 'Objectif non trouvé'], Response::HTTP_NOT_FOUND);
            }
            $sessions = $this->sessionRepository->findByGoal($goal);
        } else {
            if ($completed === 'false') {
                $sessions = $this->sessionRepository->findInProgressByUser($user);
            } else {
                $sessions = $this->sessionRepository->findByUser($user, $limit);
            }
        }

        $jsonData = $this->serializer->serialize($sessions, 'json', ['groups' => ['session']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'api_sessions_get', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Détails d\'une session',
        content: new OA\JsonContent(ref: new Model(type: Session::class, groups: ['session']))
    )]
    public function get(Session $session): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $session);

        $jsonData = $this->serializer->serialize($session, 'json', ['groups' => ['session']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('', name: 'api_sessions_start', methods: ['POST'])]
    #[OA\RequestBody(
        description: 'Données pour démarrer une session',
        required: true,
        content: new OA\JsonContent(
            required: ['goal_id'],
            properties: [
                new OA\Property(property: 'goal_id', type: 'integer', example: 1),
                new OA\Property(property: 'location', type: 'string', example: 'Salle de sport'),
                new OA\Property(property: 'notes', type: 'string', example: 'Session matinale'),
                new OA\Property(property: 'start_time', type: 'string', format: 'datetime', example: '2025-06-10T08:00:00'),
                new OA\Property(property: 'session_data', type: 'object', example: ['warmup_duration' => 300])
            ]
        )
    )]
    public function start(Request $request): JsonResponse
    {
        $data = $request->toArray();

        if (!isset($data['goal_id'])) {
            return new JsonResponse(['error' => 'goal_id est requis'], Response::HTTP_BAD_REQUEST);
        }

        $goal = $this->goalRepository->find($data['goal_id']);
        if (!$goal || $goal->getUser() !== $this->getUser()) {
            return new JsonResponse(['error' => 'Objectif non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Préparer les données de session
        $sessionData = [
            'location' => $data['location'] ?? null,
            'notes' => $data['notes'] ?? null,
            'sessionData' => $data['session_data'] ?? null
        ];

        if (isset($data['start_time'])) {
            $sessionData['startTime'] = new \DateTime($data['start_time']);
        }

        try {
            $session = $this->sessionService->startSession($goal, $sessionData);

            // Invalider le cache
            $this->cache->invalidateTags(['sessions_cache', "user_{$this->getUser()->getId()}_sessions"]);

            $jsonData = $this->serializer->serialize($session, 'json', ['groups' => ['session']]);
            $location = $this->urlGenerator->generate('api_sessions_get', ['id' => $session->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

            return new JsonResponse($jsonData, Response::HTTP_CREATED, ['Location' => $location], true);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/complete', name: 'api_sessions_complete', methods: ['PATCH'])]
    #[OA\RequestBody(
        description: 'Données pour terminer une session',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'end_time', type: 'string', format: 'datetime'),
                new OA\Property(property: 'intensity_rating', type: 'integer', minimum: 1, maximum: 10),
                new OA\Property(property: 'satisfaction_rating', type: 'integer', minimum: 1, maximum: 10),
                new OA\Property(property: 'difficulty_rating', type: 'integer', minimum: 1, maximum: 10),
                new OA\Property(property: 'notes', type: 'string'),
                new OA\Property(
                    property: 'progressions',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'metric', type: 'object'),
                            new OA\Property(property: 'value', type: 'number'),
                            new OA\Property(property: 'notes', type: 'string')
                        ]
                    )
                )
            ]
        )
    )]
    public function complete(Session $session, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $session);

        if ($session->getCompleted()) {
            return new JsonResponse(['error' => 'Session déjà terminée'], Response::HTTP_BAD_REQUEST);
        }

        $data = $request->toArray();

        // Préparer les données de completion
        $completionData = [];

        if (isset($data['end_time'])) {
            $completionData['endTime'] = new \DateTime($data['end_time']);
        }

        if (isset($data['intensity_rating'])) {
            $completionData['intensityRating'] = $data['intensity_rating'];
        }

        if (isset($data['satisfaction_rating'])) {
            $completionData['satisfactionRating'] = $data['satisfaction_rating'];
        }

        if (isset($data['difficulty_rating'])) {
            $completionData['difficultyRating'] = $data['difficulty_rating'];
        }

        if (isset($data['notes'])) {
            $completionData['notes'] = $data['notes'];
        }

        if (isset($data['progressions'])) {
            $completionData['progressions'] = $data['progressions'];
        }

        try {
            $this->sessionService->completeSession($session, $completionData);

            // Invalider le cache
            $this->cache->invalidateTags(['sessions_cache', "session_{$session->getId()}"]);

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'api_sessions_update', methods: ['PATCH'])]
    #[OA\RequestBody(
        description: 'Données à mettre à jour',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'notes', type: 'string'),
                new OA\Property(property: 'location', type: 'string'),
                new OA\Property(property: 'session_data', type: 'object')
            ]
        )
    )]
    public function update(Session $session, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $session);

        $data = $request->toArray();

        try {
            $this->sessionService->updateSession($session, $data);

            // Invalider le cache
            $this->cache->invalidateTags(['sessions_cache', "session_{$session->getId()}"]);

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/abandon', name: 'api_sessions_abandon', methods: ['PATCH'])]
    #[OA\RequestBody(
        description: 'Raison d\'abandon',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'reason', type: 'string', example: 'Blessure')
            ]
        )
    )]
    public function abandon(Session $session, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $session);

        if ($session->getCompleted()) {
            return new JsonResponse(['error' => 'Session déjà terminée'], Response::HTTP_BAD_REQUEST);
        }

        $data = $request->toArray();
        $reason = $data['reason'] ?? null;

        try {
            $this->sessionService->abandonSession($session, $reason);

            // Invalider le cache
            $this->cache->invalidateTags(['sessions_cache', "session_{$session->getId()}"]);

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/statistics', name: 'api_sessions_statistics', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Statistiques détaillées de la session',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'duration_minutes', type: 'integer'),
                new OA\Property(property: 'progress_entries', type: 'integer'),
                new OA\Property(property: 'metrics_updated', type: 'integer'),
                new OA\Property(property: 'average_ratings', type: 'number'),
                new OA\Property(property: 'completion_impact', type: 'number'),
                new OA\Property(property: 'performance', type: 'object')
            ]
        )
    )]
    public function statistics(Session $session): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $session);

        $cacheKey = "session_stats_{$session->getId()}";

        $statistics = $this->cache->get($cacheKey, function() use ($session) {
            return $this->sessionService->getSessionStatistics($session);
        });

        return new JsonResponse($statistics);
    }

    #[Route('/in-progress', name: 'api_sessions_in_progress', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Sessions en cours pour l\'utilisateur',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Session::class, groups: ['session', 'dashboard']))
        )
    )]
    public function inProgress(): JsonResponse
    {
        $user = $this->getUser();

        $sessions = $this->sessionRepository->findInProgressByUser($user);

        $jsonData = $this->serializer->serialize($sessions, 'json', ['groups' => ['session', 'dashboard']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/today', name: 'api_sessions_today', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Sessions d\'aujourd\'hui pour l\'utilisateur',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Session::class, groups: ['session', 'dashboard']))
        )
    )]
    public function today(): JsonResponse
    {
        $user = $this->getUser();

        $cacheKey = "sessions_today_{$user->getId()}_" . date('Y-m-d');

        $sessions = $this->cache->get($cacheKey, function() use ($user) {
            return $this->sessionRepository->findTodayByUser($user);
        });

        $jsonData = $this->serializer->serialize($sessions, 'json', ['groups' => ['session', 'dashboard']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/week', name: 'api_sessions_week', methods: ['GET'])]
    #[OA\Parameter(name: 'week_start', description: 'Date de début de semaine (YYYY-MM-DD)', in: 'query', required: false)]
    public function week(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $weekStart = $request->query->get('week_start');

        $weekStartDate = $weekStart ? new \DateTime($weekStart) : null;

        $sessions = $this->sessionRepository->findWeekByUser($user, $weekStartDate);

        $jsonData = $this->serializer->serialize($sessions, 'json', ['groups' => ['session', 'dashboard']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/statistics', name: 'api_sessions_user_statistics', methods: ['GET'])]
    #[OA\Parameter(name: 'days', description: 'Nombre de jours à analyser', in: 'query', required: false)]
    #[OA\Response(
        response: 200,
        description: 'Statistiques des sessions de l\'utilisateur',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'total_sessions', type: 'integer'),
                new OA\Property(property: 'completed_sessions', type: 'integer'),
                new OA\Property(property: 'avg_duration', type: 'number'),
                new OA\Property(property: 'total_duration', type: 'integer'),
                new OA\Property(property: 'avg_satisfaction', type: 'number'),
                new OA\Property(property: 'avg_intensity', type: 'number'),
                new OA\Property(property: 'avg_difficulty', type: 'number')
            ]
        )
    )]
    public function userStatistics(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $days = $request->query->getInt('days', 30);

        $cacheKey = "user_session_stats_{$user->getId()}_{$days}";

        $statistics = $this->cache->get($cacheKey, function() use ($user, $days) {
            return $this->sessionRepository->getStatsForUser($user, $days);
        });

        return new JsonResponse($statistics);
    }

    #[Route('/patterns', name: 'api_sessions_patterns', methods: ['GET'])]
    #[OA\Parameter(name: 'days', description: 'Nombre de jours à analyser', in: 'query', required: false)]
    #[OA\Response(
        response: 200,
        description: 'Analyse des patterns de sessions',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'total_sessions', type: 'integer'),
                new OA\Property(property: 'average_duration', type: 'number'),
                new OA\Property(property: 'preferred_hours', type: 'array'),
                new OA\Property(property: 'preferred_days', type: 'array'),
                new OA\Property(property: 'performance_trends', type: 'object'),
                new OA\Property(property: 'consistency_score', type: 'number'),
                new OA\Property(property: 'recommendations', type: 'array')
            ]
        )
    )]
    public function patterns(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $days = $request->query->getInt('days', 30);

        $cacheKey = "session_patterns_{$user->getId()}_{$days}";

        $patterns = $this->cache->get($cacheKey, function() use ($user, $days) {
            return $this->sessionService->analyzeUserSessionPatterns($user, $days);
        });

        return new JsonResponse($patterns);
    }

    #[Route('/compare/{id1}/{id2}', name: 'api_sessions_compare', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Comparaison entre deux sessions',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'session1', type: 'object'),
                new OA\Property(property: 'session2', type: 'object'),
                new OA\Property(property: 'improvements', type: 'object'),
                new OA\Property(property: 'better_session', type: 'string')
            ]
        )
    )]
    public function compare(Session $session1, Session $session2): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $session1);
        $this->denyAccessUnlessGranted('view', $session2);

        $comparison = $this->sessionService->compareSessionPerformance($session1, $session2);

        return new JsonResponse($comparison);
    }

    #[Route('/plan', name: 'api_sessions_plan', methods: ['GET'])]
    #[OA\Parameter(name: 'goal_id', description: 'ID de l\'objectif', in: 'query', required: true)]
    #[OA\Parameter(name: 'days_ahead', description: 'Nombre de jours à planifier', in: 'query', required: false)]
    #[OA\Response(
        response: 200,
        description: 'Plan de sessions recommandées',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'date', type: 'string', format: 'date'),
                    new OA\Property(property: 'recommended_time', type: 'string'),
                    new OA\Property(property: 'estimated_duration', type: 'integer'),
                    new OA\Property(property: 'priority', type: 'string'),
                    new OA\Property(property: 'suggested_focus', type: 'string')
                ]
            )
        )
    )]
    public function plan(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $goalId = $request->query->get('goal_id');
        $daysAhead = $request->query->getInt('days_ahead', 7);

        if (!$goalId) {
            return new JsonResponse(['error' => 'goal_id est requis'], Response::HTTP_BAD_REQUEST);
        }

        $goal = $this->goalRepository->find($goalId);
        if (!$goal || $goal->getUser() !== $user) {
            return new JsonResponse(['error' => 'Objectif non trouvé'], Response::HTTP_NOT_FOUND);
        }

        $plan = $this->sessionService->generateSessionPlan($user, $goal, $daysAhead);

        return new JsonResponse($plan);
    }

    #[Route('/most-active-goals', name: 'api_sessions_most_active_goals', methods: ['GET'])]
    #[OA\Parameter(name: 'days', description: 'Nombre de jours à analyser', in: 'query', required: false)]
    #[OA\Parameter(name: 'limit', description: 'Nombre d\'objectifs à retourner', in: 'query', required: false)]
    public function mostActiveGoals(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $days = $request->query->getInt('days', 30);
        $limit = $request->query->getInt('limit', 5);

        $activeGoals = $this->sessionService->getMostActiveGoals($user, $days, $limit);

        return new JsonResponse($activeGoals);
    }

    #[Route('/longest', name: 'api_sessions_longest', methods: ['GET'])]
    #[OA\Parameter(name: 'limit', description: 'Nombre de sessions à retourner', in: 'query', required: false)]
    public function longest(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $limit = $request->query->getInt('limit', 10);

        $longestSessions = $this->sessionRepository->findLongestSessions($user, $limit);

        $jsonData = $this->serializer->serialize($longestSessions, 'json', ['groups' => ['session']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/by-location', name: 'api_sessions_by_location', methods: ['GET'])]
    #[OA\Parameter(name: 'location', description: 'Nom du lieu', in: 'query', required: true)]
    public function byLocation(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $location = $request->query->get('location');

        if (!$location) {
            return new JsonResponse(['error' => 'location est requis'], Response::HTTP_BAD_REQUEST);
        }

        $sessions = $this->sessionRepository->findByLocation($user, $location);

        $jsonData = $this->serializer->serialize($sessions, 'json', ['groups' => ['session']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/popular-locations', name: 'api_sessions_popular_locations', methods: ['GET'])]
    #[OA\Parameter(name: 'limit', description: 'Nombre de lieux à retourner', in: 'query', required: false)]
    public function popularLocations(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $limit = $request->query->getInt('limit', 10);

        $locations = $this->sessionRepository->getPopularLocations($user, $limit);

        return new JsonResponse($locations);
    }

    #[Route('/hourly-performance', name: 'api_sessions_hourly_performance', methods: ['GET'])]
    #[OA\Parameter(name: 'days', description: 'Nombre de jours à analyser', in: 'query', required: false)]
    public function hourlyPerformance(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $days = $request->query->getInt('days', 30);

        $cacheKey = "hourly_performance_{$user->getId()}_{$days}";

        $performance = $this->cache->get($cacheKey, function() use ($user, $days) {
            return $this->sessionRepository->getHourlyPerformance($user, $days);
        });

        return new JsonResponse($performance);
    }

    #[Route('/with-notes', name: 'api_sessions_with_notes', methods: ['GET'])]
    #[OA\Parameter(name: 'goal_id', description: 'ID de l\'objectif', in: 'query', required: false)]
    #[OA\Parameter(name: 'limit', description: 'Nombre de sessions à retourner', in: 'query', required: false)]
    public function withNotes(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $goalId = $request->query->get('goal_id');
        $limit = $request->query->getInt('limit', 10);

        if ($goalId) {
            $goal = $this->goalRepository->find($goalId);
            if (!$goal || $goal->getUser() !== $user) {
                return new JsonResponse(['error' => 'Objectif non trouvé'], Response::HTTP_NOT_FOUND);
            }
            $sessions = $this->sessionRepository->findWithNotes($goal, $limit);
        } else {
            // TODO: Ajouter une méthode pour trouver toutes les sessions avec notes d'un utilisateur
            $sessions = [];
        }

        $jsonData = $this->serializer->serialize($sessions, 'json', ['groups' => ['session']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/search', name: 'api_sessions_search', methods: ['GET'])]
    #[OA\Parameter(name: 'goal_id', description: 'ID de l\'objectif', in: 'query', required: false)]
    #[OA\Parameter(name: 'completed', description: 'Sessions complétées (true/false)', in: 'query', required: false)]
    #[OA\Parameter(name: 'location', description: 'Lieu de la session', in: 'query', required: false)]
    #[OA\Parameter(name: 'start_date', description: 'Date de début', in: 'query', required: false)]
    #[OA\Parameter(name: 'end_date', description: 'Date de fin', in: 'query', required: false)]
    #[OA\Parameter(name: 'min_duration', description: 'Durée minimale en secondes', in: 'query', required: false)]
    public function search(Request $request): JsonResponse
    {
        $user = $this->getUser();

        $filters = [];

        if ($goalId = $request->query->get('goal_id')) {
            $filters['goal_id'] = $goalId;
        }

        if ($completed = $request->query->get('completed')) {
            $filters['completed'] = $completed === 'true';
        }

        if ($location = $request->query->get('location')) {
            $filters['location'] = $location;
        }

        if ($startDate = $request->query->get('start_date')) {
            $filters['start_date'] = new \DateTime($startDate);
        }

        if ($endDate = $request->query->get('end_date')) {
            $filters['end_date'] = new \DateTime($endDate);
        }

        if ($minDuration = $request->query->get('min_duration')) {
            $filters['min_duration'] = intval($minDuration);
        }

        $sessions = $this->sessionRepository->searchSessions($user, $filters);

        $jsonData = $this->serializer->serialize($sessions, 'json', ['groups' => ['session']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/export', name: 'api_sessions_export', methods: ['GET'])]
    #[OA\Parameter(name: 'format', description: 'Format d\'export (json, csv)', in: 'query', required: false)]
    #[OA\Parameter(name: 'start_date', description: 'Date de début', in: 'query', required: false)]
    #[OA\Parameter(name: 'end_date', description: 'Date de fin', in: 'query', required: false)]
    public function export(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $format = $request->query->get('format', 'json');
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        if ($startDate && $endDate) {
            $sessions = $this->sessionRepository->findCompletedInPeriod(
                $user,
                new \DateTime($startDate),
                new \DateTime($endDate)
            );
        } else {
            $sessions = $this->sessionRepository->findByUser($user);
        }

        if ($format === 'csv') {
            try {
                $csvContent = $this->sessionService->exportSessionsToCSV(
                    $user,
                    $startDate ? new \DateTime($startDate) : new \DateTime('-1 year'),
                    $endDate ? new \DateTime($endDate) : new \DateTime()
                );

                return new JsonResponse([
                    'content' => $csvContent,
                    'filename' => 'sessions_export_' . date('Y-m-d') . '.csv'
                ]);
            } catch (\Exception $e) {
                return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            }
        }

        $jsonData = $this->serializer->serialize($sessions, 'json', ['groups' => ['session']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }
}
