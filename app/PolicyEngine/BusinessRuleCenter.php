<?php

namespace App\PolicyEngine;

final class BusinessRuleCenter
{
    public function __construct(private readonly ?PolicyRegistry $registry = null)
    {
    }

    public function registry(): PolicyRegistry
    {
        return $this->registry ?? new PolicyRegistry();
    }

    public function health(): array
    {
        $policies = $this->registry()->all();
        $items = array_map(static fn(PolicyMetadata $policy) => $policy->toArray(), array_values($policies));
        $errors = array_values(array_filter($items, static fn(array $policy) => $policy['status'] === PolicyRegistry::STATUS_ERROR));
        $missingTests = array_values(array_filter($items, static fn(array $policy) => $policy['testStatus'] !== PolicyRegistry::TEST_PASS));

        return [
            'status' => !$errors ? PolicyRegistry::STATUS_READY : PolicyRegistry::STATUS_ERROR,
            'total' => count($items),
            'ready' => count(array_filter($items, static fn(array $policy) => $policy['status'] === PolicyRegistry::STATUS_READY)),
            'disabled' => count(array_filter($items, static fn(array $policy) => $policy['status'] === PolicyRegistry::STATUS_DISABLED)),
            'deprecated' => count(array_filter($items, static fn(array $policy) => $policy['status'] === PolicyRegistry::STATUS_DEPRECATED)),
            'error' => count($errors),
            'missingTests' => count($missingTests),
            'policies' => $items,
        ];
    }

    public function documentation(): array
    {
        return array_map(static function (PolicyMetadata $policy): array {
            return [
                'name' => $policy->name,
                'id' => $policy->id,
                'version' => $policy->version,
                'description' => $policy->description,
                'dependencies' => $policy->dependencies,
                'status' => $policy->status,
                'testStatus' => $policy->testStatus,
                'owner' => $policy->owner,
            ];
        }, array_values($this->registry()->all()));
    }
}
