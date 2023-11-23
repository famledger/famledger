<?php

namespace App\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Exception;

use App\Entity\EDoc;
use App\Service\EDocService;

#[AsDoctrineListener(event: Events::preUpdate)]
class EDocUpdateListener
{
    public function __construct(
        private readonly EDocService $eDocService
    ) {
    }

    /**
     * @throws Exception
     */
    public function preUpdate(PreUpdateEventArgs $eventArgs): void
    {
        $entity = $eventArgs->getObject();

        // Check if the entity is an EDoc and the filename property is being updated
        if ($entity instanceof EDoc && $eventArgs->hasChangedField('filename')) {
            $newFilename = $eventArgs->getNewValue('filename');
            $oldFilename = $eventArgs->getOldValue('filename');

            // Rename the associated file on disk
            $this->eDocService->renameFileOnDisk($entity, $oldFilename, $newFilename);
        }
    }
}
