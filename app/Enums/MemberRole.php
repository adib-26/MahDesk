<?php

namespace App\Enums;

use App\Authorization\RolePermissions;

enum MemberRole: string
{
    /**
     * Kept for backwards compatibility with existing workspaces. An owner is
     * an organization administrator with the additional ability to delete the
     * workspace.
     */
    case Owner = 'owner';

    /** Organization administrator. */
    case Admin = 'admin';

    /** Team-scoped manager. */
    case Manager = 'manager';

    /** Support agent. */
    case Agent = 'agent';

    /** Customer portal member. */
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Organization Admin',
            self::Manager => 'Manager',
            self::Agent => 'Support Agent',
            self::Customer => 'Customer',
        };
    }

    public function isOrganizationAdmin(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }

    public function isStaff(): bool
    {
        return $this !== self::Customer;
    }

    /** Whether this role has the permission. See App\Authorization\RolePermissions. */
    public function can(Permission $permission): bool
    {
        return RolePermissions::allows($this, $permission);
    }

    /** @return list<Permission> */
    public function permissions(): array
    {
        return RolePermissions::for($this);
    }

    public function canManageWorkspace(): bool
    {
        return $this->can(Permission::ManageWorkspace);
    }

    public function canManageMembers(): bool
    {
        return $this->can(Permission::ManageMembers);
    }

    public function canManageTeams(): bool
    {
        return $this->can(Permission::ManageTeams);
    }

    public function canViewAnalytics(): bool
    {
        return $this->can(Permission::ViewAnalytics);
    }

    public function canAccessAllWorkspaceTickets(): bool
    {
        return $this->can(Permission::ViewAllTickets);
    }

    public function canManageKnowledgeBase(): bool
    {
        return $this->can(Permission::ManageKnowledgeBase);
    }

    /**
     * @return list<self>
     */
    public static function staff(): array
    {
        return [self::Owner, self::Admin, self::Manager, self::Agent];
    }

    /**
     * @return list<string>
     */
    public static function staffValues(): array
    {
        return array_map(fn (self $role) => $role->value, self::staff());
    }

    /**
     * Roles that can be granted through an invitation.
     *
     * @return list<string>
     */
    public static function inviteableValues(): array
    {
        return [self::Admin->value, self::Manager->value, self::Agent->value];
    }
}
