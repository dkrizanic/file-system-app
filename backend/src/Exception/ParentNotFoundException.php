<?php

declare(strict_types=1);

namespace App\Exception;

final class ParentNotFoundException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The parent folder does not exist.');
    }
}
