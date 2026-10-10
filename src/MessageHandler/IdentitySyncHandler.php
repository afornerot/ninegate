<?php

namespace App\MessageHandler;

use App\Entity\Group as AppGroup;
use App\Entity\User;
use App\Message\GroupSyncMessage;
use App\Message\UserGroupSyncMessage;
use App\Message\UserSyncMessage;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use App\Service\IdentityProvider;
use App\Service\IdentitySyncService;
use App\Service\LdapService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Consomme les messages de sync (temps réel) et pousse les changements
 * vers OpenLDAP.
 *
 * Stratégie :
 *   - On synchronise vers OpenLDAP si `SYNC_IDENTITY=NINE2LDAP`
 *   - En cas d'erreur, on throw pour que Messenger puisse retry (3 fois max)
 *   - Si le service n'est pas joignable, on throw → retry → si KO, msg
 *     dans le transport `failed`
 */
class IdentitySyncHandler
{
    public function __construct(
        private IdentityProvider $identityProvider,
        private IdentitySyncService $syncService,
        private UserRepository $userRepository,
        private GroupRepository $groupRepository,
        private LdapService $ldapService,
    ) {
    }

    #[AsMessageHandler]
    public function handleUser(UserSyncMessage $message): void
    {
        if (!$this->identityProvider->isSyncNineToLdap()) {
            return;
        }

        $user = $this->userRepository->find($message->userId);
        if (!$user) {
            // User supprimé, ne pas retry
            return;
        }

        try {
            $this->ldapService->bind();
        } catch (\Throwable $e) {
            throw new UnrecoverableMessageHandlingException('LDAP bind failed', 0, $e);
        }

        $this->syncService->syncUser($user);
    }

    #[AsMessageHandler]
    public function handleGroup(GroupSyncMessage $message): void
    {
        if (!$this->identityProvider->isSyncNineToLdap()) {
            return;
        }

        $group = $this->groupRepository->find($message->groupId);
        if (!$group) {
            return;
        }

        try {
            $this->ldapService->bind();
        } catch (\Throwable $e) {
            throw new UnrecoverableMessageHandlingException('LDAP bind failed', 0, $e);
        }

        $this->syncService->syncGroup($group);
    }

    #[AsMessageHandler]
    public function handleUserGroup(UserGroupSyncMessage $message): void
    {
        if (!$this->identityProvider->isSyncNineToLdap()) {
            return;
        }

        $group = $this->groupRepository->find($message->groupId);
        if (!$group) {
            return;
        }

        try {
            $this->ldapService->bind();
        } catch (\Throwable $e) {
            throw new UnrecoverableMessageHandlingException('LDAP bind failed', 0, $e);
        }

        $this->syncService->syncGroupMembers($group);
    }
}