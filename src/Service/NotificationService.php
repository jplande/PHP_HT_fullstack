<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\Goal;
use App\Entity\Achievement;
use App\Repository\UserRepository;
use App\Repository\GoalRepository;
use App\Repository\ProgressRepository;
use App\Repository\UserAchievementRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Twig\Environment;

class NotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private UserRepository $userRepository,
        private GoalRepository $goalRepository,
        private ProgressRepository $progressRepository,
        private UserAchievementRepository $userAchievementRepository,
        private string $senderEmail = 'noreply@objectifs-sport.com'
    ) {}

    /**
     * Envoie les rappels quotidiens aux utilisateurs
     */
    public function sendDailyReminders(): int
    {
        $users = $this->userRepository->findActiveUsers(7);
        $sentCount = 0;

        foreach ($users as $user) {
            if ($this->shouldSendDailyReminder($user)) {
                $this->sendDailyReminderToUser($user);
                $sentCount++;
            }
        }

        return $sentCount;
    }

    /**
     * Envoie un rappel quotidien à un utilisateur spécifique
     */
    public function sendDailyReminderToUser(User $user): void
    {
        $activeGoals = $this->goalRepository->findActiveByUser($user);
        $todayProgress = $this->progressRepository->getTodayProgress($user);
        $goalsMissingProgress = $this->getGoalsMissingTodayProgress($user, $activeGoals, $todayProgress);

        if (empty($goalsMissingProgress)) {
            return; // Tout est à jour
        }

        $email = (new TemplatedEmail())
            ->from($this->senderEmail)
            ->to($user->getEmail())
            ->subject('N\'oubliez pas vos objectifs du jour !')
            ->htmlTemplate('emails/daily_reminder.html.twig')
            ->context([
                'user' => $user,
                'goals_missing_progress' => $goalsMissingProgress,
                'current_streak' => $user->getCurrentStreak(),
                'motivation_message' => $this->getMotivationMessage($user)
            ]);

        $this->mailer->send($email);
    }

    /**
     * Notifie les nouveaux badges débloqués
     */
    public function sendAchievementNotifications(): int
    {
        $unnotifiedAchievements = $this->userAchievementRepository->createQueryBuilder('ua')
            ->leftJoin('ua.user', 'u')
            ->leftJoin('ua.achievement', 'a')
            ->where('ua.isNotified = false')
            ->andWhere('u.email IS NOT NULL')
            ->getQuery()
            ->getResult();

        $sentCount = 0;
        $userNotifications = [];

        // Grouper par utilisateur
        foreach ($unnotifiedAchievements as $userAchievement) {
            $userId = $userAchievement->getUser()->getId();
            if (!isset($userNotifications[$userId])) {
                $userNotifications[$userId] = [
                    'user' => $userAchievement->getUser(),
                    'achievements' => []
                ];
            }
            $userNotifications[$userId]['achievements'][] = $userAchievement;
        }

        // Envoyer les notifications groupées
        foreach ($userNotifications as $notification) {
            $this->sendAchievementNotificationToUser(
                $notification['user'],
                $notification['achievements']
            );
            $sentCount++;

            // Marquer comme notifié
            $this->userAchievementRepository->markAsNotified($notification['achievements']);
        }

        return $sentCount;
    }

    /**
     * Envoie une notification de badge à un utilisateur
     */
    public function sendAchievementNotificationToUser(User $user, array $userAchievements): void
    {
        $totalPoints = array_sum(array_map(fn($ua) => $ua->getAchievement()->getPoints(), $userAchievements));

        $email = (new TemplatedEmail())
            ->from($this->senderEmail)
            ->to($user->getEmail())
            ->subject('🏆 Félicitations ! Vous avez débloqué de nouveaux badges !')
            ->htmlTemplate('emails/achievement_notification.html.twig')
            ->context([
                'user' => $user,
                'achievements' => $userAchievements,
                'total_points' => $totalPoints,
                'new_level' => $user->getLevel(),
                'celebration_message' => $this->getCelebrationMessage($userAchievements)
            ]);

        $this->mailer->send($email);
    }

    /**
     * Envoie les alertes d'objectifs qui se terminent bientôt
     */
    public function sendGoalDeadlineAlerts(): int
    {
        $users = $this->userRepository->findActiveUsers(30);
        $sentCount = 0;

        foreach ($users as $user) {
            $endingGoals = $this->goalRepository->findEndingSoon($user, 7);

            if (!empty($endingGoals)) {
                $this->sendGoalDeadlineAlert($user, $endingGoals);
                $sentCount++;
            }
        }

        return $sentCount;
    }

    /**
     * Envoie une alerte de deadline à un utilisateur
     */
    public function sendGoalDeadlineAlert(User $user, array $endingGoals): void
    {
        $email = (new TemplatedEmail())
            ->from($this->senderEmail)
            ->to($user->getEmail())
            ->subject('⏰ Vos objectifs arrivent à échéance !')
            ->htmlTemplate('emails/goal_deadline_alert.html.twig')
            ->context([
                'user' => $user,
                'ending_goals' => $endingGoals,
                'urgency_message' => $this->getUrgencyMessage($endingGoals)
            ]);

        $this->mailer->send($email);
    }

    /**
     * Envoie les rapports hebdomadaires
     */
    public function sendWeeklyReports(): int
    {
        $users = $this->userRepository->findActiveUsers(14);
        $sentCount = 0;

        foreach ($users as $user) {
            if ($this->shouldSendWeeklyReport($user)) {
                $this->sendWeeklyReportToUser($user);
                $sentCount++;
            }
        }

        return $sentCount;
    }

    /**
     * Envoie un rapport hebdomadaire à un utilisateur
     */
    public function sendWeeklyReportToUser(User $user): void
    {
        $weekStart = new \DateTime('monday this week');
        $weekEnd = new \DateTime('sunday this week');

        $weekProgress = $this->progressRepository->getWeekProgress($user, $weekStart);
        $weekStats = $this->calculateWeekStats($user, $weekStart, $weekEnd);
        $achievements = $this->userAchievementRepository->findByPeriod($weekStart, $weekEnd);

        $email = (new TemplatedEmail())
            ->from($this->senderEmail)
            ->to($user->getEmail())
            ->subject('📊 Votre rapport hebdomadaire')
            ->htmlTemplate('emails/weekly_report.html.twig')
            ->context([
                'user' => $user,
                'week_start' => $weekStart,
                'week_end' => $weekEnd,
                'week_stats' => $weekStats,
                'week_progress' => $weekProgress,
                'week_achievements' => array_filter($achievements, fn($ua) => $ua->getUser() === $user),
                'recommendations' => $this->generateWeeklyRecommendations($user, $weekStats)
            ]);

        $this->mailer->send($email);
    }

    /**
     * Envoie des notifications de motivation pour utilisateurs inactifs
     */
    public function sendMotivationNotifications(): int
    {
        $inactiveUsers = $this->userRepository->findInactiveUsers(7);
        $sentCount = 0;

        foreach ($inactiveUsers as $user) {
            if ($this->shouldSendMotivationNotification($user)) {
                $this->sendMotivationNotificationToUser($user);
                $sentCount++;
            }
        }

        return $sentCount;
    }

    /**
     * Envoie une notification de motivation à un utilisateur inactif
     */
    public function sendMotivationNotificationToUser(User $user): void
    {
        $lastActivity = $user->getLastActivityDate();
        $daysSinceActivity = $lastActivity ? (new \DateTime())->diff($lastActivity)->days : 999;
        $personalizedMessage = $this->getPersonalizedMotivationMessage($user, $daysSinceActivity);

        $email = (new TemplatedEmail())
            ->from($this->senderEmail)
            ->to($user->getEmail())
            ->subject('💪 On vous attend ! Reprenez vos objectifs')
            ->htmlTemplate('emails/motivation_notification.html.twig')
            ->context([
                'user' => $user,
                'days_since_activity' => $daysSinceActivity,
                'motivation_message' => $personalizedMessage,
                'quick_goals' => $this->getQuickWinGoals($user),
                'past_achievements' => $this->getUserBestAchievements($user)
            ]);

        $this->mailer->send($email);
    }

    /**
     * Envoie les notifications de félicitations pour objectifs complétés
     */
    public function sendGoalCompletionCongratulations(User $user, Goal $goal): void
    {
        $completionStats = $this->calculateGoalCompletionStats($goal);

        $email = (new TemplatedEmail())
            ->from($this->senderEmail)
            ->to($user->getEmail())
            ->subject('🎉 Objectif atteint ! Félicitations !')
            ->htmlTemplate('emails/goal_completion.html.twig')
            ->context([
                'user' => $user,
                'goal' => $goal,
                'completion_stats' => $completionStats,
                'celebration_message' => $this->getGoalCompletionMessage($goal),
                'next_goals_suggestions' => $this->suggestNextGoals($user, $goal)
            ]);

        $this->mailer->send($email);
    }

    /**
     * Envoie des notifications push/SMS (placeholder pour intégration future)
     */
    public function sendPushNotification(User $user, string $title, string $message, array $data = []): bool
    {
        // TODO: Intégrer avec un service de notifications push (Firebase, OneSignal, etc.)
        // Pour l'instant, on log ou on sauvegarde dans une queue

        return true; // Placeholder
    }

    /**
     * Méthodes privées utilitaires
     */
    private function shouldSendDailyReminder(User $user): bool
    {
        $preferences = $user->getPreferences() ?? [];
        $notificationPrefs = $preferences['notifications'] ?? [];

        // Vérifier les préférences utilisateur
        if (isset($notificationPrefs['dailyReminder']) && !$notificationPrefs['dailyReminder']) {
            return false;
        }

        // Vérifier si l'utilisateur a un email
        if (!$user->getEmail()) {
            return false;
        }

        // Vérifier l'heure préférée (par défaut 20h)
        $preferredHour = $preferences['goals']['reminderTime'] ?? '20:00';
        $currentHour = (new \DateTime())->format('H:i');

        return abs(strtotime($currentHour) - strtotime($preferredHour)) < 3600; // 1h de tolérance
    }

    private function getGoalsMissingTodayProgress(User $user, array $activeGoals, array $todayProgress): array
    {
        $goalsWithProgress = [];
        foreach ($todayProgress as $progress) {
            $goalsWithProgress[] = $progress->getGoal()->getId();
        }

        return array_filter($activeGoals, function(Goal $goal) use ($goalsWithProgress) {
            return !in_array($goal->getId(), $goalsWithProgress) &&
                $goal->getFrequencyType() === 'daily';
        });
    }

    private function getMotivationMessage(User $user): string
    {
        $streak = $user->getCurrentStreak();

        return match (true) {
            $streak >= 30 => "Incroyable série de {$streak} jours ! Vous êtes une machine ! 🔥",
            $streak >= 7 => "Excellente série de {$streak} jours ! Continuez sur cette lancée ! 💪",
            $streak >= 3 => "Belle série de {$streak} jours ! Gardez le rythme ! ⭐",
            $streak > 0 => "Série de {$streak} jour(s) en cours ! Ne la cassez pas ! 🚀",
            default => "C'est le moment de commencer une nouvelle série ! 🎯"
        };
    }

    private function getCelebrationMessage(array $userAchievements): string
    {
        $count = count($userAchievements);
        $hasRare = false;
        $hasHigh = false;

        foreach ($userAchievements as $ua) {
            $achievement = $ua->getAchievement();
            if ($achievement->getUnlockedCount() < 10) {
                $hasRare = true;
            }
            if (in_array($achievement->getLevel(), ['gold', 'platinum', 'diamond'])) {
                $hasHigh = true;
            }
        }

        return match (true) {
            $hasRare => "Félicitations ! Vous avez débloqué un badge rare ! 🌟",
            $hasHigh => "Incroyable ! Un badge de haut niveau ! Vous excellez ! 🏆",
            $count > 3 => "Wow ! {$count} badges d'un coup ! Vous êtes en feu ! 🔥",
            $count > 1 => "Super ! {$count} nouveaux badges ! 🎉",
            default => "Nouveau badge débloqué ! Bien joué ! 🏅"
        };
    }

    private function getUrgencyMessage(array $endingGoals): string
    {
        $count = count($endingGoals);
        $minDays = min(array_map(fn($goal) => (new \DateTime())->diff($goal->getEndDate())->days, $endingGoals));

        return match (true) {
            $minDays <= 1 => "⚠️ Urgent ! Certains objectifs se terminent demain !",
            $minDays <= 3 => "⏰ Plus que quelques jours pour terminer vos objectifs !",
            default => "📅 Vos objectifs arrivent bientôt à échéance, c'est le moment de sprinter !"
        };
    }

    private function shouldSendWeeklyReport(User $user): bool
    {
        $preferences = $user->getPreferences() ?? [];
        $notificationPrefs = $preferences['notifications'] ?? [];

        return ($notificationPrefs['weeklyReport'] ?? true) &&
            $user->getEmail() &&
            (new \DateTime())->format('N') == 1; // Lundi
    }

    private function calculateWeekStats(User $user, \DateTime $weekStart, \DateTime $weekEnd): array
    {
        $weekProgress = $this->progressRepository->findByPeriod($weekStart, $weekEnd);
        $userProgress = array_filter($weekProgress, fn($p) => $p->getGoal()->getUser() === $user);

        $uniqueDays = array_unique(array_map(fn($p) => $p->getDate()->format('Y-m-d'), $userProgress));
        $satisfactionRatings = array_filter(array_map(fn($p) => $p->getSatisfactionRating(), $userProgress));

        return [
            'total_progress_entries' => count($userProgress),
            'active_days' => count($uniqueDays),
            'consistency_rate' => (count($uniqueDays) / 7) * 100,
            'average_satisfaction' => !empty($satisfactionRatings) ? array_sum($satisfactionRatings) / count($satisfactionRatings) : 0,
            'goals_worked_on' => count(array_unique(array_map(fn($p) => $p->getGoal()->getId(), $userProgress)))
        ];
    }

    private function generateWeeklyRecommendations(User $user, array $weekStats): array
    {
        $recommendations = [];

        if ($weekStats['consistency_rate'] < 50) {
            $recommendations[] = [
                'type' => 'consistency',
                'message' => 'Essayez d\'être plus régulier cette semaine. Visez au moins 5 jours d\'activité.',
                'icon' => '📅'
            ];
        }

        if ($weekStats['average_satisfaction'] < 6) {
            $recommendations[] = [
                'type' => 'satisfaction',
                'message' => 'Vos sessions semblent moins satisfaisantes. Peut-être revoir vos objectifs ?',
                'icon' => '😊'
            ];
        }

        if ($weekStats['goals_worked_on'] < 2) {
            $recommendations[] = [
                'type' => 'variety',
                'message' => 'Diversifiez vos activités ! Travaillez sur plusieurs objectifs cette semaine.',
                'icon' => '🎯'
            ];
        }

        if (empty($recommendations)) {
            $recommendations[] = [
                'type' => 'encouragement',
                'message' => 'Excellente semaine ! Continuez sur cette lancée !',
                'icon' => '🚀'
            ];
        }

        return $recommendations;
    }

    private function shouldSendMotivationNotification(User $user): bool
    {
        $preferences = $user->getPreferences() ?? [];
        $notificationPrefs = $preferences['notifications'] ?? [];

        return ($notificationPrefs['motivationReminders'] ?? true) && $user->getEmail();
    }

    private function getPersonalizedMotivationMessage(User $user, int $daysSinceActivity): string
    {
        $firstName = $user->getFirstName() ?: $user->getUsername();

        return match (true) {
            $daysSinceActivity >= 30 => "{$firstName}, ça fait un mois qu'on ne vous a pas vu ! Vos objectifs vous attendent. 💪",
            $daysSinceActivity >= 14 => "{$firstName}, deux semaines d'absence, c'est le moment de revenir ! 🚀",
            $daysSinceActivity >= 7 => "{$firstName}, une semaine sans activité ? Reprenons ensemble ! ⭐",
            default => "{$firstName}, quelques jours d'absence, ça arrive ! L'important c'est de reprendre ! 🎯"
        };
    }

    private function getQuickWinGoals(User $user): array
    {
        $activeGoals = $this->goalRepository->findActiveByUser($user);

        return array_filter($activeGoals, function(Goal $goal) {
            $completion = $goal->getCompletionPercentage();
            return $completion > 70 && $completion < 100;
        });
    }

    private function getUserBestAchievements(User $user): array
    {
        $userAchievements = $this->userAchievementRepository->findByUser($user);

        // Filtrer les badges de haut niveau ou rares
        return array_filter($userAchievements, function($ua) {
            $achievement = $ua->getAchievement();
            return in_array($achievement->getLevel(), ['gold', 'platinum', 'diamond']) ||
                $achievement->getUnlockedCount() < 20;
        });
    }

    private function calculateGoalCompletionStats(Goal $goal): array
    {
        $startDate = $goal->getStartDate();
        $completionDate = new \DateTime();
        $totalDays = $startDate->diff($completionDate)->days;

        $allProgress = $this->progressRepository->findByGoalAndPeriod($goal, $startDate, $completionDate);

        return [
            'total_days' => $totalDays,
            'total_progress_entries' => count($allProgress),
            'average_progress_per_day' => $totalDays > 0 ? count($allProgress) / $totalDays : 0,
            'completion_percentage' => $goal->getCompletionPercentage()
        ];
    }

    private function getGoalCompletionMessage(Goal $goal): string
    {
        $category = $goal->getCategory();
        $completion = $goal->getCompletionPercentage();

        $messages = [
            'FITNESS' => [
                "Votre corps vous dit merci ! 💪",
                "Force et persévérance ont payé ! 🏋️",
                "Vous avez repoussé vos limites ! 🔥"
            ],
            'RUNNING' => [
                "Chaque foulée vous a mené au succès ! 🏃‍♀️",
                "La ligne d'arrivée n'était que le début ! 🏁",
                "Votre endurance est remarquable ! 🌟"
            ],
            'NUTRITION' => [
                "Votre santé vous remercie ! 🥗",
                "De bonnes habitudes pour la vie ! 🌱",
                "Votre corps est votre temple ! ✨"
            ]
        ];

        $categoryMessages = $messages[$category?->getCode()] ?? [
            "Objectif atteint avec brio ! 🎯",
            "Votre détermination a payé ! 🚀",
            "Bravo pour cette réussite ! 🏆"
        ];

        return $categoryMessages[array_rand($categoryMessages)];
    }

    private function suggestNextGoals(User $user, Goal $completedGoal): array
    {
        $category = $completedGoal->getCategory();
        $suggestions = [];

        // Suggestions basées sur la catégorie
        if ($category) {
            $suggestions[] = [
                'title' => 'Niveau supérieur en ' . $category->getName(),
                'description' => 'Augmentez la difficulté de vos exercices',
                'category' => $category
            ];
        }

        // Suggestions génériques
        $suggestions[] = [
            'title' => 'Maintenir vos acquis',
            'description' => 'Créez un objectif de maintien de vos performances',
            'category' => $category
        ];

        $suggestions[] = [
            'title' => 'Explorer une nouvelle catégorie',
            'description' => 'Diversifiez vos activités avec un nouvel objectif',
            'category' => null
        ];

        return array_slice($suggestions, 0, 3);
    }

    /**
     * Méthodes publiques pour tester les notifications
     */
    public function sendTestNotification(User $user, string $type): bool
    {
        try {
            switch ($type) {
                case 'daily_reminder':
                    $this->sendDailyReminderToUser($user);
                    break;
                case 'achievement':
                    $achievements = $this->userAchievementRepository->findRecentByUser($user, 7);
                    if (!empty($achievements)) {
                        $this->sendAchievementNotificationToUser($user, array_slice($achievements, 0, 1));
                    }
                    break;
                case 'weekly_report':
                    $this->sendWeeklyReportToUser($user);
                    break;
                case 'motivation':
                    $this->sendMotivationNotificationToUser($user);
                    break;
                default:
                    return false;
            }
            return true;
        } catch (\Exception $e) {
            // Log l'erreur
            return false;
        }
    }

    /**
     * Statistiques des notifications envoyées
     */
    public function getNotificationStats(\DateTime $startDate, \DateTime $endDate): array
    {
        // TODO: Implémenter un système de logs des notifications pour les statistiques
        return [
            'daily_reminders' => 0,
            'achievement_notifications' => 0,
            'weekly_reports' => 0,
            'motivation_notifications' => 0,
            'goal_completion_notifications' => 0
        ];
    }
}
