<?php

namespace App\Command;

use App\Service\StorageService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:migrate-blogimages', description: 'Migrate blog article images from old ninegate to new storage')]
class MigrateBlogImagesCommand extends Command
{
    public function __construct(
        private StorageService $storage,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Migrate blog article images');

        $sourceDir = $_SERVER['APP_PROJECT_DIR'] ?? dirname(__DIR__, 2);
        $migrationDir = $sourceDir . '/misc/migration/blogarticle';

        if (!is_dir($migrationDir)) {
            $io->error("Directory not found: $migrationDir");
            return Command::FAILURE;
        }

        $files = glob($migrationDir . '/*');
        $count = 0;
        $skipped = 0;

        foreach ($files as $file) {
            $filename = basename($file);
            $destPath = 'blogarticle/' . $filename;

            if ($this->storage->fileExists($destPath)) {
                $skipped++;
                continue;
            }

            $contents = file_get_contents($file);
            $this->storage->write($destPath, $contents);
            $count++;
        }

        $io->success("Migration complete: $count images migrated, $skipped skipped (already exist)");
        $io->text("Storage type: " . ($this->storage->isS3() ? 'S3' : 'Local'));

        return Command::SUCCESS;
    }
}
