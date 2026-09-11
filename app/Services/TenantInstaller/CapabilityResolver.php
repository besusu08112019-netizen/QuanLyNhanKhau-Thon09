<?php

namespace App\Services\TenantInstaller;

final class CapabilityResolver
{
    public function supports(InstallationProfile $profile, string $capability): bool
    {
        return $profile->supports($capability);
    }

    public function capabilityMap(InstallationProfile $profile): array
    {
        return $profile->capabilities();
    }
}
