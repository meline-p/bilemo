<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findAllUsersWithPagination(int $page, int $customer_id, int $limit = 3): array
    {
        $limit = abs($limit);

        $result = [];

        $queryBuilder = $this->getEntityManager()->createQueryBuilder()
            ->select('u', 'c')
            ->from('App\Entity\User', 'u')
            ->innerJoin('u.customer', 'c')
            ->where('c.id = :customer_id')
            ->setParameter('customer_id', $customer_id)
            ->orderBy('u.id', 'DESC');

        $queryBuilder->setMaxResults($limit)
            ->setFirstResult(($page * $limit) - $limit);

        $paginator = new Paginator($queryBuilder);
        $data = $paginator->getQuery()->getResult();

        if(empty($data)) {
            return $result;
        }

        $pages = ceil($paginator->count() / $limit);

        $result = [
            'data' => $data,
            'pages' => $pages,
            'page' => $page,
            'limit' => $limit
        ];

        return $result;
    }
}
