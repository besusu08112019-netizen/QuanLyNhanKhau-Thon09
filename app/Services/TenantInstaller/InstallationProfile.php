<?php

namespace App\Services\TenantInstaller;

final class InstallationProfile
{
    /**
     * @param array<string,bool> $capabilities
     */
    public function __construct(
        private string $id,
        private string $name,
        private array $capabilities
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function supports(string $capability): bool
    {
        return (bool) ($this->capabilities[$capability] ?? false);
    }

    public function supportsVerifyDatabase(): bool
    {
        return $this->supports(InstallationCapabilities::VERIFY_DATABASE);
    }

    public function supportsImportSchema(): bool
    {
        return $this->supports(InstallationCapabilities::IMPORT_SCHEMA);
    }

    public function supportsCreateEnv(): bool
    {
        return $this->supports(InstallationCapabilities::CREATE_ENV);
    }

    public function supportsCreateAdmin(): bool
    {
        return $this->supports(InstallationCapabilities::CREATE_ADMIN);
    }

    public function supportsRollbackApplication(): bool
    {
        return $this->supports(InstallationCapabilities::ROLLBACK_APPLICATION);
    }

    public function supportsCreateDatabase(): bool
    {
        return $this->supports(InstallationCapabilities::CREATE_DATABASE);
    }

    public function supportsCreateDatabaseUser(): bool
    {
        return $this->supports(InstallationCapabilities::CREATE_DATABASE_USER);
    }

    public function supportsGrantDatabase(): bool
    {
        return $this->supports(InstallationCapabilities::GRANT_DATABASE);
    }

    public function supportsCreateVirtualHost(): bool
    {
        return $this->supports(InstallationCapabilities::CREATE_VIRTUAL_HOST);
    }

    public function supportsConfigureDns(): bool
    {
        return $this->supports(InstallationCapabilities::CONFIGURE_DNS);
    }

    public function supportsProvisionSsl(): bool
    {
        return $this->supports(InstallationCapabilities::PROVISION_SSL);
    }

    public function supportsProvisionStorage(): bool
    {
        return $this->supports(InstallationCapabilities::PROVISION_STORAGE);
    }

    public function capabilities(): array
    {
        $capabilities = [];
        foreach (InstallationCapabilities::all() as $capability) {
            $capabilities[$capability] = $this->supports($capability);
        }
        return $capabilities;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'capabilities' => $this->capabilities(),
        ];
    }
}
