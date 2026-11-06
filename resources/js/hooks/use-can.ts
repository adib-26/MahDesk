import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * Permission names for the current workspace, shared by the server from
 * App\Authorization\RolePermissions.
 *
 * Only use this to hide or show UI. The server checks every request.
 */
export type Permission =
    | 'desk.access'
    | 'tickets.view-all'
    | 'tickets.create'
    | 'tickets.assign'
    | 'tickets.delete'
    | 'tickets.internal-notes'
    | 'portal.access'
    | 'contacts.manage'
    | 'tags.manage'
    | 'kb.view'
    | 'kb.manage'
    | 'analytics.view'
    | 'workspace.manage'
    | 'workspace.delete'
    | 'members.manage'
    | 'teams.manage'
    | 'sla.manage'
    | 'automations.manage';

export function useCan(): (permission: Permission) => boolean {
    const { permissions } = usePage<SharedData>().props;
    const granted = new Set(Array.isArray(permissions) ? permissions : []);

    return (permission) => granted.has(permission);
}
