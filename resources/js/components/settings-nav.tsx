import { Button } from '@/components/ui/button';
import { useCan, type Permission } from '@/hooks/use-can';
import { deskRoute } from '@/lib/desk';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

/** Settings pages and the permission each one needs (same as its route middleware). */
export const SETTINGS_PAGES: { title: string; name: string; permission: Permission }[] = [
    { title: 'General', name: 'desk.settings.general', permission: 'workspace.manage' },
    { title: 'Agents', name: 'desk.members.index', permission: 'members.manage' },
    { title: 'Teams', name: 'desk.teams.index', permission: 'teams.manage' },
    { title: 'SLA', name: 'desk.sla.index', permission: 'sla.manage' },
    { title: 'Automations', name: 'desk.automations.index', permission: 'automations.manage' },
];

export function SettingsNav() {
    const { currentWorkspace } = usePage<SharedData>().props;
    const path = usePage().url.split('?')[0];
    const can = useCan();
    const items = SETTINGS_PAGES.filter((item) => can(item.permission));

    if (!currentWorkspace) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-2">
            {items.map((item) => {
                const href = deskRoute(item.name, currentWorkspace);
                const active = path === href;

                return (
                    <Button key={item.name} variant={active ? 'default' : 'outline'} size="sm" asChild>
                        <Link href={href}>{item.title}</Link>
                    </Button>
                );
            })}
        </div>
    );
}
