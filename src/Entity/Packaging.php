<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PackagingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use LogicException;

/**
 * Represents a box available in the warehouse.
 *
 * Warehouse workers pack a set of products for a given order into one of these boxes.
 */
#[ORM\Entity(repositoryClass: PackagingRepository::class)]
class Packaging {
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column(type: Types::FLOAT)]
    public float $width;

    #[ORM\Column(type: Types::FLOAT)]
    public float $height;

    #[ORM\Column(type: Types::FLOAT)]
    public float $length;

    #[ORM\Column(type: Types::FLOAT)]
    public float $maxWeight;

    public function __construct(float $width, float $height, float $length, float $maxWeight) {
        $this->width = $width;
        $this->height = $height;
        $this->length = $length;
        $this->maxWeight = $maxWeight;
    }

    public function getId(): int {
        if ($this->id === null) {
            throw new LogicException('ID is not set yet.');
        }

        return $this->id;
    }
}
