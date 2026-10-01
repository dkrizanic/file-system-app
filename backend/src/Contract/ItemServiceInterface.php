<?php

declare(strict_types=1);

namespace App\Contract;

use App\DTO\Read\ItemDetail;
use App\DTO\Read\ItemSummary;
use App\DTO\Read\Page;
use App\DTO\Read\SuggestionList;
use App\DTO\Write\CreateFile;
use App\DTO\Write\CreateFolder;
use App\DTO\Write\PaginationQuery;
use App\DTO\Write\RenameItem;
use App\DTO\Write\SearchQuery;
use App\DTO\Write\SuggestionQuery;
use Symfony\Component\Uid\Uuid;

interface ItemServiceInterface
{
    public function createFolder(CreateFolder $command): ItemSummary;

    public function createFile(CreateFile $command): ItemSummary;

    public function rename(Uuid $id, RenameItem $command): ItemSummary;

    public function delete(Uuid $id): void;

    /**
     * @return Page<ItemSummary>
     */
    public function listChildren(Uuid $folderId, PaginationQuery $query): Page;

    public function getItem(Uuid $id): ItemDetail;

    /**
     * @return Page<ItemDetail>
     */
    public function search(SearchQuery $query): Page;

    public function suggestions(SuggestionQuery $query): SuggestionList;
}
