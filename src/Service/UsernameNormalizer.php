<?php

namespace App\Service;

class UsernameNormalizer
{
    public const PATTERN = '/^[a-z0-9._-]{3,180}$/';

    /**
     * Normalise une valeur brute en login ninegate conforme.
     * - lowercase UTF-8
     * - caractères non conformes remplacés par _
     * - collapse des underscores multiples
     * - pas de début/fin par _ . -
     *
     * @throws \InvalidArgumentException si le résultat est trop court
     */
    public function normalizeForUsername(string $raw): string
    {
        $value = mb_strtolower(trim($raw), 'UTF-8');
        $value = preg_replace('/[^a-z0-9._-]/u', '_', $value);
        $value = preg_replace('/_+/', '_', $value);
        $value = trim($value, '_-.');

        if (strlen($value) < 3) {
            throw new \InvalidArgumentException(sprintf(
                'Impossible de normaliser "%s" en un login ninegate valide (résultat trop court).',
                $raw
            ));
        }

        return $value;
    }

    public function isValid(string $value): bool
    {
        return (bool) preg_match(self::PATTERN, $value);
    }

    public static function getRegex(): string
    {
        return self::PATTERN;
    }
}