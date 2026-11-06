<?php

namespace App\Authorization;

use App\Enums\MemberRole;
use App\Enums\Permission;

/**
 * The role -> permission matrix. This is the only place that decides what a
 * role may do; MemberRole helpers, Gates, policies, route middleware and the
 * permissions sent to the frontend all read from here.
 *
 * Some permissions are scoped further by policies:
 *   - Managers with CreateTickets / AssignTickets act only within their teams.
 *   - Managers with ViewAnalytics see reports only if they belong to a team.
 *   - Agents see tickets assigned to them (plus unassigned team tickets when
 *     their membership allows it).
 *   - Customers see only their own tickets and contact record.
 */
final class RolePermissions
{
    /** @var array<string, list<Permission>>|null */
    private static ?array $map = null;

    /** @return array<string, list<Permission>> */
    public static function map(): array
    {
        if (self::$map !== null) {
            return self::$map;
        }

        $agent = [
            Permission::AccessDesk,
            Permission::ViewInternalNotes,
            Permission::ViewKnowledgeBase,
        ];

        $manager = [
            ...$agent,
            Permission::CreateTickets,
            Permission::AssignTickets,
            Permission::ManageContacts,
            Permission::ManageTags,
            Permission::ViewAnalytics,
        ];

        $admin = [
            ...$manager,
            Permission::ViewAllTickets,
            Permission::DeleteTickets,
            Permission::ManageKnowledgeBase,
            Permission::ManageWorkspace,
            Permission::ManageMembers,
            Permission::ManageTeams,
            Permission::ManageSla,
            Permission::ManageAutomations,
        ];

        return self::$map = [
            MemberRole::Owner->value => [...$admin, Permission::DeleteWorkspace],
            MemberRole::Admin->value => $admin,
            MemberRole::Manager->value => $manager,
            MemberRole::Agent->value => $agent,
            MemberRole::Customer->value => [Permission::AccessPortal],
        ];
    }

    public static function allows(?MemberRole $role, Permission $permission): bool
    {
        return $role !== null && in_array($permission, self::map()[$role->value], true);
    }

    /** @return list<Permission> */
    public static function for(?MemberRole $role): array
    {
        return $role === null ? [] : self::map()[$role->value];
    }

    /** @return list<string> */
    public static function valuesFor(?MemberRole $role): array
    {
        return array_map(fn (Permission $permission) => $permission->value, self::for($role));
    }

    /** @return list<MemberRole> roles that have the permission */
    public static function rolesWith(Permission $permission): array
    {
        return array_values(array_filter(
            MemberRole::cases(),
            fn (MemberRole $role) => self::allows($role, $permission),
        ));
    }
}
