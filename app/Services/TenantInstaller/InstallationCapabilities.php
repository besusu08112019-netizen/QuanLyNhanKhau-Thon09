<?php

namespace App\Services\TenantInstaller;

final class InstallationCapabilities
{
    public const VERIFY_DATABASE = 'database.verify';
    public const IMPORT_SCHEMA = 'schema.import';
    public const CREATE_ENV = 'env.create';
    public const CREATE_ADMIN = 'admin.create';
    public const ROLLBACK_APPLICATION = 'application.rollback';
    public const CREATE_DATABASE = 'database.create';
    public const CREATE_DATABASE_USER = 'database.user.create';
    public const GRANT_DATABASE = 'database.grant';
    public const CREATE_VIRTUAL_HOST = 'domain.virtual_host.create';
    public const CONFIGURE_DNS = 'dns.configure';
    public const PROVISION_SSL = 'ssl.provision';
    public const PROVISION_STORAGE = 'storage.provision';

    public static function all(): array
    {
        return [
            self::VERIFY_DATABASE,
            self::IMPORT_SCHEMA,
            self::CREATE_ENV,
            self::CREATE_ADMIN,
            self::ROLLBACK_APPLICATION,
            self::CREATE_DATABASE,
            self::CREATE_DATABASE_USER,
            self::GRANT_DATABASE,
            self::CREATE_VIRTUAL_HOST,
            self::CONFIGURE_DNS,
            self::PROVISION_SSL,
            self::PROVISION_STORAGE,
        ];
    }
}
