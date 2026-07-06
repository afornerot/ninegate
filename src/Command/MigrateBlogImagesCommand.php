<?php

namespace App\Command;

use App\Service\StorageService;
use Doctrine\DBAL\Connection;
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
        private Connection $connection,
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

        // Get all blog articles with images
        $articles = $this->connection->fetchAllAssociative(
            "SELECT id, blog_id, image FROM blog_article WHERE image IS NOT NULL AND image != ''"
        );

        $count = 0;
        $skipped = 0;
        $notFound = 0;

        foreach ($articles as $article) {
            $filename = basename($article['image']);
            $sourceFile = $migrationDir . '/' . $filename;

            if (!file_exists($sourceFile)) {
                $notFound++;
                continue;
            }

            $destPath = 'blog/' . $article['blog_id'] . '/blogarticle/' . $filename;

            if ($this->storage->fileExists($destPath)) {
                $skipped++;
                continue;
            }

            $contents = file_get_contents($sourceFile);
            $this->storage->write($destPath, $contents);
            $count++;
        }

        $io->success("$count migrated, $skipped already exist, $notFound source files missing");
        $io->text("Storage: " . ($this->storage->isS3() ? 'S3' : 'Local'));

        return Command::SUCCESS;
    }
}
