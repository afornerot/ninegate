<?php

namespace App\Command;

use App\Service\IdentityProvider;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Orchestrateur de synchronisation d'identité.
 *
 * Dispatche vers la sous-commande appropriée selon SYNC_IDENTITY :
 *   - NINE2LDAP → app:identity:nine2ldap
 *   - LDAP2NINE → app:identity:ldap2nine
 *   - false     → affiche l'aide, aucune action
 */
#[AsCommand(
    name: 'app:identity:sync',
    description: 'Synchronise l\'identité (dispatch selon SYNC_IDENTITY)',
)]
class IdentitySyncCommand extends Command
{
    public function __construct(
        private IdentityProvider $identityProvider,
        private Application $application,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('user-id', null, InputOption::VALUE_OPTIONAL, 'Sync ciblée sur un user')
            ->addOption('group-id', null, InputOption::VALUE_OPTIONAL, 'Sync ciblée sur un group')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulation sans écriture')
            ->addOption('no-purge', null, InputOption::VALUE_NONE, 'Ne pas purger les orphelins');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $direction = $this->identityProvider->getSyncIdentity();

        if (IdentityProvider::SYNC_DISABLED === $direction) {
            $io->note('SYNC_IDENTITY=false. Aucune synchronisation configurée.');
            $io->listing([
                'app:identity:nine2ldap — pousse les données ninegate vers OpenLDAP',
                'app:identity:ldap2nine — importe les données OpenLDAP vers ninegate',
            ]);
            return Command::SUCCESS;
        }

        $subCommand = match ($direction) {
            IdentityProvider::SYNC_NINE2LDAP => 'app:identity:nine2ldap',
            IdentityProvider::SYNC_LDAP2NINE => 'app:identity:ldap2nine',
            default => null,
        };

        if (null === $subCommand) {
            $io->error(sprintf('SYNC_IDENTITY=%s invalide.', $direction));
            return Command::FAILURE;
        }

        // Règle de cohérence : LDAP2NINE nécessite que la source d'identité ne soit PAS ninegate (SQL).
        // NINE2LDAP reste OK même en MASTERIDENTITY=SQL : on alimente un annuaire local pour les
        // services tiers (Nextcloud, Gitea, etc.) à partir de notre source de vérité.
        if (IdentityProvider::SYNC_LDAP2NINE === $direction
            && IdentityProvider::MASTER_SQL === $this->identityProvider->getMasterIdentity()) {
            $io->error(sprintf(
                'SYNC_IDENTITY=%s incompatible avec APP_MASTERIDENTITY=SQL (la source de vérité est ninegate).',
                $direction
            ));
            return Command::FAILURE;
        }

        $subInput = new ArrayInput([
            '--user-id'  => $input->getOption('user-id'),
            '--group-id' => $input->getOption('group-id'),
            '--dry-run'  => $input->getOption('dry-run'),
            '--no-purge' => $input->getOption('no-purge'),
        ]);

        return $this->application->find($subCommand)->run($subInput, $output);
    }
}