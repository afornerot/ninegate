<?php

namespace App\Message;

/**
 * Message déclenché lors d'une modification des membres d'un Group
 * (ajout ou retrait d'un UserGroup). Routé sur `async`.
 *
 * Consommé par `IdentitySyncHandler` qui resynchronise le `memberUID`
 * côté OpenLDAP et GLAuth.
 */
final class UserGroupSyncMessage
{
    public function __construct(
        public readonly int $groupId,
    ) {
    }
}
