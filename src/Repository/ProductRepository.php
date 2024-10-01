<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findAllProductsWithPagination(int $page, int $limit = 3): array
    {
        $limit = abs($limit);

        $result = [];

        $queryBuilder = $this->getEntityManager()->createQueryBuilder()
            ->select('p')
            ->from('App\Entity\Product', 'p');

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
