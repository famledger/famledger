<?php

namespace App\Repository;

use App\Entity\FinancialMonth;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

use App\Constant\DocumentType;
use App\Entity\Document;
use App\Entity\Statement;

/**
 * @extends ServiceEntityRepository<Document>
 *
 * @method Document|null find($id, $lockMode = null, $lockVersion = null)
 * @method Document|null findOneBy(array $criteria, array $orderBy = null)
 * @method Document[]    findAll()
 * @method Document[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    public function findUnLinked(Statement $statement)
    {
        $qb = $this->getStatementQueryBuilder($statement);
        $qb
            ->andWhere($qb->expr()->neq('d.type', $qb->expr()->literal(DocumentType::ATTACHMENT->value)))
            ->andWhere($qb->expr()->isNull('d.transaction'));

        return $qb->getQuery()->getResult();
    }

    private function getStatementQueryBuilder(Statement $statement): QueryBuilder
    {
        $qb = $this->createQueryBuilder('d');
        $qb
            ->innerJoin('d.financialMonth', 'f')
            ->innerJoin('f.statement', 's')
            ->where($qb->expr()->eq('s', $qb->expr()->literal($statement->getId())));

        return $qb;
    }

    public function findByChecksum(string $checksum): ?Document
    {
        $qb = $this->createQueryBuilder('d');
        $qb->where($qb->expr()->andX()
            ->add($qb->expr()->eq('d.checksum', $qb->expr()->literal($checksum)))
        );

        return $qb->getQuery()->getOneOrNullResult();
    }
    public function findByChecksumForFinancialMonth(FinancialMonth $financialMonth, string $checksum): ?Document
    {
        $qb = $this->createQueryBuilder('d');
        $qb->where($qb->expr()->andX()
            ->add($qb->expr()->eq('d.checksum', $qb->expr()->literal($checksum)))
            ->add($qb->expr()->eq('d.financialMonth', $qb->expr()->literal($financialMonth->getId())))
        );

        return $qb->getQuery()->getOneOrNullResult();
    }
}
