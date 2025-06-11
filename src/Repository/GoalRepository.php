<?php

namespace App\Repository;

use App\Entity\Goal;
use App\Entity\User;
use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<Goal>
 */
class GoalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Goal::class);
    }

    /**
     * Trouve tous les objectifs actifs d'un utilisateur
     */
    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.user = :user')
            ->andWhere('g.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'active')
            ->orderBy('g.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les objectifs par statut pour un utilisateur
     */
    public function findByUserAndStatus(User $user, string $status): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.user = :user')
            ->andWhere('g.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', $status)
            ->orderBy('g.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les objectifs par catégorie pour un utilisateur
     */
    public function findByUserAndCategory(User $user, Category $category, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('g')
            ->andWhere('g.user = :user')
            ->andWhere('g.category = :category')
            ->setParameter('user', $user)
            ->setParameter('category', $category);

        if ($status) {
            $qb->andWhere('g.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->orderBy('g.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les objectifs avec leurs métriques et progressions récentes
     */
    public function findWithRecentProgress(User $user, int $days = 30): array
    {
        $fromDate = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('g')
            ->leftJoin('g.metrics', 'm')
            ->leftJoin('g.progressEntries', 'p', 'WITH', 'p.date >= :fromDate')
            ->addSelect('m', 'p')
            ->andWhere('g.user = :user')
            ->andWhere('g.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'active')
            ->setParameter('fromDate', $fromDate)
            ->orderBy('g.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les objectifs qui se terminent bientôt
     */
    public function findEndingSoon(User $user, int $days = 7): array
    {
        $endDate = new \DateTime("+{$days} days");

        return $this->createQueryBuilder('g')
            ->andWhere('g.user = :user')
            ->andWhere('g.status = :status')
            ->andWhere('g.endDate IS NOT NULL')
            ->andWhere('g.endDate <= :endDate')
            ->andWhere('g.endDate >= :today')
            ->setParameter('user', $user)
            ->setParameter('status', 'active')
            ->setParameter('endDate', $endDate)
            ->setParameter('today', new \DateTime())
            ->orderBy('g.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les objectifs par fréquence
     */
    public function findByFrequency(User $user, string $frequency): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.user = :user')
            ->andWhere('g.frequencyType = :frequency')
            ->andWhere('g.status = :status')
            ->setParameter('user', $user)
            ->setParameter('frequency', $frequency)
            ->setParameter('status', 'active')
            ->orderBy('g.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche d'objectifs par texte
     */
    public function searchByText(User $user, string $query): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.category', 'c')
            ->addSelect('c')
            ->andWhere('g.user = :user')
            ->andWhere('(g.title LIKE :query OR g.description LIKE :query OR c.name LIKE :query)')
            ->setParameter('user', $user)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('g.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques d'objectifs pour un utilisateur
     */
    public function getStatsForUser(User $user): array
    {
        $qb = $this->createQueryBuilder('g')
            ->select('
                COUNT(g.id) as total,
                SUM(CASE WHEN g.status = :active THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN g.status = :completed THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN g.status = :paused THEN 1 ELSE 0 END) as paused
            ')
            ->andWhere('g.user = :user')
            ->setParameter('user', $user)
            ->setParameter('active', 'active')
            ->setParameter('completed', 'completed')
            ->setParameter('paused', 'paused');

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * Objectifs les plus récemment modifiés
     */
    public function findRecentlyUpdated(User $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.category', 'c')
            ->addSelect('c')
            ->andWhere('g.user = :user')
            ->orderBy('g.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    /**
     * Objectifs complétés par période
     */
    public function findCompletedInPeriod(User $user, \DateTime $startDate, \DateTime $endDate): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.user = :user')
            ->andWhere('g.status = :status')
            ->andWhere('g.updatedAt BETWEEN :startDate AND :endDate')
            ->setParameter('user', $user)
            ->setParameter('status', 'completed')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('g.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Objectifs avec le plus de progressions récentes
     */
    public function findMostActiveGoals(User $user, int $days = 30, int $limit = 10): array
    {
        $fromDate = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('g')
            ->leftJoin('g.progressEntries', 'p', 'WITH', 'p.date >= :fromDate')
            ->addSelect('COUNT(p.id) as progress_count')
            ->andWhere('g.user = :user')
            ->andWhere('g.status = :status')
            ->groupBy('g.id')
            ->orderBy('progress_count', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('user', $user)
            ->setParameter('status', 'active')
            ->setParameter('fromDate', $fromDate)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les objectifs qui nécessitent une mise à jour (pas de progression depuis X jours)
     */
    public function findNeedingUpdate(User $user, int $daysSinceLastProgress = 3): array
    {
        $cutoffDate = new \DateTime("-{$daysSinceLastProgress} days");

        // Sous-requête pour obtenir la dernière progression de chaque objectif
        $subQuery = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(p_sub.goal)')
            ->from('App\Entity\Progress', 'p_sub')
            ->where('p_sub.date >= :cutoffDate');

        return $this->createQueryBuilder('g')
            ->andWhere('g.user = :user')
            ->andWhere('g.status = :status')
            ->andWhere('g.id NOT IN (' . $subQuery->getDQL() . ')')
            ->setParameter('user', $user)
            ->setParameter('status', 'active')
            ->setParameter('cutoffDate', $cutoffDate)
            ->orderBy('g.updatedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Dashboard : objectifs avec leurs dernières progressions
     */
    public function findForDashboard(User $user): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.category', 'c')
            ->leftJoin('g.metrics', 'm', 'WITH', 'm.isPrimary = true')
            ->leftJoin('g.progressEntries', 'p', 'WITH', 'p.metric = m')
            ->addSelect('c', 'm', 'p')
            ->andWhere('g.user = :user')
            ->andWhere('g.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'active')
            ->orderBy('g.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les objectifs similaires (même catégorie et mots-clés)
     */
    public function findSimilar(Goal $goal, int $limit = 5): array
    {
        $keywords = explode(' ', strtolower($goal->getTitle()));
        $keywords = array_filter($keywords, fn($word) => strlen($word) > 3);

        if (empty($keywords)) {
            return [];
        }

        $qb = $this->createQueryBuilder('g')
            ->andWhere('g.id != :goalId')
            ->andWhere('g.category = :category')
            ->andWhere('g.status = :status')
            ->setParameter('goalId', $goal->getId())
            ->setParameter('category', $goal->getCategory())
            ->setParameter('status', 'active')
            ->setMaxResults($limit);

        // Ajouter des conditions OR pour chaque mot-clé
        $orConditions = [];
        foreach ($keywords as $index => $keyword) {
            $orConditions[] = "g.title LIKE :keyword{$index}";
            $qb->setParameter("keyword{$index}", "%{$keyword}%");
        }

        if (!empty($orConditions)) {
            $qb->andWhere('(' . implode(' OR ', $orConditions) . ')');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Statistiques par catégorie pour un utilisateur
     */
    public function getStatsByCategory(User $user): array
    {
        return $this->createQueryBuilder('g')
            ->select('
                c.name as category_name,
                c.code as category_code,
                COUNT(g.id) as total_goals,
                SUM(CASE WHEN g.status = :completed THEN 1 ELSE 0 END) as completed_goals,
                AVG(CASE WHEN g.status = :active THEN 1 ELSE 0 END) as avg_completion
            ')
            ->leftJoin('g.category', 'c')
            ->andWhere('g.user = :user')
            ->groupBy('c.id', 'c.name', 'c.code')
            ->setParameter('user', $user)
            ->setParameter('completed', 'completed')
            ->setParameter('active', 'active')
            ->getQuery()
            ->getResult();
    }

    /**
     * Sauvegarde un objectif
     */
    public function save(Goal $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime un objectif
     */
    public function remove(Goal $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
