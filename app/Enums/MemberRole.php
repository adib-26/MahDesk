<?php

namespace App\Enums;

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

    public function canManageWorkspace(): bool
    {
        return $this->isOrganizationAdmin();
    }

    public function canManageMembers(): bool
    {
        return $this->isOrganizationAdmin();
    }

    public function canManageTeams(): bool
    {
        return $this->isOrganizationAdmin();
    }

    public function canViewAnalytics(): bool
    {
        return $this->isOrganizationAdmin() || $this === self::Manager;
    }

    public function canAccessAllWorkspaceTickets(): bool
    {
        return $this->isOrganizationAdmin();
    }

    public function canManageKnowledgeBase(): bool
    {
        return $this->isOrganizationAdmin();
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
