import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { deskRoute } from '@/lib/desk';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BookOpen, BookText, CheckSquare, LayoutGrid, Plus, Settings2, Users } from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { currentWorkspace, workspaces, memberRole } = usePage<SharedData>().props;
    const workspace = currentWorkspace ?? workspaces[0];
    const canManage = memberRole === 'owner' || memberRole === 'admin';

    const mainNavItems: NavItem[] = workspace
        ? [
              { title: 'Dashboard', url: deskRoute('desk.dashboard', workspace), icon: LayoutGrid },
              { title: 'Tickets', url: deskRoute('desk.tickets.index', workspace), icon: CheckSquare },
              { title: 'Customers', url: deskRoute('desk.contacts.index', workspace), icon: Users },
              { title: 'Knowledge base', url: deskRoute('desk.kb.index', workspace), icon: BookText },
              ...(canManage
                  ? [{ title: 'Settings', url: deskRoute('desk.settings.general', workspace), icon: Settings2 } satisfies NavItem]
                  : []),
          ]
        : [];

    const footerNavItems: NavItem[] = workspace
        ? [{ title: 'Public help center', url: route('help.index', workspace.slug), icon: BookOpen }]
        : [];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={workspace ? deskRoute('desk.dashboard', workspace) : route('dashboard')} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {workspaces.length > 0 && (
                    <SidebarGroup className="px-2 py-0">
                        <SidebarGroupLabel>Workspaces</SidebarGroupLabel>
                        <SidebarMenu>
                            {workspaces.map((item) => (
                                <SidebarMenuItem key={item.id}>
                                    <SidebarMenuButton asChild isActive={workspace?.slug === item.slug}>
                                        <Link href={deskRoute('desk.dashboard', item)} prefetch>
                                            <span className="truncate">{item.name}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                            <SidebarMenuItem>
                                <SidebarMenuButton asChild>
                                    <Link href={route('workspaces.create')}>
                                        <Plus />
                                        <span>New workspace</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroup>
                )}
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
