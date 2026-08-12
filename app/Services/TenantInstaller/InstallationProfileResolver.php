<?php

namespace App\Services\TenantInstaller;

use RuntimeException;

final class InstallationProfileResolver
{
    public const DEFAULT_PROFILE = 'shared_hosting_cpanel';

    public function resolve(?string $profileId = null): InstallationProfile
    {
        $id = strtolower(trim((string) ($profileId ?: self::DEFAULT_PROFILE)));

        return match ($id) {
            self::DEFAULT_PROFILE => $this->sharedHostingCpanel(),
            default => throw new RuntimeException('Installation Profile khÃ´ng Ä‘Æ°á»£c há»— trá»£: ' . $id),
        };
    }

    private function sharedHostingCpanel(): InstallationProfile
    {
        return new InstallationProfile(
            self::DEFAULT_PROFILE,
            'Shared Hosting cPanel',
            [
                InstallationCapabilities::VERIFY_DATABASE => true,
                InstallationCapabilities::IMPORT_SCHEMA => true,
                InstallationCapabilities::CREATE_ENV => true,
                InstallationCapabilities::CREATE_ADMIN => true,
                InstallationCapabilities::ROLLBACK_APPLICATION => true,
                InstallationCapabilities::CREATE_DATABASE => false,
                InstallationCapabilities::CREATE_DATABASE_USER => false,
                InstallationCapabilities::GRANT_DATABASE => false,
                InstallationCapabilities::CREATE_VIRTUAL_HOST => false,
                InstallationCapabilities::CONFIGURE_DNS => false,
                InstallationCapabilities::PROVISION_SSL => false,
                InstallationCapabilities::PROVISION_STORAGE => false,
            ]
        );
    }
}
