<?php

namespace App\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Persistence\ObjectManager;
use Exception;

use App\Annotation\TenantDependent;
use App\Entity\TenantAwareInterface;
use App\Service\TenantContext;

/**
 * Associates Tenant dependent entities (annotated with TenantDependent) with the tenant determined
 * by the TenantContext before a flush occurs (during the Doctrine preFlush event):
 *
 * @package App\EventListener
 */
#[AsDoctrineListener(event: Events::prePersist)]
class TenantListener
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {
    }

    /**
     * Assigns the current Tenant to entities with annotation TenantDependent.
     * If the annotated entity does not implement TenantAwareInterface, an exception is thrown.
     *
     * @throws Exception
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $tenantAware = $args->getObject();
        if ($this->isTenantDependent($args->getObjectManager(), get_class($tenantAware))) {
            //------------------------------------------------------------------------------------------------------
            // if entity is annotated as @TenantDependent it MUST implement TenantAwareInterface
            //------------------------------------------------------------------------------------------------------
            if (!$tenantAware instanceof TenantAwareInterface) {
                throw new Exception(sprintf('Entity %s is annotated as TenantDependent and must therefore implement TenantAwareInterface.',
                    get_class($tenantAware)
                ));
            }
            //------------------------------------------------------------------------------------------------------
            // only assign tenant if none has been assigned yet
            //------------------------------------------------------------------------------------------------------
            if (null === $tenantAware->getTenant()) {
                if (null === $tenant = $this->tenantContext->getTenant()) {
                    throw new Exception('No tenant could be determined');
                }
                $tenantAware->setTenant($tenant);
            }
        }
    }

    /**
     * Check if the provided class or one of its parent classes is tenant dependent
     */
    private function isTenantDependent(ObjectManager $em, ?string $className): bool
    {
        while(!empty($className)) {
            // some mapped super classes are not part of the symfony mapping and will trigger an exception
            try {
                $classMetaData = $em->getClassMetadata($className);
            } catch (Exception) {
                return false;
            }

            $reflectionClass = $classMetaData->getReflectionClass();

            if ($reflectionClass->getAttributes(TenantDependent::class)) {
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