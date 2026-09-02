import { SettingsNav } from '@/components/settings-nav';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { deskRoute } from '@/lib/desk';
import type { AutomationRule, SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const fields = [
    { value: 'subject', label: 'Subject' },
    { value: 'priority', label: 'Priority' },
    { value: 'status', label: 'Status' },
    { value: 'channel', label: 'Channel' },
    { value: 'contact_email', label: 'Customer email' },
];

const operators = [
    { value: 'equals', label: 'equals' },
    { value: 'not_equals', label: 'does not equal' },
    { value: 'contains', label: 'contains' },
];

const actionTypes = [
    { value: 'set_priority', label: 'Set priority' },
    { value: 'set_status', label: 'Set status' },
    { value: 'assign_agent', label: 'Assign agent' },
    { value: 'add_tag', label: 'Add tag' },
    { value: 'add_note', label: 'Add internal note' },
];

export default function AutomationsSettings({ rules, agents }: { rules: AutomationRule[]; agents: { id: number; name: string }[] }) {
    const { currentWorkspace } = usePage<SharedData>().props;
    const workspace = currentWorkspace!;
    const form = useForm({
        name: '',
        event: 'ticket_created',
        is_active: true,
        conditions: [{ field: 'priority', operator: 'equals', value: 'urgent' }],
        actions: [{ type: 'add_tag', value: 'vip' }],
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.post(deskRoute('desk.automations.store', workspace), {
            onSuccess: () => form.reset('name'),
        });
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Automations', href: deskRoute('desk.automations.index', workspace) }]}>
            <Head title="Automations" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Automations</h1>
                    <p className="text-muted-foreground text-sm">When a ticket is created or updated, matching rules run in order.</p>
                </div>
                <SettingsNav />
                <Card>
                    <CardHeader>
                        <CardTitle>New rule</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="flex flex-col gap-4">
                            <div className="grid gap-3 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label>Name</Label>
                                    <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Event</Label>
                                    <Select value={form.data.event} onValueChange={(value) => form.setData('event', value)}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem value="ticket_created">Ticket created</SelectItem>
                                                <SelectItem value="ticket_updated">Ticket updated</SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label>Conditions (all must match)</Label>
                                {form.data.conditions.map((condition, index) => (
                                    <div key={index} className="grid gap-2 md:grid-cols-3">
                                        <Select
                                            value={condition.field}
                                            onValueChange={(value) => {
                                                const next = [...form.data.conditions];
                                                next[index] = { ...condition, field: value };
                                                form.setData('conditions', next);
                                            }}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {fields.map((field) => (
                                                        <SelectItem key={field.value} value={field.value}>
                                                            {field.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <Select
                                            value={condition.operator}
                                            onValueChange={(value) => {
                                                const next = [...form.data.conditions];
                                                next[index] = { ...condition, operator: value };
                                                form.setData('conditions', next);
                                            }}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {operators.map((operator) => (
                                                        <SelectItem key={operator.value} value={operator.value}>
                                                            {operator.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <Input
                                            value={condition.value}
                                            onChange={(event) => {
                                                const next = [...form.data.conditions];
                                                next[index] = { ...condition, value: event.target.value };
                                                form.setData('conditions', next);
                                            }}
                                        />
                                    </div>
                                ))}
                            </div>
                            <div className="flex flex-col gap-2">
                                <Label>Actions</Label>
                                {form.data.actions.map((action, index) => (
                                    <div key={index} className="grid gap-2 md:grid-cols-2">
                                        <Select
                                            value={action.type}
                                            onValueChange={(value) => {
                                                const next = [...form.data.actions];
                                                next[index] = { ...action, type: value };
                                                form.setData('actions', next);
                                            }}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {actionTypes.map((type) => (
                                                        <SelectItem key={type.value} value={type.value}>
                                                            {type.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        {action.type === 'assign_agent' ? (
                                            <Select
                                                value={action.value}
                                                onValueChange={(value) => {
                                                    const next = [...form.data.actions];
                                                    next[index] = { ...action, value };
                                                    form.setData('actions', next);
                                                }}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Choose agent" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        {agents.map((agent) => (
                                                            <SelectItem key={agent.id} value={String(agent.id)}>
                                                                {agent.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                        ) : (
                                            <Input
                                                value={action.value}
                                                placeholder={action.type === 'add_tag' ? 'billing' : action.type === 'set_priority' ? 'urgent' : 'value'}
                                                onChange={(event) => {
                                                    const next = [...form.data.actions];
                                                    next[index] = { ...action, value: event.target.value };
                                                    form.setData('actions', next);
                                                }}
                                            />
                                        )}
                                    </div>
                                ))}
                                <InputError message={form.errors.actions} />
                            </div>
                            <Button type="submit" disabled={form.processing}>
                                Create rule
                            </Button>
                        </form>
                    </CardContent>
                </Card>
                <div className="grid gap-3">
                    {rules.map((rule) => (
                        <Card key={rule.id}>
                            <CardContent className="flex flex-col justify-between gap-3 pt-6 md:flex-row md:items-start">
                                <div className="flex flex-col gap-1">
                                    <p className="font-medium">{rule.name}</p>
                                    <p className="text-muted-foreground text-xs">{rule.event.replace('_', ' ')}</p>
                                    <p className="text-sm">
                                        If {rule.conditions.map((c) => `${c.field} ${c.operator} ${c.value}`).join(' and ') || 'always'}
                                    </p>
                                    <p className="text-sm">Then {rule.actions.map((a) => `${a.type} ${a.value}`).join(', ')}</p>
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        variant={rule.is_active ? 'default' : 'outline'}
                                        size="sm"
                                        onClick={() =>
                                            router.patch(deskRoute('desk.automations.update', workspace, { rule: rule.id }), { is_active: !rule.is_active }, { preserveScroll: true })
                                        }
                                    >
                                        {rule.is_active ? 'On' : 'Off'}
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            if (confirm('Delete this rule?')) {
                                                router.delete(deskRoute('desk.automations.destroy', workspace, { rule: rule.id }));
                                            }
                                        }}
                                    >
                                        Delete
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
