<?php

namespace App\Support;

final class SystemPermission
{
    public const ACCESS_CENTER = 'system.center.access';
    public const AUDIT_VIEW = 'system.audit.view';
    public const AUDIT_EXPORT = 'system.audit.export';
    public const AUDIT_FILTER_ADVANCED = 'system.audit.filter-advanced';
    public const TRASH_VIEW = 'system.trash.view';
    public const TRASH_RESTORE = 'system.trash.restore';
    public const TRASH_FORCE_DELETE = 'system.trash.force-delete';
    public const TRASH_EMPTY = 'system.trash.empty';
    public const ROLES_MANAGE = 'system.roles.manage';
    public const PERMISSIONS_MANAGE = 'system.permissions.manage';
    public const SUPER_ADMINISTRATORS_MANAGE = 'system.super-administrators.manage';
    public const SETTINGS_MANAGE = 'system.settings.manage';
    public const INTEGRATIONS_MANAGE = 'system.integrations.manage';
    public const TOOLS_ACCESS = 'system.tools.access';
    public const MONITORING_VIEW = 'system.monitoring.view';
    public const STATISTICS_VIEW = 'system.statistics.view';
    public const LOGS_VIEW = 'system.logs.view';
    public const MAINTENANCE_MANAGE = 'system.maintenance.manage';
    public const RECORDS_VIEW_ALL = 'system.records.view-all';
    public const BRANCHES_ACCESS_ALL = 'branches.access-all';

    public static function exclusive(): array
    {
        return [
            self::ACCESS_CENTER,
            self::AUDIT_VIEW,
            self::AUDIT_EXPORT,
            self::AUDIT_FILTER_ADVANCED,
            self::TRASH_VIEW,
            self::TRASH_RESTORE,
            self::TRASH_FORCE_DELETE,
            self::TRASH_EMPTY,
            self::ROLES_MANAGE,
            self::PERMISSIONS_MANAGE,
            self::SUPER_ADMINISTRATORS_MANAGE,
            self::SETTINGS_MANAGE,
            self::INTEGRATIONS_MANAGE,
            self::TOOLS_ACCESS,
            self::MONITORING_VIEW,
            self::STATISTICS_VIEW,
            self::LOGS_VIEW,
            self::MAINTENANCE_MANAGE,
            self::RECORDS_VIEW_ALL,
        ];
    }
}
