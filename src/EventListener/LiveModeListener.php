<?php

namespace App\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use Exception;

use App\Annotation\LiveModeDependent;
use App\Entity\LiveModeAwareInterface;
use App\Service\LiveModeContext;

/**
 * Associates LiveMode dependent entities (annotated with LiveModeDependent) with the liveMode determined
 * by the LiveModeContext before a flush occurs (during the Doctrine preFlush event):
 *
 * @package App\EventListener
 */
#[AsDoctrineListener(event: Events::prePersist)]
class LiveModeListener
{
    public function __construct(
        private readonly LiveModeContext $liveModeProvider
    ) {
    }

    /**
     * Assigns the current LiveMode to entities with annotation LiveModeDependent.
     * If the annotated entity does not implement LiveModeAwareInterface, an exception is thrown.
     *
     * @throws Exception
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $liveModeAware = $args->getObject();
        if ($this->isLiveModeDependent($args->getObjectManager(), get_class($liveModeAware))) {
            //------------------------------------------------------------------------------------------------------
            // if entity is annotated as @LiveModeDependent it MUST implement LiveModeAwareInterface
            //------------------------------------------------------------------------------------------------------
            if (!$liveModeAware instanceof LiveModeAwareInterface) {
                throw new Exception(sprintf('Entity %s is annotated as LiveModeDependent and must therefore implement LiveModeAwareInterface.',
                    get_class($liveModeAware)
                ));
            }
            //------------------------------------------------------------------------------------------------------
            // only assign liveMode if none has been assigned yet
            //------------------------------------------------------------------------------------------------------
            if (null === $liveModeAware->getLiveMode()) {
                if (null === $liveMode = $this->liveModeProvider->getLiveMode()) {
                    throw new Exception('No liveMode could be determined');
                }
                $liveModeAware->setLiveMode($liveMode);
            }
        }
    }

    /**
     * Check if the provided class or one of its parent classes is liveMode dependent
     */
    private function isLiveModeDependent(ObjectManager $em, ?string $className): bool
    {
        while(!empty($className)) {
            // some mapped super classes are not part of the symfony mapping and will trigger an exception
            try {
                $classMetaData = $em->getClassMetadata($className);
            } catch (Exception) {
                return false;
            }

            $reflectionClass = $classMetaData->getReflectionClass();

            if ($reflectionClass->getAttributes(LiveModeDependent::class)) {
                return true;
            }

            if (false === $parentClass = $reflectionClass->getParentClass()) {
                return false;
            }

            $className = $parentClass->getName();
        }

        return false;
    }
}