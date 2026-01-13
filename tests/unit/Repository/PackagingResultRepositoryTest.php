<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PackagingResult;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Persisters\Entity\EntityPersister;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PackagingResultRepository::class)]
final class PackagingResultRepositoryTest extends TestCase {
    public function testFindOneByProductsCodeReturnsNull(): void {
        /** @var ClassMetadata<PackagingResult>&Stub $classMetadata */
        $classMetadata = self::createStub(ClassMetadata::class);
        $em = self::createMock(EntityManagerInterface::class);
        $unitOfWork = self::createMock(UnitOfWork::class);
        $entityPersister = self::createMock(EntityPersister::class);

        $classMetadata->name = PackagingResult::class;
        $em->expects($this->once())->method('getUnitOfWork')->willReturn($unitOfWork);
        $unitOfWork->expects($this->once())->method('getEntityPersister')
            ->with(PackagingResult::class)
            ->willReturn($entityPersister);
        $entityPersister->expects($this->once())->method('load')
            ->with(['productsCode' => 'abc'], null, null, [], null, 1, null)
            ->willReturn(null);

        $repo = new PackagingResultRepository($em, $classMetadata);
        $this->assertNull($repo->findOneByProductsCode('abc'));
    }

    public function testFindOneByProductsCodeReturnsObject(): void {
        /** @var ClassMetadata<PackagingResult>&Stub $classMetadata */
        $classMetadata = self::createStub(ClassMetadata::class);
        $em = self::createMock(EntityManagerInterface::class);
        $unitOfWork = self::createMock(UnitOfWork::class);
        $entityPersister = self::createMock(EntityPersister::class);
        $result = self::createStub(PackagingResult::class);

        $classMetadata->name = PackagingResult::class;
        $em->expects($this->once())->method('getUnitOfWork')->willReturn($unitOfWork);
        $unitOfWork->expects($this->once())->method('getEntityPersister')
            ->with(PackagingResult::class)
            ->willReturn($entityPersister);
        $entityPersister->expects($this->once())->method('load')
            ->with(['productsCode' => 'abc'], null, null, [], null, 1, null)
            ->willReturn($result);

        $repo = new PackagingResultRepository($em, $classMetadata);
        $this->assertSame($result, $repo->findOneByProductsCode('abc'));
    }
}
