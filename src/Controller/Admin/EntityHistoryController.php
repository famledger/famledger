<?php

namespace App\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Gedmo\Loggable\Entity\LogEntry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EntityHistoryController extends AbstractController
{
    #[Route('/admin/entityHistory/{class}/{id}', name: 'admin_entity_history', methods: ['GET'])]
    public function index(
        string                 $class,
        string                 $id,
        EntityManagerInterface $em,
        AdminUrlGenerator      $adminUrlGenerator
    ): Response {
        $logEntries = $em->getRepository(LogEntry::class)->findBy([
            'objectClass' => $class,
            'objectId'    => $id,
        ]);

        $entity           = $em->getRepository($class)->find($id);
        $shortClassName   = basename(str_replace('\\', '/', $class));
        $entityDetailsUrl = $adminUrlGenerator
            ->setController("App\\Controller\\Admin\\{$shortClassName}CrudController")
            ->setAction(Action::DETAIL)
            ->setEntityId($id)
            ->generateUrl();

        return $this->render('admin/EntityHistory/index.html.twig', [
            'class'            => $shortClassName,
            'id'               => $id,
            'logEntries'       => $logEntries,
            'entityDetailsUrl' => $entityDetailsUrl,
            'slug'             => $entity->__toString(),
        ]);
    }
}
