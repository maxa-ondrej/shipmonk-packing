<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Packaging;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<Packaging>
 */
class PackagingRepository extends EntityRepository {
    /**
     * @return Packaging[]
     */
    public function findByAllowedWeight(float $weight): array {
        return $this->validateArrayType($this->createQueryBuilder('p')
            ->where('p.maxWeight >= :weight')
            ->setParameter('weight', $weight)
            ->getQuery()
            ->getArrayResult());
    }

    /**
     * @param mixed[] $input
     *
     * @return Packaging[]
     */
    private function validateArrayType(array $input): array {
        return array_filter($input, static fn ($item): bool => $item instanceof Packaging);
    }
}
