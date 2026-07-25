<?php

declare(strict_types=1);

namespace Ai\Contracts;

interface PermissionAwareAiToolInterface extends AiToolInterface
{
    public function module(): string;

    public function action(): string;

    public function readOnly(): bool;
}

