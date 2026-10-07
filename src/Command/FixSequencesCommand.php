<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:db:fix-sequences',
    description: 'Resynchronise les séquences PostgreSQL avec le max(id) de chaque table'
)]
class FixSequencesCommand extends Command
{
    public function __construct(
        private Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche les corrections sans les appliquer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        if ($dryRun) {
            $io->note('Mode dry-run : aucune modification ne sera effectuée.');
        }

        // Détection de PostgreSQL via la classe de la plateforme
        $platformClass = get_class($this->connection->getDatabasePlatform());
        if (!str_contains($platformClass, 'PostgreSQL')) {
            $io->warning(sprintf('Cette commande est spécifique à PostgreSQL (plateforme détectée : %s).', $platformClass));

            return Command::SUCCESS;
        }

        // Liste des tables avec une colonne id et une séquence.
        // On récupère la séquence via pg_get_serial_sequence directement
        // dans la requête SQL pour éviter les exceptions sur les tables
        // sans colonne id.
        $rows = $this->connection->fetchAllAssociative(
            "SELECT c.table_name,
                    pg_get_serial_sequence(quote_ident(c.table_schema) || '.' || quote_ident(c.table_name), 'id') AS seq
             FROM information_schema.columns c
             WHERE c.table_schema = 'public'
               AND c.column_name = 'id'
               AND (c.data_type = 'integer' OR c.data_type = 'bigint')
             ORDER BY c.table_name"
        );

        $tables = [];
        foreach ($rows as $row) {
            if (!empty($row['seq'])) {
                $tables[$row['table_name']] = $row['seq'];
            }
        }

        $fixed = 0;
        $checked = 0;
        foreach ($tables as $table => $seqName) {
            ++$checked;

            $seqValue = (int) $this->connection->fetchOne("SELECT last_value FROM $seqName");
            $maxId = (int) $this->connection->fetchOne("SELECT COALESCE(MAX(id), 0) FROM $table");

            if ($seqValue < $maxId) {
                $io->writeln(sprintf(
                    '  <comment>FIX</comment>  %s : séquence=%d, max(id)=%d → setval à %d',
                    $table,
                    $seqValue,
                    $maxId,
                    $maxId
                ));
                if (!$dryRun) {
                    $this->connection->executeStatement(
                        sprintf('SELECT setval(%s, ?)', $this->connection->getDatabasePlatform()->quoteStringLiteral($seqName)),
                        [$maxId]
                    );
                }
                ++$fixed;
            } else {
                $io->writeln(sprintf(
                    '  <info>OK</info>   %s : séquence=%d, max(id)=%d',
                    $table,
                    $seqValue,
                    $maxId
                ));
            }
        }

        $io->newLine();
        $io->success(sprintf(
            '%d table%s vérifiée%s, %d corrigée%s.',
            $checked,
            $checked > 1 ? 's' : '',
            $checked > 1 ? 's' : '',
            $fixed,
            $fixed > 1 ? 's' : ''
        ));

        return Command::SUCCESS;
    }
}