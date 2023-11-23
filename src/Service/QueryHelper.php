<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

class QueryHelper
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
    }

    public function getPropertyOptions(string $class, string $property): array
    {
        $options = $this->em->getRepository($class)->createQueryBuilder('a')
            ->select("DISTINCT a.$property")
            ->getQuery()
            ->getSingleColumnResult();

        return array_combine($options, $options);
    }
}
