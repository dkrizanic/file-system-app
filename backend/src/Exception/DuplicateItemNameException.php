<?php

declare(strict_types=1);

namespace App\Exception;

final class DuplicateItemNameException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('An item with this name already exists in the parent folder.');
    }
}
