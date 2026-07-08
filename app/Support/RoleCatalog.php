<?php

namespace App\Support;

class RoleCatalog
{
    // Canonical role buckets used across auth, routes, and resources.
    public const PLATFORM_ROLES = [
        'system_developer',
        'super_admin',
    ];

    public const TENANT_ROLES = [
        'tenant_owner',
        'tenant_admin',
        'agency_manager',
        'agency_staff',
    ];

    public const CUSTOMER_ROLE = 'customer';

    public const ALL_ROLES = [
        'system_developer',
        'super_admin',
        'tenant_owner',
        'tenant_admin',
        'agency_manager',
        'agency_staff',
        'customer',
    ];

    public static function normalize(?string $role): ?string
    {
        // Normalize incoming role strings so frontend and backend variants compare consistently.
        if ($role === null) {
            return null;
        }

        $role = strtolower(trim($role));
        $role = preg_replace('/[^a-z0-9]+/', '_', $role) ?? '';
        $role = trim($role, '_');

        return $role !== '' ? $role : null;
    }

    public static function isPlatformRole(?string $role): bool
    {
        $role = self::normalize($role);

        return $role !== null && in_array($role, self::PLATFORM_ROLES, true);
    }

    public static function isTenantRole(?string $role): bool
    {
        $role = self::normalize($role);

        return $role !== null && in_array($role, self::TENANT_ROLES, true);
    }

    public static function isCustomerRole(?string $role): bool
    {
        return self::normalize($role) === self::CUSTOMER_ROLE;
    }

    public static function label(?string $role): string
    {
        $role = self::normalize($role);

        if ($role === null) {
            return 'User';
        }

        return implode(' ', array_map(
            static fn (string $part) => ucfirst($part),
            explode('_', $role)
        ));
    }

    public static function priority(?string $role): int
    {
        $role = self::normalize($role);

        return match ($role) {
            'system_developer' => 1,
            'super_admin' => 2,
            'tenant_owner' => 3,
            'tenant_admin' => 4,
            'agency_manager' => 5,
            'agency_staff' => 6,
            'customer' => 7,
            default => 99,
        };
    }

    public static function membershipPriority(?string $role, ?string $status): int
    {
        // Active workspace memberships should outrank pending/restricted ones.
        $role = self::normalize($role);
        $status = self::normalize($status);

        if ($role !== null && self::isPlatformRole($role)) {
            return self::priority($role);
        }

        $statusWeight = match ($status) {
            'active' => 10,
            'pending' => 20,
            'suspended' => 30,
            'rejected' => 40,
            'archived' => 50,
            default => 90,
        };

        $membershipWeight = $statusWeight + self::priority($role);

        if ($role !== null && self::isCustomerRole($role)) {
            return 100 + $membershipWeight;
        }

        return $membershipWeight;
    }
}
