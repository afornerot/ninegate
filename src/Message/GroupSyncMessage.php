<?php

namespace App\Message;

/**
 * Message déclenché lors d'une modification d'un Group (slug, cn, name,
 * description, isOpen). Routé sur `async`.
 */
final class GroupSyncMessage
{
    public function __construct(
        public readonly int $groupId,
        public readonly string $action = 'upsert',
    ) {
    }
}
