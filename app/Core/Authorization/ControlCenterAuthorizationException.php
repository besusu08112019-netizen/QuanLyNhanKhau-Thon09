<?php

namespace App\Core\Authorization;

use RuntimeException;

final class ControlCenterAuthorizationException extends RuntimeException
{
    public function __construct(string $message, private int $status = 403)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }
}
