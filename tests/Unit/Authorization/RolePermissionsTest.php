<?php

namespace Tests\Unit\Authorization;

use App\Authorization\RolePermissions;
use App\Enums\MemberRole;
use App\Enums\Permission;
use PHPUnit\Framework\TestCase;

class RolePermissionsTest extends TestCase
{
    public function test_every_role_has_the_expected_permissions(): void
    {
        $expected = [
            MemberRole::Owner->value => [
                'desk.access',
                'tickets.view-all',
                'tickets.create',
                'tickets.assign',
                'tickets.delete',
                'tickets.internal-notes',
                'contacts.manage',
                'tags.manage',
                'kb.view',
                'kb.manage',
                'analytics.view',
                'workspace.manage',
                'workspace.delete',
                'members.manage',
                'teams.manage',
                'sla.manage',
                'automations.manage',
            ],
            MemberRole::Admin->value => [
                'desk.access',
                'tickets.view-all',
                'tickets.create',
                'tickets.assign',
                'tickets.delete',
                'tickets.internal-notes',
                'contacts.manage',
                'tags.manage',
                'kb.view',
                'kb.manage',
                'analytics.view',
                'workspace.manage',
                'members.manage',
                'teams.manage',
                'sla.manage',
                'automations.manage',
            ],
            MemberRole::Manager->value => [
                'desk.access',
                'tickets.create',
                'tickets.assign',
                'tickets.internal-notes',
                'contacts.manage',
                'tags.manage',
                'kb.view',
                'analytics.view',
            ],
            MemberRole::Agent->value => [
                'desk.access',
                'tickets.internal-notes',
                'kb.view',
                'analytics.view',
            ],
            MemberRole::Customer->value => [
                'portal.access',
            ],
        ];

        $actual = array_map(
            function (array $permissions): array {
                $values = array_map(fn (Permission $permission) => $permission->value, $permissions);
                sort($values);

                return $values;
            },
            RolePermissions::map(),
        );
        array_walk($expected, fn (array &$permissions) => sort($permissions));

        $this->assertSame($expected, $actual);
        $this->assertSame(array_keys($expected), array_keys(RolePermissions::map()));
    }

    public function test_role_permissions_only_contain_declared_permissions(): void
    {
        $declared = array_map(
            fn (Permission $permission) => $permission->value,
            Permission::cases(),
        );

        foreach (RolePermissions::map() as $permissions) {
            $this->assertEmpty(array_diff(
                array_map(fn (Permission $permission) => $permission->value, $permissions),
                $declared,
            ));
        }
    }
}
