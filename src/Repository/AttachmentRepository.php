<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use App\Entity\Attachment;
use App\Entity\Invoice;
use App\Entity\Statement;

/**
 * @extends ServiceEntityRepository<Attachment>
 *
 * @method Attachment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Attachment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Attachment[]    findAll()
 * @method Attachment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AttachmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attachment::class);
    }


    public function findPendingAttachments(Statement $statement)
    {
        $financialMonth = $statement->getFinancialMonth();
        $qb             = $this->createQueryBuilder('d');
        // Exclude documents that are already associated (with a transaction)
        // Legacy documents (isLegacy=true) are included if the document's financial month matches the statement
        $qb->where($qb->expr()->andX()
            ->add($qb->expr()->isNull('d.transaction'))
            ->add($qb->expr()->orX()
                ->add($qb->expr()->eq('d.isLegacy', $qb->expr()->literal(false)))
                ->add($qb->expr()->eq('d.financialMonth', $qb->expr()->literal($financialMonth->getId())))
            ));

        return $qb->getQuery()->getResult();
    }
}
