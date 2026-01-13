<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PackagingResultRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use LogicException;

/**
 * Represents a box available in the warehouse.
 *
 * Warehouse workers pack a set of products for a given order into one of these boxes.
 */
#[ORM\Entity(repositoryClass: PackagingResultRepository::class)]
class PackagingResult {
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column(type: Types::STRING, unique: true)]
    public string $productsCode;

    #[ORM\ManyToOne(targetEntity: Packaging::class)]
    #[ORM\JoinColumn(nullable: true)]
    public ?Packaging $packaging;

    public function __construct(
        string $productsCode,
        ?Packaging $packaging = null,
    ) {
        $this->productsCode = $productsCode;
        $this->packaging = $packaging;
    }

    public function getId(): int {
        if ($this->id === null) {
            throw new LogicException('ID is not set yet.');
        }

        return $this->id;
    }
}
