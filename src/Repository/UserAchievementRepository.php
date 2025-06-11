<?php

namespace App\Repository;

use App\Entity\UserAchievement;
use App\Entity\User;
use App\Entity\Achievement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserAchievement>
 */
class UserAchievementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserAchievement::class);
    }

    /**
     * Trouve tous les badges d'un utilisateur
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('ua')
            ->leftJoin('ua.achievement', 'a')
            ->addSelect('a')
            ->andWhere('ua.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ua.unlockedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Badges récents d'un utilisateur
     */
    public function findRecentByUser(User $user, int $days = 30): array
    {
        $fromDate = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('ua')
            ->leftJoin('ua.achievement', 'a')
            ->addSelect('a')
            ->andWhere('ua.user = :user')
            ->andWhere('ua.unlockedAt >= :fromDate')
            ->setParameter('user', $user)
            ->setParameter('fromDate', $fromDate)
            ->orderBy('ua.unlockedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Badges par niveau pour un utilisateur
     */
    public function findByUserAndLevel(User $user, string $level): array
    {
        return $this->createQueryBuilder('ua')
            ->leftJoin('ua.achievement', 'a')
            ->addSelect('a')
            ->andWhere('ua.user = :user')
            ->andWhere('a.level = :level')
            ->setParameter('user', $user)
            ->setParameter('level', $level)
            ->orderBy('ua.unlockedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Badges par catégorie pour un utilisateur
     */
    public function findByUserAndCategory(User $user, string $categoryCode): array
    {
        return $this->createQueryBuilder('ua')
            ->leftJoin('ua.achievement', 'a')
            ->addSelect('a')
            ->andWhere('ua.user = :user')
            ->andWhere('a.categoryCode = :categoryCode')
            ->setParameter('user', $user)
            ->setParameter('categoryCode', $categoryCode)
            ->orderBy('ua.unlockedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un utilisateur possède un badge spécifique
     */
    public function userHasAchievement(User $user, Achievement $achievement): bool
    {
        $result = $this->createQueryBuilder('ua')
            ->select('COUNT(ua.id)')
            ->andWhere('ua.user = :user')
            ->andWhere('ua.achievement = :achievement')
            ->setParameter('user', $user)
            ->setParameter('achievement', $achievement)
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }

    /**
     * Statistiques des badges pour un utilisateur
     */
    public function getStatsForUser(User $user): array
    {
        return $this->createQueryBuilder('ua')
            ->select('
                COUNT(ua.id) as total_achievements,
                SUM(a.points) as total_points,
                a.level,
                COUNT(ua.id) as count_by_level
            ')
            ->leftJoin('ua.achievement', 'a')
            ->andWhere('ua.user = :user')
            ->groupBy('a.level')
            ->setParameter('user', $user)
            ->orderBy('a.level', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Badges non notifiés pour un utilisateur
     */
    public function findUnnotifiedByUser(User $user): array
    {
        return $this->createQueryBuilder('ua')
            ->leftJoin('ua.achievement', 'a')
            ->addSelect('a')
            ->andWhere('ua.user = :user')
            ->andWhere('ua.isNotified = :notified')
            ->setParameter('user', $user)
            ->setParameter('notified', false)
            ->orderBy('ua.unlockedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Marque les badges comme notifiés
     */
    public function markAsNotified(array $userAchievements): void
    {
        if (empty($userAchievements)) {
            return;
        }

        $ids = array_map(fn($ua) => $ua->getId(), $userAchievements);

        $this->createQueryBuilder('ua')
            ->update()
            ->set('ua.isNotified', ':notified')
            ->andWhere('ua.id IN (:ids)')
            ->setParameter('notified', true)
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }

    /**
     * Classement des utilisateurs par points de badges
     */
    public function getLeaderboard(int $limit = 10): array
    {
        return $this->createQueryBuilder('ua')
            ->select('
                u.id as user_id,
                u.username,
                u.firstName,
                u.lastName,
                SUM(a.points) as total_points,
                COUNT(ua.id) as total_achievements
            ')
            ->leftJoin('ua.user', 'u')
            ->leftJoin('ua.achievement', 'a')
            ->groupBy('u.id', 'u.username', 'u.firstName', 'u.lastName')
            ->orderBy('total_points', 'DESC')
            ->addOrderBy('total_achievements', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Progression mensuelle des badges pour un utilisateur
     */
    public function getMonthlyProgress(User $user, int $months = 12): array
    {
        $fromDate = new \DateTime("-{$months} months");

        return $this->createQueryBuilder('ua')
            ->select('
                YEAR(ua.unlockedAt) as year,
                MONTH(ua.unlockedAt) as month,
                COUNT(ua.id) as achievements_count,
                SUM(a.points) as points_earned
            ')
            ->leftJoin('ua.achievement', 'a')
            ->andWhere('ua.user = :user')
            ->andWhere('ua.unlockedAt >= :fromDate')
            ->groupBy('year', 'month')
            ->orderBy('year', 'ASC')
            ->addOrderBy('month', 'ASC')
            ->setParameter('user', $user)
            ->setParameter('fromDate', $fromDate)
            ->getQuery()
            ->getResult();
    }

    /**
     * Utilisateurs ayant débloqué un badge spécifique
     */
    public function findUsersByAchievement(Achievement $achievement): array
    {
        return $this->createQueryBuilder('ua')
            ->leftJoin('ua.user', 'u')
            ->addSelect('u')
            ->andWhere('ua.achievement = :achievement')
            ->setParameter('achievement', $achievement)
            ->orderBy('ua.unlockedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Derniers badges débloqués (tous utilisateurs)
     */
    public function findLatestUnlocked(int $limit = 20): array
    {
        return $this->createQueryBuilder('ua')
            ->leftJoin('ua.user', 'u')
            ->leftJoin('ua.achievement', 'a')
            ->addSelect('u', 'a')
            ->orderBy('ua.unlockedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Comparaison entre utilisateurs
     */
    public function compareUsers(User $user1, User $user2): array
    {
        $user1Stats = $this->getStatsForUser($user1);
        $user2Stats = $this->getStatsForUser($user2);

        // Badges en commun
        $commonAchievements = $this->createQueryBuilder('ua1')
            ->select('a.id, a.name, a.level')
            ->leftJoin('ua1.achievement', 'a')
            ->leftJoin('App\Entity\UserAchievement', 'ua2', 'WITH', 'ua2.achievement = a.id')
            ->andWhere('ua1.user = :user1')
            ->andWhere('ua2.user = :user2')
            ->setParameter('user1', $user1)
            ->setParameter('user2', $user2)
            ->getQuery()
            ->getResult();

        return [
            'user1_stats' => $user1Stats,
            'user2_stats' => $user2Stats,
            'common_achievements' => $commonAchievements
        ];
    }

    /**
     * Badges débloqués par période
     */
    public function findByPeriod(\DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('ua')
            ->leftJoin('ua.user', 'u')
            ->leftJoin('ua.achievement', 'a')
            ->addSelect('u', 'a')
            ->andWhere('ua.unlockedAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('ua.unlockedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Sauvegarde une attribution de badge
     */
    public function save(UserAchievement $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une attribution de badge
     */
    public function remove(UserAchievement $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
