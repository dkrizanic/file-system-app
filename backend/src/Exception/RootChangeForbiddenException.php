<?php

declare(strict_types=1);

namespace App\Exception;

final class RootChangeForbiddenException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The root folder cannot be renamed or deleted.');
    }
}
