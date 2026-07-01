<?php

declare(strict_types=1);

namespace App\Support\Navigation;

use App\Enums\UserRole;

final class RoleNavigation
{
    public static function shellContext(UserRole $role): ShellContext
    {
        return new ShellContext(
            role: $role,
            centerName: 'Demo Technical Inspection Center',
            centerLabel: $role === UserRole::Owner ? 'Active center' : 'Assigned center',
            showsCenterSwitcher: $role === UserRole::Owner,
            navigationGroups: self::groupsFor($role),
        );
    }

    /**
     * @return list<NavigationGroup>
     */
    public static function groupsFor(UserRole $role): array
    {
        return match ($role) {
            UserRole::Owner => [
                new NavigationGroup('Operations', self::ownerOperationalItems()),
                new NavigationGroup('Administration', self::ownerAdministrativeItems()),
            ],
            UserRole::Manager => [
                new NavigationGroup('Operations', self::managerOperationalItems()),
            ],
            UserRole::Cashier => [
                new NavigationGroup('Operations', self::cashierOperationalItems()),
            ],
        };
    }

    /**
     * @return list<NavigationItem>
     */
    private static function ownerOperationalItems(): array
    {
        return [
            new NavigationItem('Dashboard', 'home', 'dashboard'),
            new NavigationItem('Import CSV', 'arrow-up-tray', 'imports.create'),
            new NavigationItem('Imports', 'inbox-stack', 'imports.index'),
            new NavigationItem('Records', 'document-currency-dollar', 'records.index'),
            new NavigationItem('Daily Versions', 'clock', 'daily-versions.index'),
            new NavigationItem('Revisions', 'check-badge', 'revisions.index'),
            new NavigationItem('Reports', 'chart-bar-square', 'reports.index'),
            new NavigationItem('Anomalies', 'exclamation-triangle', 'anomalies.index'),
            new NavigationItem('WhatsApp History', 'chat-bubble-left-right', 'whatsapp-history.index'),
        ];
    }

    /**
     * @return list<NavigationItem>
     */
    private static function ownerAdministrativeItems(): array
    {
        return [
            new NavigationItem('Manage Centers', 'building-office-2', 'centers.index'),
            new NavigationItem('Manage Users', 'users', 'users.index'),
            new NavigationItem('Organization Settings', 'cog-6-tooth', 'settings.organization'),
            new NavigationItem('WhatsApp Settings', 'chat-bubble-left-right', 'settings.whatsapp'),
            new NavigationItem('Security', 'shield-check', 'security.index'),
            new NavigationItem('Audit Logs', 'clipboard-document-list', 'audit-logs.index'),
        ];
    }

    /**
     * @return list<NavigationItem>
     */
    private static function managerOperationalItems(): array
    {
        return [
            new NavigationItem('Dashboard', 'home', 'dashboard'),
            new NavigationItem('Import CSV', 'arrow-up-tray', 'imports.create'),
            new NavigationItem('Imports', 'inbox-stack', 'imports.index'),
            new NavigationItem('Records', 'document-currency-dollar', 'records.index'),
            new NavigationItem('Reports', 'chart-bar-square', 'reports.index'),
            new NavigationItem('Revisions', 'check-badge', 'revisions.index'),
        ];
    }

    /**
     * @return list<NavigationItem>
     */
    private static function cashierOperationalItems(): array
    {
        return [
            new NavigationItem('Dashboard', 'home', 'dashboard'),
            new NavigationItem('Import CSV', 'arrow-up-tray', 'imports.create'),
            new NavigationItem('Imports', 'inbox-stack', 'imports.index'),
        ];
    }
}
