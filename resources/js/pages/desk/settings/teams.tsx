import InputError from '@/components/input-error';
import { SettingsNav } from '@/components/settings-nav';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { deskRoute } from '@/lib/desk';
import type { SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { type FormEventHandler, useState } from 'react';

interface WorkspaceMember {
    id: number;
    name: string;
    email: string;
    role: string;
}

interface TeamMember {
    id: number;
    name: string;
    email: string;
}

interface Team {
    id: number;
    name: string;
    tickets_count: number;
    members: TeamMember[];
}

export default function TeamsSettings({ teams, members }: { teams: Team[]; members: WorkspaceMember[] }) {
    const { currentWorkspace } = usePage<SharedData>().props;
    const workspace = currentWorkspace!;
    const createForm = useForm({ name: '' });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        createForm.post(deskRoute('desk.teams.store', workspace), {
            onSuccess: () => createForm.reset(),
        });
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Teams', href: deskRoute('desk.teams.index', workspace) }]}>
            <Head title="Teams" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Teams</h1>
                    <p className="text-muted-foreground text-sm">Group workspace members so managers can work and report within their teams.</p>
                </div>

                <SettingsNav />

                <Card>
                    <CardHeader>
                        <CardTitle>New team</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="team-name">Team name</Label>
                                <Input
                                    id="team-name"
                                    value={createForm.data.name}
                                    onChange={(event) => createForm.setData('name', event.target.value)}
                                    placeholder="e.g. Billing"
                                    required
                                />
                                <InputError message={createForm.errors.name} />
                            </div>
                            <Button type="submit" disabled={createForm.processing}>
                                Create team
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                {teams.length === 0 ? (
                    <Card>
                        <CardContent className="text-muted-foreground py-10 text-center text-sm">
                            No teams yet. Create one to organize your support staff.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4">
                        {teams.map((team) => (
                            <TeamCard key={team.id} team={team} members={members} workspace={workspace} />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function TeamCard({ team, members, workspace }: { team: Team; members: WorkspaceMember[]; workspace: { slug: string } }) {
    const renameForm = useForm({ name: team.name });
    const addMemberForm = useForm({ member_id: '' });
    const [editingName, setEditingName] = useState(false);
    const teamMemberIds = new Set(team.members.map((member) => member.id));
    const availableMembers = members.filter((member) => !teamMemberIds.has(member.id));

    const rename: FormEventHandler = (event) => {
        event.preventDefault();
        renameForm.patch(deskRoute('desk.teams.update', workspace, { team: team.id }), {
            preserveScroll: true,
            onSuccess: () => setEditingName(false),
        });
    };

    const addMember: FormEventHandler = (event) => {
        event.preventDefault();
        addMemberForm.post(deskRoute('desk.teams.members.store', workspace, { team: team.id }), {
            preserveScroll: true,
            onSuccess: () => addMemberForm.reset('member_id'),
        });
    };

    return (
        <Card>
            <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    {editingName ? (
                        <form onSubmit={rename} className="flex flex-wrap items-center gap-2">
                            <Input
                                aria-label="Team name"
                                className="h-9 w-56"
                                value={renameForm.data.name}
                                onChange={(event) => renameForm.setData('name', event.target.value)}
                                required
                            />
                            <Button size="sm" type="submit" disabled={renameForm.processing}>
                                Save
                            </Button>
                            <Button size="sm" type="button" variant="ghost" onClick={() => setEditingName(false)}>
                                Cancel
                            </Button>
                            <InputError message={renameForm.errors.name} />
                        </form>
                    ) : (
                        <>
                            <CardTitle>{team.name}</CardTitle>
                            <p className="text-muted-foreground mt-1 text-sm">
                                {team.members.length} {team.members.length === 1 ? 'member' : 'members'} · {team.tickets_count} open or historical
                                tickets
                            </p>
                        </>
                    )}
                </div>
                {!editingName && (
                    <div className="flex shrink-0 items-center gap-2">
                        <Button size="sm" variant="outline" onClick={() => setEditingName(true)}>
                            Rename
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => {
                                if (confirm(`Delete ${team.name}? Tickets will remain, but lose their team assignment.`)) {
                                    router.delete(deskRoute('desk.teams.destroy', workspace, { team: team.id }), { preserveScroll: true });
                                }
                            }}
                        >
                            Delete
                        </Button>
                    </div>
                )}
            </CardHeader>
            <CardContent className="grid gap-5">
                <form onSubmit={addMember} className="flex flex-col gap-2 sm:flex-row">
                    <Select value={addMemberForm.data.member_id} onValueChange={(value) => addMemberForm.setData('member_id', value)}>
                        <SelectTrigger className="sm:max-w-sm">
                            <SelectValue placeholder="Add a workspace member" />
                        </SelectTrigger>
                        <SelectContent>
                            {availableMembers.map((member) => (
                                <SelectItem key={member.id} value={String(member.id)}>
                                    {member.name} · {member.role.replace('_', ' ')}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button type="submit" disabled={!addMemberForm.data.member_id || addMemberForm.processing}>
                        Add member
                    </Button>
                    <InputError message={addMemberForm.errors.member_id} />
                </form>

                {team.members.length === 0 ? (
                    <p className="text-muted-foreground text-sm">No workspace members are assigned to this team.</p>
                ) : (
                    <div className="flex flex-wrap gap-2">
                        {team.members.map((member) => (
                            <Badge key={member.id} variant="secondary" className="gap-1.5 py-1 pr-1 pl-2.5">
                                <span>{member.name}</span>
                                <button
                                    type="button"
                                    aria-label={`Remove ${member.name} from ${team.name}`}
                                    className="text-muted-foreground hover:bg-background hover:text-foreground rounded-sm px-1"
                                    onClick={() =>
                                        router.delete(deskRoute('desk.teams.members.destroy', workspace, { team: team.id, member: member.id }), {
                                            preserveScroll: true,
                                        })
                                    }
                                >
                                    ×
                                </button>
                            </Badge>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
