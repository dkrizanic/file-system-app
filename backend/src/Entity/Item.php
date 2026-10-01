<?php

declare(strict_types=1);

namespace App\Entity;

use App\Service\NameNormalizer;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'item')]
class Item
{
    public const ROOT_ID = '1a0ef9c6-0000-7000-8000-000000000000';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?self $parent = null;

    #[ORM\Column(enumType: ItemType::class, length: 6)]
    private ItemType $type;

    #[ORM\Column(type: 'text')]
    private string $name;

    #[ORM\Column(name: 'normalized_name', type: 'text')]
    private string $normalizedName;

    public function __construct(string $name, ItemType $type, ?self $parent, NameNormalizer $normalizer)
    {
        $this->id = Uuid::v7();
        $this->type = $type;
        $this->parent = $parent;
        $this->applyName($name, $normalizer);
    }

    public function rename(string $name, NameNormalizer $normalizer): void
    {
        $this->applyName($name, $normalizer);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function getParentId(): ?Uuid
    {
        return $this->parent?->getId();
    }

    public function getType(): ItemType
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNormalizedName(): string
    {
        return $this->normalizedName;
    }

    private function applyName(string $name, NameNormalizer $normalizer): void
    {
        $this->name = $name;
        $this->normalizedName = $normalizer->normalize($name);
    }
}
