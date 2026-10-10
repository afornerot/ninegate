<?php

namespace App\Message;

/**
 * Message déclenché lors d'une modification d'un User (username, lastname,
 * firstname, email, password).
 *
 * Routé sur le transport `async` puis consommé par `IdentitySyncHandler`
 * qui pousse les changements vers OpenLDAP et GLAuth.
 */
final class UserSyncMessage
{
    public function __construct(
        public readonly int $userId,
        public readonly string $action = 'upsert',
    ) {
    }
}
