<?php

namespace App\Repository;

use App\Entity\Family;
use App\Entity\Photo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Photo>
 */
class PhotoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Photo::class);
    }

    /** @return Photo[] */
    public function findByFamily(Family $family, int $limit = 200): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('IDENTITY(p.family) = :fid')->setParameter('fid', (int)$family->getId())
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }
}
