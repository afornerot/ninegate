<?php

namespace App\EventListener;

use Oneup\UploaderBundle\Event\PostPersistEvent;
use Oneup\UploaderBundle\Event\ValidationEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

final class UploadListener
{
    private string $projectDir;

    public function __construct(KernelInterface $kernel)
    {
        // Utiliser le projectDir pour construire le chemin
        $this->projectDir = $kernel->getProjectDir();
    }

    #[AsEventListener(event: 'oneup_uploader.validation')]
    public function onOneupUploaderValidation(ValidationEvent $event): void
    {
        // On s'assure que le repertoire de destination existe bien
        $fs = new Filesystem();
        $fs->mkdir($this->projectDir.'/public/uploads');
        $fs->mkdir($this->projectDir.'/public/uploads/'.$event->getType());
    }

    #[AsEventListener(event: 'oneup_uploader.post_persist')]
    public function onOneupUploaderPostPersit(PostPersistEvent $event): void
    {
        $file = $event->getFile();
        $type = $event->getType();
        $filename = $file->getFilename();
        $response = $event->getResponse();
        $response['file'] = $filename;
        $response['path'] = 'uploads/'.$type;
        $response['filepath'] = 'uploads/'.$type.'/'.$filename;
    }
}
