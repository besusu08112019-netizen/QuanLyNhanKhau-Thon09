<?php

declare(strict_types=1);

namespace Ai\Tools;

final class ToolPermissionDeniedException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $requirement
     */
    public function __construct(private readonly array $requirement = [])
    {
        parent::__construct('permission_denied');
    }

    /**
     * @return array<string, mixed>
     */
    public function requirement(): array
    {
        return $this->requirement;
    }
}
