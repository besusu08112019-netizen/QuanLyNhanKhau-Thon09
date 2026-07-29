<?php

namespace App\PolicyEngine;

use ReflectionClass;
use Throwable;

final class PolicyRegistry
{
    public const STATUS_READY = 'READY';
    public const STATUS_DISABLED = 'DISABLED';
    public const STATUS_DEPRECATED = 'DEPRECATED';
    public const STATUS_ERROR = 'ERROR';

    public const TEST_PASS = 'PASS';
    public const TEST_MISSING = 'MISSING';

    public function __construct(
        private readonly ?string $policyDirectory = null,
        private readonly ?string $testDirectory = null,
    ) {
    }

    /** @return array<string,PolicyMetadata> */
    public function all(): array
    {
        $policies = [];
        foreach ($this->policyFiles() as $file) {
            $className = $this->classNameFromFile($file);
            $id = $this->idFromClass($className);
            $dependencies = $this->dependenciesFor($file, $className);

            try {
                if (!class_exists($className)) {
                    throw new \RuntimeException('Policy class cannot be autoloaded.');
                }
                $reflection = new ReflectionClass($className);
                $status = $this->statusFor($reflection);
                $error = null;
                $version = $this->constant($reflection, 'VERSION', '1.0.0');
                $description = $this->constant($reflection, 'DESCRIPTION', $this->humanName($id) . ' policy.');
                $owner = $this->constant($reflection, 'OWNER', 'Policy Engine');
            } catch (Throwable $e) {
                $status = self::STATUS_ERROR;
                $error = $e->getMessage();
                $version = 'unknown';
                $description = $this->humanName($id) . ' policy.';
                $owner = 'Policy Engine';
            }

            $policies[$id] = new PolicyMetadata(
                $id,
                $className,
                $this->humanName($id),
                $version,
                $description,
                $dependencies,
                $status,
                $this->testStatusFor($className),
                $owner,
                $error
            );
        }

        ksort($policies);
        return $policies;
    }

    public function find(string $id): ?PolicyMetadata
    {
        return $this->all()[$id] ?? null;
    }

    /** @return list<string> */
    private function policyFiles(): array
    {
        $directory = $this->policyDirectory();
        if (!is_dir($directory)) return [];

        $files = [];
        foreach (new \DirectoryIterator($directory) as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), 'Policy.php')) continue;
            $files[] = $file->getPathname();
        }
        sort($files);
        return $files;
    }

    private function policyDirectory(): string
    {
        return $this->policyDirectory ?? BASE_PATH . '/app/Policies';
    }

    private function testDirectory(): string
    {
        return $this->testDirectory ?? BASE_PATH . '/tests/policies';
    }

    private function classNameFromFile(string $file): string
    {
        return 'App\\Policies\\' . basename($file, '.php');
    }

    private function idFromClass(string $className): string
    {
        $short = preg_replace('/^.*\\\\/', '', $className) ?? $className;
        $short = preg_replace('/Policy$/', '', $short) ?? $short;
        $id = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $short));
        return str_replace('__', '_', $id);
    }

    private function humanName(string $id): string
    {
        return implode(' ', array_map(static fn($part) => ucfirst($part), explode('_', $id)));
    }

    /** @return list<string> */
    private function dependenciesFor(string $file, string $className): array
    {
        $source = (string) file_get_contents($file);
        $dependencies = [];

        foreach ($this->policyFiles() as $candidate) {
            $candidateClass = $this->classNameFromFile($candidate);
            if ($candidateClass === $className) continue;
            $candidateShort = basename($candidate, '.php');
            if (str_contains($source, $candidateShort . '::') || str_contains($source, $candidateClass . '::')) {
                $dependencies[] = $this->idFromClass($candidateClass);
            }
        }

        sort($dependencies);
        return array_values(array_unique($dependencies));
    }

    private function statusFor(ReflectionClass $reflection): string
    {
        if ($reflection->hasConstant('POLICY_DISABLED') && $reflection->getConstant('POLICY_DISABLED') === true) {
            return self::STATUS_DISABLED;
        }
        if (
            ($reflection->hasConstant('POLICY_DEPRECATED') && $reflection->getConstant('POLICY_DEPRECATED') === true)
            || str_contains((string) $reflection->getDocComment(), '@deprecated')
        ) {
            return self::STATUS_DEPRECATED;
        }
        return self::STATUS_READY;
    }

    private function constant(ReflectionClass $reflection, string $name, string $default): string
    {
        return $reflection->hasConstant($name) ? (string) $reflection->getConstant($name) : $default;
    }

    private function testStatusFor(string $className): string
    {
        if (!is_dir($this->testDirectory())) {
            return self::TEST_MISSING;
        }

        $short = preg_replace('/^.*\\\\/', '', $className) ?? $className;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->testDirectory(), \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) continue;
            if ($file->getFilename() === $short . 'Test.php') return self::TEST_PASS;
        }
        return self::TEST_MISSING;
    }
}
