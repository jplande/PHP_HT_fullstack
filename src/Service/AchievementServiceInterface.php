<?php

namespace App\Service;

use App\Entity\User;

interface AchievementServiceInterface
{
    /**
     * Vérifie et déverrouille les badges pour un utilisateur
     *
     * @return array Tableau des badges déverrouillés
     */
    public function checkAndUnlockAchievements(User $user): array;
}
