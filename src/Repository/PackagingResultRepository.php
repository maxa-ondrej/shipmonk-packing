<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PackagingResult;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<PackagingResult>
 */
class PackagingResultRepository extends EntityRepository {
    public function findOneByProductsCode(string $productsCode): ?PackagingResult {
        return $this->findOneBy(['productsCode' => $productsCode]);
    }
}
