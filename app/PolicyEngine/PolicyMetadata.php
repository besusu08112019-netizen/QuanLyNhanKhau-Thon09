<?php

namespace App\PolicyEngine;

final class PolicyMetadata
{
    public function __construct(
        public readonly string $id,
        public readonly string $className,
        public readonly string $name,
        public readonly string $version,
        public readonly string $description,
        public readonly array $dependencies,
        public readonly string $status,
        public readonly string $testStatus,
        public readonly string $owner,
        public readonly ?string $error = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'className' => $this->className,
            'name' => $this->name,
            'version' => $this->version,
            'description' => $this->description,
            'dependencies' => $this->dependencies,
            'status' => $this->status,
            'testStatus' => $this->testStatus,
            'owner' => $this->owner,
            'error' => $this->error,
        ];
    }
}
