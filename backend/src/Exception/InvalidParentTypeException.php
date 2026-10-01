<?php

declare(strict_types=1);

namespace App\Exception;

final class InvalidParentTypeException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The parent must be a folder, not a file.');
    }
}
