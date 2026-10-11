<?php

namespace App\Service;

class CronSchedule
{
    /**
     * Calcule la prochaine date d'exécution d'un cron.
     *
     * Le calcul se base sur la date planifiée (nextexecdate), pas sur
     * l'instant d'exécution : préserve les créneaux horaires fixes,
     * ex. « tous les jours à 05:00 ».
     * Si la date calculée est déjà passée (exécutions en retard,
     * conteneur arrêté…), celle-ci est perdue et on repart de maintenant.
     *
     * @param int        $interval    intervalle en secondes (>= 0 ; 0 = à chaque cycle de planification)
     * @param \DateTime  $now         l'instant du calcul
     * @param ?\DateTime $oldNextDate prochaine date planifiée, si déjà planifiée
     */
    public static function nextDate(int $interval, \DateTime $now, ?\DateTime $oldNextDate): \DateTime
    {
        $interval = max(0, $interval);

        if (0 === $interval) {
            return clone $now;
        }

        $next = (null !== $oldNextDate)
            ? (clone $oldNextDate)->add(new \DateInterval('PT'.$interval.'S'))
            : (clone $now)->add(new \DateInterval('PT'.$interval.'S'));

        if ($next < $now) {
            $next = (clone $now)->add(new \DateInterval('PT'.$interval.'S'));
        }

        return $next;
    }
}
