<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Trouve un utilisateur par email
     */
    public function findOneByEmail(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Utilisateurs actifs (avec activité récente)
     */
    public function findActiveUsers(int $days = 7): array
    {
        $fromDate = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('u')
            ->andWhere('u.lastActivityDate >= :fromDate')
            ->setParameter('fromDate', $fromDate)
            ->orderBy('u.lastActivityDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Classement par niveau
     */
    public function getLeaderboardByLevel(int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->select('
                u.id,
                u.username,
                u.firstName,
                u.lastName,
                u.level,
                u.totalPoints,
                u.currentStreak,
                u.longestStreak
            ')
            ->orderBy('u.level', 'DESC')
            ->addOrderBy('u.totalPoints', 'DESC')
            ->addOrderBy('u.currentStreak', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Classement par points
     */
    public function getLeaderboardByPoints(int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->select('
                u.id,
                u.username,
                u.firstName,
                u.lastName,
                u.level,
                u.totalPoints,
                u.currentStreak
            ')
            ->orderBy('u.totalPoints', 'DESC')
            ->addOrderBy('u.level', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Classement par série actuelle
     */
    public function getLeaderboardByStreak(int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->select('
                u.id,
                u.username,
                u.firstName,
                u.lastName,
                u.currentStreak,
                u.longestStreak,
                u.totalPoints
            ')
            ->andWhere('u.currentStreak > 0')
            ->orderBy('u.currentStreak', 'DESC')
            ->addOrderBy('u.longestStreak', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Utilisateurs avec objectifs actifs
     */
    public function findWithActiveGoals(): array
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.goals', 'g', 'WITH', 'g.status = :active')
            ->andWhere('g.id IS NOT NULL')
            ->setParameter('active', 'active')
            ->orderBy('u.lastActivityDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques globales des utilisateurs
     */
    public function getGlobalStats(): array
    {
        return $this->createQueryBuilder('u')
            ->select('
                COUNT(u.id) as total_users,
                AVG(u.level) as avg_level,
                AVG(u.totalPoints) as avg_points,
                AVG(u.currentStreak) as avg_current_streak,
                AVG(u.longestStreak) as avg_longest_streak,
                MAX(u.level) as max_level,
                MAX(u.totalPoints) as max_points,
                MAX(u.longestStreak) as max_streak
            ')
            ->getQuery()
            ->getSingleResult();
    }

    /**
     * Utilisateurs par gamme de niveau
     */
    public function findByLevelRange(int $minLevel, int $maxLevel): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.level BETWEEN :minLevel AND :maxLevel')
            ->setParameter('minLevel', $minLevel)
            ->setParameter('maxLevel', $maxLevel)
            ->orderBy('u.level', 'DESC')
            ->addOrderBy('u.totalPoints', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche d'utilisateurs
     */
    public function searchUsers(string $query): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.username LIKE :query OR u.firstName LIKE :query OR u.lastName LIKE :query OR u.email LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Utilisateurs inactifs (sans activité récente)
     */
    public function findInactiveUsers(int $days = 30): array
    {
        $cutoffDate = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('u')
            ->andWhere('u.lastActivityDate < :cutoffDate OR u.lastActivityDate IS NULL')
            ->setParameter('cutoffDate', $cutoffDate)
            ->orderBy('u.lastActivityDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Utilisateurs nouvellement inscrits
     */
    public function findNewUsers(int $days = 7): array
    {
        $fromDate = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('u')
            ->andWhere('u.createdAt >= :fromDate')
            ->setParameter('fromDate', $fromDate)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Distribution des utilisateurs par niveau
     */
    public function getLevelDistribution(): array
    {
        return $this->createQueryBuilder('u')
            ->select('
                u.level,
                COUNT(u.id) as user_count
            ')
            ->groupBy('u.level')
            ->orderBy('u.level', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Utilisateurs avec le plus d'objectifs
     */
    public function findMostActiveByGoals(int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->select('u, COUNT(g.id) as goal_count')
            ->leftJoin('u.goals', 'g')
            ->groupBy('u.id')
            ->orderBy('goal_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Utilisateurs avec le plus de badges
     */
    public function findMostActiveByAchievements(int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->select('u, COUNT(ua.id) as achievement_count')
            ->leftJoin('u.userAchievements', 'ua')
            ->groupBy('u.id')
            ->orderBy('achievement_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Évolution mensuelle des inscriptions
     */
    public function getMonthlyRegistrations(int $months = 12): array
    {
        $fromDate = new \DateTime("-{$months} months");

        return $this->createQueryBuilder('u')
            ->select('
                YEAR(u.createdAt) as year,
                MONTH(u.createdAt) as month,
                COUNT(u.id) as registration_count
            ')
            ->andWhere('u.createdAt >= :fromDate')
            ->groupBy('year', 'month')
            ->orderBy('year', 'ASC')
            ->addOrderBy('month', 'ASC')
            ->setParameter('fromDate', $fromDate)
            ->getQuery()
            ->getResult();
    }

    /**
     * Utilisateurs par système d'unités
     */
    public function findByUnitSystem(string $unitSystem): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.unitSystem = :unitSystem')
            ->setParameter('unitSystem', $unitSystem)
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Utilisateurs par locale
     */
    public function findByLocale(string $locale): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.locale = :locale')
            ->setParameter('locale', $locale)
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Mise à jour de la dernière activité
     */
    public function updateLastActivity(User $user): void
    {
        $user->setLastActivityDate(new \DateTime());
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Utilisateurs recommandés (amis potentiels)
     */
    public function findRecommended(User $user, int $limit = 5): array
    {
        // Trouve des utilisateurs avec des objectifs similaires ou du même niveau
        return $this->createQueryBuilder('u')
            ->leftJoin('u.goals', 'g')
            ->leftJoin('g.category', 'c')
            ->leftJoin('App\Entity\Goal', 'ug', 'WITH', 'ug.user = :user')
            ->leftJoin('ug.category', 'uc', 'WITH', 'uc.id = c.id')
            ->andWhere('u.id != :userId')
            ->andWhere('u.level BETWEEN :minLevel AND :maxLevel')
            ->andWhere('uc.id IS NOT NULL') // Ont des catégories en commun
            ->setParameter('user', $user)
            ->setParameter('userId', $user->getId())
            ->setParameter('minLevel', max(1, $user->getLevel() - 2))
            ->setParameter('maxLevel', $user->getLevel() + 2)
            ->groupBy('u.id')
            ->orderBy('u.totalPoints', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Sauvegarde un utilisateur
     */
    public function save(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime un utilisateur
     */
    public function remove(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
