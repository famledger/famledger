<?php

namespace App\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Exception\EntityNotFoundException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use App\Entity\Tenant;
use App\Service\TenantContext;

class TenantExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TenantContext          $tenantContext,
    ) {
    }

    public
    static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof EntityNotFoundException) {
            $request  = $event->getRequest();
            $tenantId = $this->getEntityRecord($request);
            if ($tenantId === null or $tenantId === $this->tenantContext->getTenant()->getId()) {
                throw $exception;
            }
            $tenant = $this->em->getRepository(Tenant::class)->find($tenantId);
            $this->tenantContext->setTenant($tenant);

            $event->setResponse(new RedirectResponse($request->getUri()));
        }
    }

    private function getEntityRecord(Request $request): ?int
    {
        $crudControllerFqcn = $request->query->get('crudControllerFqcn');
        $entityId           = $request->query->get('entityId');
        $entityFqcn         = $crudControllerFqcn::getEntityFqcn();
        $metadata           = $this->em->getClassMetadata($entityFqcn);
        $tableName          = $metadata->getTableName();
        $query              = "SELECT tenant_id FROM $tableName WHERE id = $entityId";

        return $this->em->getConnection()->executeQuery($query)->fetchOne();
    }
}
