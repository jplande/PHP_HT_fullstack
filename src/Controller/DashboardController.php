<?php

namespace App\Controller;

use App\Service\AnalyticsService;
use App\Service\AchievementService;
use App\Service\ProgressService;
use App\Repository\GoalRepository;
use App\Repository\ProgressRepository;
use App\Repository\SessionRepository;
use App\Repository\UserAchievementRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/api/v1/dashboard')]
#[OA\Tag(name: 'Dashboard')]
final class DashboardController extends AbstractController
{
    public function __construct(
        private AnalyticsService $analyticsService,
        private AchievementService $achievementService,
        private ProgressService $progressService,
        private GoalRepository $goalRepository,
        private ProgressRepository $progressRepository,
        private SessionRepository $sessionRepository,
        private UserAchievementRepository $userAchievementRepository,
        private SerializerInterface $serializer,
        private TagAwareCacheInterface $cache
    ) {}

    #[Route('', name: 'api_dashboard_main', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Dashboard principal avec toutes les données importantes',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'user_overview', type: 'object'),
                new OA\Property(property: 'goals_summary', type: 'object'),
                new OA\Property(property: 'progress_today', type: 'array'),
                new OA\Property(property: 'achievements_summary', type: 'object'),
                new OA\Property(property: 'analytics', type: 'object'),
                new OA\Property(property: 'quick_actions', type: 'array')
            ]
        )
    )]
    public function main(): JsonResponse
    {
        $user = $this->getUser();

        $cacheKey = "dashboard_main_{$user->getId()}_" . date('Y-m-d-H');

        $dashboardData = $this->cache->get($cacheKey, function() use ($user) {
            // Vue d'ensemble utilisateur
            $userOverview = [
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'full_name' => $user->getFullName(),
                    'level' => $user->getLevel(),
                    'total_points' => $user->getTotalPoints(),
                    'current_streak' => $user->getCurrentStreak(),
                    'longest_streak' => $user->getLongestStreak(),
                    'rank' => $user->getRank()
                ],
                'level_progress' => [
                    'current_level' => $user->getLevel(),
                    'points_to_next_level' => $user->getPointsToNextLevel(),
                    'level_progress_percentage' => $user->getLevelProgressPercentage()
                ]
            ];

            // Résumé des objectifs
            $activeGoals = $this->goalRepository->findActiveByUser($user);
            $goalStats = $this->goalRepository->getStatsForUser($user);

            $goalsSummary = [
                'stats' => $goalStats,
                'active_goals' => array_slice($activeGoals, 0, 5), // Top 5
                'ending_soon' => $this->goalRepository->findEndingSoon($user, 7),
                'needing_update' => $this->goalRepository->findNeedingUpdate($user, 3)
            ];

            // Progressions d'aujourd'hui
            $todayProgress = $this->progressRepository->getTodayProgress($user);

            // Résumé des badges
            $achievementStats = $this->achievementService->getUserAchievementStats($user);
            $recentAchievements = $this->userAchievementRepository->findRecentByUser($user, 7);
            $unnotifiedAchievements = $this->achievementService->getUnnotifiedAchievements($user);

            $achievementsSummary = [
                'stats' => $achievementStats,
                'recent' => $recentAchievements,
                'unnotified_count' => count($unnotifiedAchievements),
                'recommendations' => $this->achievementService->getRecommendedAchievements($user, 3)
            ];

            // Analytics globales
            $analytics = $this->analyticsService->getDashboardAnalytics($user, 30);

            // Actions rapides suggérées
            $quickActions = $this->generateQuickActions($user, $activeGoals, $todayProgress);

            return [
                'user_overview' => $userOverview,
                'goals_summary' => $goalsSummary,
                'progress_today' => $todayProgress,
                'achievements_summary' => $achievementsSummary,
                'analytics' => $analytics,
                'quick_actions' => $quickActions
            ];
        });

        $jsonData = $this->serializer->serialize($dashboardData, 'json', ['groups' => ['dashboard', 'goal', 'progress', 'user_achievement']]);

        return new JsonResponse($jsonData, Response::HTTP_OK, [], true);
    }

    #[Route('/overview', name: 'api_dashboard_overview', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Vue d\'ensemble rapide pour widgets',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'goals_count', type: 'integer'),
                new OA\Property(property: 'progress_today', type: 'integer'),
                new OA\Property(property: 'current_streak', type: 'integer'),
                new OA\Property(property: 'achievements_count', type: 'integer'),
                new OA\Property(property: 'level', type: 'integer'),
                new OA\Property(property: 'total_points', type: 'integer')
            ]
        )
    )]
    public function overview(): JsonResponse
    {
        $user = $this->getUser();

        $cacheKey = "dashboard_overview_{$user->getId()}_" . date('Y-m-d');

        $overview = $this->cache->get($cacheKey, function() use ($user) {
            $activeGoals = $this->goalRepository->findActiveByUser($user);
            $todayProgress = $this->progressRepository->getTodayProgress($user);
            $userAchievements = $this->userAchievementRepository->findByUser($user);

            return [
                'goals_count' => count($activeGoals),
                'progress_today' => count($todayProgress),
                'current_streak' => $user->getCurrentStreak(),
                'achievements_count' => count($userAchievements),
                'level' => $user->getLevel(),
                'total_points' => $user->getTotalPoints()
            ];
        });

        return new JsonResponse($overview);
    }

    #[Route('/statistics', name: 'api_dashboard_statistics', methods: ['GET'])]
    #[OA\Parameter(name: 'period', description: 'Période d\'analyse (7d, 30d, 90d)', in: 'query', required: false)]
    #[OA\Response(
        response: 200,
        description: 'Statistiques détaillées pour le dashboard',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'goals_statistics', type: 'object'),
                new OA\Property(property: 'progress_statistics', type: 'object'),
                new OA\Property(property: 'session_statistics', type: 'object'),
                new OA\Property(property: 'performance_score', type: 'integer'),
                new OA\Property(property: 'trends', type: 'object')
            ]
        )
    )]
    public function statistics(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $period = $request->query->get('period', '30d');

        $days = match($period) {
            '7d' => 7,
            '90d' => 90,
            default => 30
        };

        $cacheKey = "dashboard_statistics_{$user->getId()}_{$period}";

        $statistics = $this->cache->get($cacheKey, function() use ($user, $days) {
            return [
                'goals_statistics' => $this->goalRepository->getStatsForUser($user),
                'progress_statistics' => $this->progressService->getUserProgressStatistics($user, $days),
                'session_statistics' => $this->sessionRepository->getStatsForUser($user, $days),
                'performance_score' => $this->calculatePerformanceScore($user, $days),
                'trends' => $this->calculateTrends($user, $days)
            ];
        });

        return new JsonResponse($statistics);
    }

    #[Route('/activity-feed', name: 'api_dashboard_activity_feed', methods: ['GET'])]
    #[OA\Parameter(name: 'limit', description: 'Nombre d\'activités à retourner', in: 'query', required: false)]
    #[OA\Response(
        response: 200,
        description: 'Flux d\'activité récente de l\'utilisateur',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'type', type: 'string'),
                    new OA\Property(property: 'title', type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'date', type: 'string', format: 'datetime'),
                    new OA\Property(property: 'icon', type: 'string'),
                    new OA\Property(property: 'data', type: 'object')
                ]
            )
        )
    )]
    public function activityFeed(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $limit = $request->query->getInt('limit', 20);

        $cacheKey = "dashboard_activity_feed_{$user->getId()}_{$limit}";

        $activities = $this->cache->get($cacheKey, function() use ($user, $limit) {
            $activities = [];

            // Progressions récentes
            $recentProgress = $this->progressRepository->getTodayProgress($user);
            foreach (array_slice($recentProgress, 0, 5) as $progress) {
                $activities[] = [
                    'type' => 'progress',
                    'title' => 'Progression enregistrée',
                    'description' => "Vous avez enregistré {$progress->getFormattedValue()} pour {$progress->getGoal()->getTitle()}",
                    'date' => $progress->getCreatedAt(),
                    'icon' => '📊',
                    'data' => ['progress_id' => $progress->getId()]
                ];
            }

            // Badges récents
            $recentAchievements = $this->userAchievementRepository->findRecentByUser($user, 7);
            foreach ($recentAchievements as $userAchievement) {
                $activities[] = [
                    'type' => 'achievement',
                    'title' => 'Badge débloqué !',
                    'description' => "Vous avez obtenu le badge \"{$userAchievement->getAchievement()->getName()}\"",
                    'date' => $userAchievement->getUnlockedAt(),
                    'icon' => '🏆',
                    'data' => ['achievement_id' => $userAchievement->getAchievement()->getId()]
                ];
            }

            // Sessions récentes
            $recentSessions = $this->sessionRepository->findByUser($user, 5);
            foreach ($recentSessions as $session) {
                if ($session->getCompleted()) {
                    $activities[] = [
                        'type' => 'session',
                        'title' => 'Session terminée',
                        'description' => "Session de {$session->getFormattedDuration()} pour {$session->getGoal()->getTitle()}",
                        'date' => $session->getEndTime(),
                        'icon' => '🏃',
                        'data' => ['session_id' => $session->getId()]
                    ];
                }
            }

            // Objectifs créés récemment
            $recentGoals = $this->goalRepository->findRecentlyUpdated($user, 3);
            foreach ($recentGoals as $goal) {
                if ($goal->getCreatedAt() > new \DateTime('-7 days')) {
                    $activities[] = [
                        'type' => 'goal_created',
                        'title' => 'Nouvel objectif',
                        'description' => "Vous avez créé l'objectif \"{$goal->getTitle()}\"",
                        'date' => $goal->getCreatedAt(),
                        'icon' => '🎯',
                        'data' => ['goal_id' => $goal->getId()]
                    ];
                }
            }

            // Trier par date décroissante
            usort($activities, fn($a, $b) => $b['date'] <=> $a['date']);

            return array_slice($activities, 0, $limit);
        });

        return new JsonResponse($activities);
    }

    #[Route('/notifications', name: 'api_dashboard_notifications', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Notifications importantes pour l\'utilisateur',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'type', type: 'string'),
                    new OA\Property(property: 'priority', type: 'string'),
                    new OA\Property(property: 'title', type: 'string'),
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'action_url', type: 'string'),
                    new OA\Property(property: 'created_at', type: 'string', format: 'datetime')
                ]
            )
        )
    )]
    public function notifications(): JsonResponse
    {
        $user = $this->getUser();

        $notifications = [];

        // Badges non notifiés
        $unnotifiedAchievements = $this->achievementService->getUnnotifiedAchievements($user);
        if (!empty($unnotifiedAchievements)) {
            $count = count($unnotifiedAchievements);
            $notifications[] = [
                'type' => 'achievement',
                'priority' => 'high',
                'title' => 'Nouveaux badges !',
                'message' => "Vous avez débloqué {$count} nouveau(x) badge(s) !",
                'action_url' => '/achievements/unnotified',
                'created_at' => new \DateTime()
            ];
        }

        // Objectifs qui se terminent bientôt
        $endingGoals = $this->goalRepository->findEndingSoon($user, 7);
        if (!empty($endingGoals)) {
            $notifications[] = [
                'type' => 'deadline',
                'priority' => 'medium',
                'title' => 'Objectifs à terminer',
                'message' => count($endingGoals) . ' objectif(s) se terminent bientôt',
                'action_url' => '/goals?filter=ending_soon',
                'created_at' => new \DateTime()
            ];
        }

        // Objectifs nécessitant une mise à jour
        $needingUpdate = $this->goalRepository->findNeedingUpdate($user, 3);
        if (!empty($needingUpdate)) {
            $notifications[] = [
                'type' => 'reminder',
                'priority' => 'low',
                'title' => 'Objectifs en attente',
                'message' => count($needingUpdate) . ' objectif(s) n\'ont pas été mis à jour récemment',
                'action_url' => '/goals?filter=needing_update',
                'created_at' => new \DateTime()
            ];
        }

        // Série en danger
        if ($user->getCurrentStreak() > 7) {
            $todayProgress = $this->progressRepository->getTodayProgress($user);
            if (empty($todayProgress) && date('H') > 18) {
                $notifications[] = [
                    'type' => 'streak_warning',
                    'priority' => 'high',
                    'title' => 'Série en danger !',
                    'message' => "Votre série de {$user->getCurrentStreak()} jours risque d'être cassée",
                    'action_url' => '/progress/today',
                    'created_at' => new \DateTime()
                ];
            }
        }

        return new JsonResponse($notifications);
    }

    #[Route('/quick-stats', name: 'api_dashboard_quick_stats', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Statistiques rapides pour widgets',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'today', type: 'object'),
                new OA\Property(property: 'week', type: 'object'),
                new OA\Property(property: 'month', type: 'object'),
                new OA\Property(property: 'comparisons', type: 'object')
            ]
        )
    )]
    public function quickStats(): JsonResponse
    {
        $user = $this->getUser();

        $cacheKey = "dashboard_quick_stats_{$user->getId()}_" . date('Y-m-d');

        $quickStats = $this->cache->get($cacheKey, function() use ($user) {
            // Stats aujourd'hui
            $todayProgress = $this->progressRepository->getTodayProgress($user);
            $todaySessions = $this->sessionRepository->findTodayByUser($user);

            // Stats semaine
            $weekProgress = $this->progressRepository->getWeekProgress($user);
            $weekSessions = $this->sessionRepository->findWeekByUser($user);

            // Stats mois
            $monthStart = new \DateTime('first day of this month');
            $monthEnd = new \DateTime('last day of this month');
            $monthProgress = $this->progressRepository->findByPeriod($monthStart, $monthEnd);
            $monthSessions = $this->sessionRepository->findCompletedInPeriod($user, $monthStart, $monthEnd);

            // Comparaisons avec période précédente
            $lastWeekStart = new \DateTime('monday last week');
            $lastWeekEnd = new \DateTime('sunday last week');
            $lastWeekProgress = $this->progressRepository->findByPeriod($lastWeekStart, $lastWeekEnd);

            return [
                'today' => [
                    'progress_entries' => count($todayProgress),
                    'sessions' => count($todaySessions),
                    'goals_updated' => count(array_unique(array_map(fn($p) => $p->getGoal()->getId(), $todayProgress)))
                ],
                'week' => [
                    'progress_entries' => count($weekProgress),
                    'sessions' => count($weekSessions),
                    'active_days' => count(array_unique(array_map(fn($p) => $p->getDate()->format('Y-m-d'), $weekProgress))),
                    'total_session_time' => array_sum(array_map(fn($s) => $s->getDurationInMinutes(), $weekSessions))
                ],
                'month' => [
                    'progress_entries' => count($monthProgress),
                    'sessions' => count($monthSessions),
                    'active_days' => count(array_unique(array_map(fn($p) => $p->getDate()->format('Y-m-d'), $monthProgress)))
                ],
                'comparisons' => [
                    'week_vs_last_week' => [
                        'progress_diff' => count($weekProgress) - count($lastWeekProgress),
                        'improvement_percentage' => $this->calculateImprovementPercentage(count($lastWeekProgress), count($weekProgress))
                    ]
                ]
            ];
        });

        return new JsonResponse($quickStats);
    }

    #[Route('/recommendations', name: 'api_dashboard_recommendations', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Recommandations personnalisées pour l\'utilisateur',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'type', type: 'string'),
                    new OA\Property(property: 'title', type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'priority', type: 'string'),
                    new OA\Property(property: 'action', type: 'object'),
                    new OA\Property(property: 'reason', type: 'string')
                ]
            )
        )
    )]
    public function recommendations(): JsonResponse
    {
        $user = $this->getUser();

        $cacheKey = "dashboard_recommendations_{$user->getId()}_" . date('Y-m-d');

        $recommendations = $this->cache->get($cacheKey, function() use ($user) {
            $recommendations = [];

            // Analytics pour recommandations intelligentes
            $analytics = $this->analyticsService->getDashboardAnalytics($user, 30);
            $progressStats = $this->progressService->getUserProgressStatistics($user, 30);

            // Recommandation basée sur la consistance
            if ($progressStats['week']['consistency_rate'] < 50) {
                $recommendations[] = [
                    'type' => 'consistency',
                    'title' => 'Améliorez votre régularité',
                    'description' => 'Vous n\'êtes actif que ' . round($progressStats['week']['consistency_rate']) . '% de la semaine',
                    'priority' => 'high',
                    'action' => [
                        'type' => 'set_reminder',
                        'url' => '/settings/notifications'
                    ],
                    'reason' => 'Une meilleure régularité améliore significativement les résultats'
                ];
            }

            // Recommandation basée sur les objectifs
            $activeGoals = $this->goalRepository->findActiveByUser($user);
            if (count($activeGoals) < 2) {
                $recommendations[] = [
                    'type' => 'goals',
                    'title' => 'Diversifiez vos objectifs',
                    'description' => 'Avoir plusieurs objectifs augmente la motivation',
                    'priority' => 'medium',
                    'action' => [
                        'type' => 'create_goal',
                        'url' => '/goals/create'
                    ],
                    'reason' => 'La variété maintient l\'engagement à long terme'
                ];
            }

            // Recommandation basée sur les badges
            $achievementRecommendations = $this->achievementService->getRecommendedAchievements($user, 2);
            foreach ($achievementRecommendations as $achRec) {
                if ($achRec['progress']['percentage'] > 70) {
                    $recommendations[] = [
                        'type' => 'achievement',
                        'title' => 'Badge à portée !',
                        'description' => "Vous êtes à {$achRec['progress']['percentage']}% du badge \"{$achRec['achievement']->getName()}\"",
                        'priority' => 'medium',
                        'action' => [
                            'type' => 'view_achievement',
                            'url' => '/achievements/' . $achRec['achievement']->getId()
                        ],
                        'reason' => 'Un petit effort supplémentaire pour débloquer ce badge'
                    ];
                    break; // Une seule recommandation de badge
                }
            }

            // Recommandation basée sur l'inactivité
            $todayProgress = $this->progressRepository->getTodayProgress($user);
            if (empty($todayProgress) && date('H') > 12) {
                $recommendations[] = [
                    'type' => 'activity',
                    'title' => 'Aucune activité aujourd\'hui',
                    'description' => 'Enregistrez au moins une progression pour maintenir votre série',
                    'priority' => 'high',
                    'action' => [
                        'type' => 'record_progress',
                        'url' => '/progress/quick-add'
                    ],
                    'reason' => 'Maintenir votre série de ' . $user->getCurrentStreak() . ' jours'
                ];
            }

            // Recommandation basée sur les sessions
            $recentSessions = $this->sessionRepository->findByUser($user, 5);
            if (count($recentSessions) < 2) {
                $recommendations[] = [
                    'type' => 'sessions',
                    'title' => 'Essayez les sessions guidées',
                    'description' => 'Les sessions vous aident à structurer vos entraînements',
                    'priority' => 'low',
                    'action' => [
                        'type' => 'start_session',
                        'url' => '/sessions/start'
                    ],
                    'reason' => 'Améliore le suivi et la motivation'
                ];
            }

            // Trier par priorité
            $priorityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
            usort($recommendations, fn($a, $b) =>
                ($priorityOrder[$b['priority']] ?? 0) <=> ($priorityOrder[$a['priority']] ?? 0)
            );

            return array_slice($recommendations, 0, 5); // Max 5 recommandations
        });

        return new JsonResponse($recommendations);
    }

    #[Route('/widgets/streak', name: 'api_dashboard_widget_streak', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Widget de série de jours consécutifs',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'current_streak', type: 'integer'),
                new OA\Property(property: 'longest_streak', type: 'integer'),
                new OA\Property(property: 'streak_type', type: 'string'),
                new OA\Property(property: 'today_completed', type: 'boolean'),
                new OA\Property(property: 'motivation_message', type: 'string'),
                new OA\Property(property: 'next_milestone', type: 'integer')
            ]
        )
    )]
    public function widgetStreak(): JsonResponse
    {
        $user = $this->getUser();

        $streakAnalysis = $this->analyticsService->getStreakAnalysis($user);

        // Ajouter le prochain milestone
        $currentStreak = $user->getCurrentStreak();
        $nextMilestone = match (true) {
            $currentStreak < 7 => 7,
            $currentStreak < 30 => 30,
            $currentStreak < 100 => 100,
            $currentStreak < 365 => 365,
            default => ceil($currentStreak / 100) * 100
        };

        $streakData = array_merge($streakAnalysis, [
            'next_milestone' => $nextMilestone,
            'days_to_milestone' => $nextMilestone - $currentStreak
        ]);

        return new JsonResponse($streakData);
    }

    #[Route('/widgets/goals-progress', name: 'api_dashboard_widget_goals_progress', methods: ['GET'])]
    #[OA\Parameter(name: 'limit', description: 'Nombre d\'objectifs à afficher', in: 'query', required: false)]
    public function widgetGoalsProgress(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $limit = $request->query->getInt('limit', 3);

        $activeGoals = $this->goalRepository->findActiveByUser($user);
        $goalsWithProgress = [];

        foreach (array_slice($activeGoals, 0, $limit) as $goal) {
            $completion = $goal->getCompletionPercentage();
            $trend = $this->analyticsService->calculateProgressTrend($goal, 14);

            $goalsWithProgress[] = [
                'goal' => [
                    'id' => $goal->getId(),
                    'title' => $goal->getTitle(),
                    'category' => $goal->getCategory()?->getName(),
                    'category_color' => $goal->getCategory()?->getColor()
                ],
                'completion_percentage' => $completion,
                'trend' => $trend['direction'],
                'status' => match (true) {
                    $completion >= 100 => 'completed',
                    $completion >= 80 => 'near_completion',
                    $trend['direction'] === 'increasing' => 'on_track',
                    $trend['direction'] === 'decreasing' => 'needs_attention',
                    default => 'stable'
                }
            ];
        }

        return new JsonResponse([
            'goals' => $goalsWithProgress,
            'total_active' => count($activeGoals)
        ]);
    }

    /**
     * Méthodes privées utilitaires
     */
    private function generateQuickActions($user, $activeGoals, $todayProgress): array
    {
        $actions = [];

        // Action rapide : Enregistrer progression
        if (!empty($activeGoals)) {
            $actions[] = [
                'type' => 'record_progress',
                'title' => 'Enregistrer une progression',
                'description' => 'Ajoutez rapidement vos derniers résultats',
                'icon' => '📊',
                'url' => '/progress/quick-add',
                'priority' => empty($todayProgress) ? 'high' : 'medium'
            ];
        }

        // Action rapide : Commencer une session
        $actions[] = [
            'type' => 'start_session',
            'title' => 'Commencer une session',
            'description' => 'Démarrez un entraînement guidé',
            'icon' => '🏃',
            'url' => '/sessions/start',
            'priority' => 'medium'
        ];

        // Action rapide : Nouvel objectif
        $actions[] = [
            'type' => 'create_goal',
            'title' => 'Créer un objectif',
            'description' => 'Définissez un nouveau défi',
            'icon' => '🎯',
            'url' => '/goals/create',
            'priority' => count($activeGoals) < 3 ? 'medium' : 'low'
        ];

        // Action rapide : Voir badges
        $unnotified = $this->achievementService->getUnnotifiedAchievements($user);
        if (!empty($unnotified)) {
            $actions[] = [
                'type' => 'view_achievements',
                'title' => 'Nouveaux badges !',
                'description' => count($unnotified) . ' badge(s) débloqué(s)',
                'icon' => '🏆',
                'url' => '/achievements/unnotified',
                'priority' => 'high'
            ];
        }

        // Trier par priorité
        $priorityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
        usort($actions, fn($a, $b) =>
            ($priorityOrder[$b['priority']] ?? 0) <=> ($priorityOrder[$a['priority']] ?? 0)
        );

        return array_slice($actions, 0, 4);
    }

    private function calculatePerformanceScore($user, $days): int
    {
        $progressStats = $this->progressService->getUserProgressStatistics($user, $days);
        $sessionStats = $this->sessionRepository->getStatsForUser($user, $days);

        $score = 0;

        // Score basé sur la régularité (40 points max)
        $score += min(40, $progressStats['period_stats']['active_days'] * 2);

        // Score basé sur la satisfaction (30 points max)
        $avgSatisfaction = $progressStats['period_stats']['avg_satisfaction'] ?? 0;
        $score += $avgSatisfaction * 5;

        // Score basé sur les sessions (30 points max)
        $completedSessions = $sessionStats['completed_sessions'] ?? 0;
        $score += min(30, $completedSessions * 3);

        return min(100, $score);
    }

    private function calculateTrends($user, $days): array
    {
        $activeGoals = $this->goalRepository->findActiveByUser($user);
        $trends = [
            'improving' => 0,
            'stable' => 0,
            'declining' => 0
        ];

        foreach ($activeGoals as $goal) {
            $trend = $this->analyticsService->calculateProgressTrend($goal, $days);
            $direction = $trend['direction'];

            if ($direction === 'increasing') {
                $trends['improving']++;
            } elseif ($direction === 'decreasing') {
                $trends['declining']++;
            } else {
                $trends['stable']++;
            }
        }

        return $trends;
    }

    private function calculateImprovementPercentage($previous, $current): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return (($current - $previous) / $previous) * 100;
    }
}
