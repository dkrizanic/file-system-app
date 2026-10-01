<?php

declare(strict_types=1);

namespace App\DTO\Write;

use App\Validator\SearchTerm;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Uid\Uuid;

final class SearchQuery
{
    public function __construct(
        #[SearchTerm]
        public readonly string $name = '',
        public readonly SearchScope $scope = SearchScope::All,
        #[Assert\Uuid]
        #[Assert\When(
            expression: 'this.scope == constant(\'App\\\DTO\\\Write\\\SearchScope::Folder\')',
            constraints: [new Assert\NotNull(message: 'A folder scope requires a folderId.')],
        )]
        public readonly ?Uuid $folderId = null,
        #[Assert\Range(min: 1, max: 100, notInRangeMessage: 'The page size must be between {{ min }} and {{ max }}.')]
        public readonly int $limit = 50,
        #[Assert\PositiveOrZero(message: 'The page offset must not be negative.')]
        public readonly int $offset = 0,
    ) {
    }

    public function folderScopeId(): Uuid
    {
        return $this->folderId ?? throw new \LogicException('A folder scope requires a folderId; the When constraint rejects a missing one.');
    }
}
