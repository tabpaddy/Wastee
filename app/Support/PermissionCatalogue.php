<?php

namespace App\Support;

final class PermissionCatalogue
{
    public const PLATFORM_SUPER_ADMIN = 'Platform Super Admin';

    public static function platform(): array
    {
        return ['platform.access', 'platform.companies.view', 'platform.companies.review',
            'platform.companies.suspend', 'platform.users.view', 'platform.users.manage',
            'platform.roles.manage', 'platform.reports.view'];
    }

    public static function company(): array
    {
        return ['company.access', 'company.view', 'company.update',
            'staff.view', 'staff.create', 'staff.update', 'staff.deactivate', 'staff.invite',
            'roles.view', 'roles.create', 'roles.update', 'roles.assign',
            'residents.view', 'residents.create', 'residents.update',
            'properties.view', 'properties.create', 'properties.update',
            'service-plans.view', 'service-plans.create', 'service-plans.update',
            'billing.view', 'billing.create', 'billing.issue',
            'payments.view', 'payments.record', 'payments.verify',
            'collections.view', 'collections.manage', 'collections.record',
            'complaints.view', 'complaints.assign', 'complaints.resolve', 'reports.view'];
    }

    public static function platformRoles(): array
    {
        return [
            self::PLATFORM_SUPER_ADMIN => self::platform(),
            'Platform Admin' => ['platform.access', 'platform.companies.view',
                'platform.companies.review', 'platform.users.view', 'platform.reports.view'],
        ];
    }

    public static function companyRoles(): array
    {
        $basic = ['company.access', 'company.view'];

        return [
            'Owner' => self::company(),
            'Admin' => self::company(),
            'Accountant' => [...$basic, 'residents.view', 'properties.view', 'service-plans.view',
                'billing.view', 'billing.create', 'billing.issue', 'payments.view',
                'payments.record', 'payments.verify', 'reports.view'],
            'Customer Support' => [...$basic, 'residents.view', 'residents.create', 'residents.update',
                'properties.view', 'billing.view', 'payments.view', 'collections.view',
                'complaints.view', 'complaints.assign', 'complaints.resolve'],
            'Waste Collector' => [...$basic, 'properties.view', 'collections.view', 'collections.record'],
        ];
    }
}
