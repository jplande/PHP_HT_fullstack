<?php

namespace App\Repository;

use App\Entity\Achievement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Achievement>
 */
class AchievementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Achievement::class);
    }

    /**
     * Trouve tous les badges actifs
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('a.level', 'ASC')
            ->addOrderBy('a.points', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un badge par son code
     */
    public function findOneByCode(string $code): ?Achievement
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.code = :code')
            ->setParameter('code', strtoupper($code))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Badges par niveau
     */
    public function findByLevel(string $level): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.level = :level')
            ->andWhere('a.isActive = :active')
            ->setParameter('level', $level)
            ->setParameter('active', true)
            ->orderBy('a.points', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Badges par catégorie
     */
    public function findByCategory(string $categoryCode): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.categoryCode = :categoryCode')
            ->andWhere('a.isActive = :active')
            ->setParameter('categoryCode', $categoryCode)
            ->setParameter('active', true)
            ->orderBy('a.level', 'ASC')
            ->addOrderBy('a.points', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Badges non secrets (visibles publiquement)
     */
    public function findPublic(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.isSecret = :secret')
            ->andWhere('a.isActive = :active')
            ->setParameter('secret', false)
            ->setParameter('active', true)
            ->orderBy('a.level', 'ASC')
            ->addOrderBy('a.points', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Badges disponibles pour un utilisateur (non encore obtenus)
     */
    public function findAvailableForUser(User $user): array
    {
        $subQuery = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(ua_sub.achievement)')
            ->from('App\Entity\UserAchievement', 'ua_sub')
            ->where('ua_sub.user = :user');

        return $this->createQueryBuilder('a')
            ->andWhere('a.isActive = :active')
            ->andWhere('a.id NOT IN (' . $subQuery->getDQL() . ')')
            ->setParameter('active', true)
            ->setParameter('user', $user)
            ->orderBy('a.points', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Badges obtenus par un utilisateur
     */
    public function findUnlockedByUser(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.userAchievements', 'ua')
            ->addSelect('ua')
            ->andWhere('ua.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ua.unlockedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des badges
     */
    public function getStatistics(): array
    {
        return $this->createQueryBuilder('a')
            ->select('
                a.level,
                COUNT(a.id) as total_achievements,
                SUM(a.points) as total_points,
                AVG(a.points) as avg_points
            ')
            ->andWhere('a.isActive = :active')
            ->groupBy('a.level')
            ->setParameter('active', true)
            ->orderBy('a.level', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Badges les plus populaires (les plus obtenus)
     */
    public function findMostPopular(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->select('a, COUNT(ua.id) as unlock_count')
            ->leftJoin('a.userAchievements', 'ua')
            ->andWhere('a.isActive = :active')
            ->groupBy('a.id')
            ->orderBy('unlock_count', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Badges les plus rares (les moins obtenus)
     */
    public function findRarest(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->select('a, COUNT(ua.id) as unlock_count')
            ->leftJoin('a.userAchievements', 'ua')
            ->andWhere('a.isActive = :active')
            ->groupBy('a.id')
            ->having('unlock_count < 10 OR unlock_count IS NULL')
            ->orderBy('unlock_count', 'ASC')
            ->setMaxResults($limit)
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Badges recommandés pour un utilisateur (basé sur ses objectifs)
     */
    public function findRecommendedForUser(User $user, int $limit = 5): array
    {
        // Trouve les catégories utilisées par l'utilisateur
        $userCategories = $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT c.code')
            ->from('App\Entity\Category', 'c')
            ->leftJoin('c.goals', 'g')
            ->where('g.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $categoryCodes = array_column($userCategories, 'code');

        if (empty($categoryCodes)) {
            return $this->findAvailableForUser($user);
        }

        $subQuery = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(ua_sub.achievement)')
            ->from('App\Entity\UserAchievement', 'ua_sub')
            ->where('ua_sub.user = :user');

        return $this->createQueryBuilder('a')
            ->andWhere('a.isActive = :active')
            ->andWhere('a.categoryCode IN (:categories) OR a.categoryCode IS NULL')
            ->andWhere('a.id NOT IN (' . $subQuery->getDQL() . ')')
            ->setParameter('active', true)
            ->setParameter('categories', $categoryCodes)
            ->setParameter('user', $user)
            ->orderBy('a.points', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de badges
     */
    public function searchByName(string $query): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.name LIKE :query OR a.description LIKE :query')
            ->andWhere('a.isActive = :active')
            ->andWhere('a.isSecret = :secret')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('active', true)
            ->setParameter('secret', false)
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Badges par gamme de points
     */
    public function findByPointsRange(int $minPoints, int $maxPoints): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.points BETWEEN :minPoints AND :maxPoints')
            ->andWhere('a.isActive = :active')
            ->setParameter('minPoints', $minPoints)
            ->setParameter('maxPoints', $maxPoints)
            ->setParameter('active', true)
            ->orderBy('a.points', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Sauvegarde un badge
     */
    public function save(Achievement $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime un badge
     */
    public function remove(Achievement $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
