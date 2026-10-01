<?php

declare(strict_types=1);

namespace App\Exception;

final class ItemNotFoundException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The item does not exist.');
    }
}
