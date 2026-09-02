import { Button } from '@/components/ui/button';
import { deskRoute } from '@/lib/desk';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

const items = [
    { title: 'General', name: 'desk.settings.general' },
    { title: 'Agents', name: 'desk.members.index' },
    { title: 'Teams', name: 'desk.teams.index' },
    { title: 'SLA', name: 'desk.sla.index' },
    { title: 'Automations', name: 'desk.automations.index' },
] as const;

export function SettingsNav() {
    const { currentWorkspace } = usePage<SharedData>().props;
    const path = usePage().url.split('?')[0];

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
