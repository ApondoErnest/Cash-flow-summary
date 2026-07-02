<?php

declare(strict_types=1);

namespace App\Support\Navigation;

use App\Enums\UserRole;

final class RoleNavigation
{
    public static function shellContext(UserRole $role, ?string $centerName = null): ShellContext
    {
        return new ShellContext(
            role: $role,
            centerName: $centerName ?? ($role === UserRole::Owner
                ? __('navigation.shell.no_active_center')
                : __('navigation.shell.demo_center_name')),
            centerLabel: $role === UserRole::Owner
                ? __('navigation.shell.active_center')
                : __('navigation.shell.assigned_center'),
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
                new NavigationGroup(__('navigation.groups.operations'), self::ownerOperationalItems()),
                new NavigationGroup(__('navigation.groups.administration'), self::ownerAdministrativeItems()),
            ],
            UserRole::Manager => [
                new NavigationGroup(__('navigation.groups.operations'), self::managerOperationalItems()),
            ],
            UserRole::Cashier => [
                new NavigationGroup(__('navigation.groups.operations'), self::cashierOperationalItems()),
            ],
        };
    }

    /**
     * @return list<NavigationItem>
     */
    private static function ownerOperationalItems(): array
    {
        return [
            new NavigationItem(__('navigation.items.dashboard'), 'home', 'dashboard'),
            new NavigationItem(__('navigation.items.import_csv'), 'arrow-up-tray', 'imports.create'),
            new NavigationItem(__('navigation.items.imports'), 'inbox-stack', 'imports.index'),
            new NavigationItem(__('navigation.items.records'), 'document-currency-dollar', 'records.index'),
            new NavigationItem(__('navigation.items.daily_versions'), 'clock', 'daily-versions.index'),
            new NavigationItem(__('navigation.items.revisions'), 'check-badge', 'revisions.index'),
            new NavigationItem(__('navigation.items.reports'), 'chart-bar-square', 'reports.index'),
            new NavigationItem(__('navigation.items.anomalies'), 'exclamation-triangle', 'anomalies.index'),
            new NavigationItem(__('navigation.items.whatsapp_history'), 'chat-bubble-left-right', 'whatsapp-history.index'),
        ];
    }

    /**
     * @return list<NavigationItem>
     */
    private static function ownerAdministrativeItems(): array
    {
        return [
            new NavigationItem(__('navigation.items.manage_centers'), 'building-office-2', 'centers.index'),
            new NavigationItem(__('navigation.items.manage_users'), 'users', 'users.index'),
            new NavigationItem(__('navigation.items.organization_settings'), 'cog-6-tooth', 'settings.organization'),
            new NavigationItem(__('navigation.items.whatsapp_settings'), 'chat-bubble-left-right', 'settings.whatsapp'),
            new NavigationItem(__('navigation.items.security'), 'shield-check', 'security.index'),
            new NavigationItem(__('navigation.items.audit_logs'), 'clipboard-document-list', 'audit-logs.index'),
        ];
    }

    /**
     * @return list<NavigationItem>
     */
    private static function managerOperationalItems(): array
    {
        return [
            new NavigationItem(__('navigation.items.dashboard'), 'home', 'dashboard'),
            new NavigationItem(__('navigation.items.import_csv'), 'arrow-up-tray', 'imports.create'),
            new NavigationItem(__('navigation.items.imports'), 'inbox-stack', 'imports.index'),
            new NavigationItem(__('navigation.items.records'), 'document-currency-dollar', 'records.index'),
            new NavigationItem(__('navigation.items.reports'), 'chart-bar-square', 'reports.index'),
            new NavigationItem(__('navigation.items.revisions'), 'check-badge', 'revisions.index'),
        ];
    }

    /**
     * @return list<NavigationItem>
     */
    private static function cashierOperationalItems(): array
    {
        return [
            new NavigationItem(__('navigation.items.dashboard'), 'home', 'dashboard'),
            new NavigationItem(__('navigation.items.import_csv'), 'arrow-up-tray', 'imports.create'),
            new NavigationItem(__('navigation.items.imports'), 'inbox-stack', 'imports.index'),
        ];
    }
}
