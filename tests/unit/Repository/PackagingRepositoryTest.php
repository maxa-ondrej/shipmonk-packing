<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Packaging;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PackagingRepository::class)]
final class PackagingRepositoryTest extends TestCase {
    public function testFindByAllowedWeight(): void {
        /** @var ClassMetadata<Packaging>&Stub $classMetadata */
        $classMetadata = self::createStub(ClassMetadata::class);
        $em = self::createMock(EntityManagerInterface::class);
        $queryBuilder = self::createMock(QueryBuilder::class);
        $query = self::createMock(Query::class);
        $result = [self::createStub(Packaging::class), self::createStub(Packaging::class)];

        $classMetadata->name = Packaging::class;
        $em->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);
        $queryBuilder->expects($this->once())->method('select')->with('p')->willReturnSelf();
        $queryBuilder->expects($this->once())->method('from')->with(Packaging::class, 'p')->willReturnSelf();
        $queryBuilder->expects($this->once())->method('where')->with('p.maxWeight >= :weight')->willReturnSelf();
        $queryBuilder->expects($this->once())->method('setParameter')->with('weight', 1.0)->willReturnSelf();
        $queryBuilder->expects($this->once())->method('getQuery')->willReturn($query);
        $query->expects($this->once())->method('getArrayResult')->willReturn($result);

        $repo = new PackagingRepository($em, $classMetadata);
        $response = $repo->findByAllowedWeight(1.0);
        $this->assertCount(2, $response);
        $this->assertSame($result, $response);
    }
}
