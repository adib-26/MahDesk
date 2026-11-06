import InputError from '@/components/input-error';
import { SettingsNav } from '@/components/settings-nav';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { CATEGORY_LABELS, CHANNEL_LABELS, deskRoute, PRIORITY_LABELS, relativeTime, STATUS_LABELS } from '@/lib/desk';
import type { AutomationRule, AutomationRuleRun, SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Play, Plus, Sparkles, Trash2 } from 'lucide-react';
import { FormEventHandler } from 'react';

interface StaffMember {
    id: number;
    name: string;
    role?: string;
}

interface TeamOption {
    id: number;
    name: string;
}

interface SlaPolicyOption {
    id: number;
    name: string;
    resolution_minutes: number;
}

const EVENT_LABELS: Record<AutomationRule['event'], string> = {
    ticket_created: 'When ticket is created',
    ticket_updated: 'When ticket is updated',
    ticket_escalation: 'Scheduled check · unresolved / SLA timer',
};

const fields = [
    { value: 'priority', label: 'Ticket priority' },
    { value: 'category', label: 'Category' },
    { value: 'status', label: 'Status' },
    { value: 'channel', label: 'Channel' },
    { value: 'team_id', label: 'Team' },
    { value: 'assignee_id', label: 'Assignee' },
    { value: 'subject', label: 'Subject' },
    { value: 'contact_email', label: 'Customer email' },
    { value: 'unresolved_hours', label: 'Unresolved for (hours)' },
    { value: 'unanswered_hours', label: 'Unanswered for (hours)' },
    { value: 'hours_until_sla_due', label: 'Hours until SLA due' },
] as const;

const numericFields = new Set(['unresolved_hours', 'unanswered_hours', 'hours_until_sla_due']);

const operatorsForField = (field: string) => {
    if (numericFields.has(field)) {
        return [
            { value: 'greater_than_or_equal', label: 'is at least (≥)' },
            { value: 'less_than_or_equal', label: 'is at most (≤)' },
            { value: 'equals', label: 'equals (=)' },
        ];
    }

    if (field === 'subject' || field === 'contact_email') {
        return [
            { value: 'contains', label: 'contains' },
            { value: 'equals', label: 'equals' },
            { value: 'not_equals', label: 'does not equal' },
        ];
    }

    return [
        { value: 'equals', label: 'is (=)' },
        { value: 'not_equals', label: 'is not (≠)' },
    ];
};

const actionTypes = [
    { value: 'assign_team', label: 'Assign to team' },
    { value: 'assign_agent', label: 'Assign to agent' },
    { value: 'escalate_to_manager', label: 'Escalate to manager' },
    { value: 'send_notification', label: 'Send notification' },
    { value: 'set_sla_hours', label: 'Set SLA target (hours)' },
    { value: 'set_sla_policy', label: 'Apply SLA policy' },
    { value: 'set_priority', label: 'Set priority' },
    { value: 'set_status', label: 'Set status' },
    { value: 'set_category', label: 'Set category' },
    { value: 'add_tag', label: 'Add tag' },
    { value: 'add_note', label: 'Add internal note' },
] as const;

const notificationTargets = [
    { value: 'assigned_team', label: 'Assigned team members' },
    { value: 'assignee', label: 'Assigned agent' },
    { value: 'managers', label: 'Workspace managers' },
    { value: 'admins', label: 'Organization admins' },
];

function defaultConditionValue(field: string, teams: TeamOption[], agents: StaffMember[]): string {
    switch (field) {
        case 'priority':
            return 'high';
        case 'category':
            return 'technical';
        case 'status':
            return 'open';
        case 'channel':
            return 'email';
        case 'team_id':
            return teams[0] ? String(teams[0].id) : '';
        case 'assignee_id':
            return agents[0] ? String(agents[0].id) : 'unassigned';
        case 'unresolved_hours':
            return '4';
        case 'unanswered_hours':
            return '2';
        case 'hours_until_sla_due':
            return '1';
        default:
            return '';
    }
}

function defaultActionValue(type: string, teams: TeamOption[], agents: StaffMember[], slaPolicies: SlaPolicyOption[]): string {
    switch (type) {
        case 'assign_team': {
            const tech = teams.find((t) => t.name.toLowerCase().includes('tech')) ?? teams[0];
            return tech ? String(tech.id) : 'Technical Team';
        }
        case 'assign_agent':
            return agents[0] ? String(agents[0].id) : '';
        case 'escalate_to_manager':
            return 'auto';
        case 'send_notification':
            return 'assigned_team';
        case 'set_sla_hours':
            return '2';
        case 'set_sla_policy':
            return slaPolicies[0] ? String(slaPolicies[0].id) : '';
        case 'set_priority':
            return 'high';
        case 'set_status':
            return 'open';
        case 'set_category':
            return 'technical';
        case 'add_tag':
            return 'vip';
        case 'add_note':
            return 'Routed automatically by workflow engine.';
        default:
            return '';
    }
}

export default function AutomationsSettings({
    rules,
    recentRuns = [],
    agents,
    teams = [],
    slaPolicies = [],
}: {
    rules: AutomationRule[];
    recentRuns?: AutomationRuleRun[];
    agents: StaffMember[];
    teams?: TeamOption[];
    slaPolicies?: SlaPolicyOption[];
}) {
    const { currentWorkspace } = usePage<SharedData>().props;
    const workspace = currentWorkspace!;

    const technicalTeam = teams.find((t) => t.name.toLowerCase().includes('tech')) ?? teams[0];

    const form = useForm<{
        name: string;
        event: AutomationRule['event'];
        is_active: boolean;
        conditions: { field: string; operator: string; value: string }[];
        actions: { type: string; value: string }[];
    }>({
        name: '',
        event: 'ticket_created',
        is_active: true,
        conditions: [
            { field: 'priority', operator: 'equals', value: 'high' },
            { field: 'category', operator: 'equals', value: 'technical' },
        ],
        actions: [
            { type: 'assign_team', value: technicalTeam ? String(technicalTeam.id) : 'Technical Team' },
            { type: 'send_notification', value: 'assigned_team' },
            { type: 'set_sla_hours', value: '2' },
        ],
    });

    const loadHighTechnicalPreset = () => {
        form.setData({
            name: 'Route high-priority technical tickets',
            event: 'ticket_created',
            is_active: true,
            conditions: [
                { field: 'priority', operator: 'equals', value: 'high' },
                { field: 'category', operator: 'equals', value: 'technical' },
            ],
            actions: [
                { type: 'assign_team', value: technicalTeam ? String(technicalTeam.id) : 'Technical Team' },
                { type: 'send_notification', value: 'assigned_team' },
                { type: 'set_sla_hours', value: '2' },
            ],
        });
    };

    const loadUnresolvedEscalationPreset = () => {
        form.setData({
            name: 'Escalate tickets unresolved after 4 hours',
            event: 'ticket_escalation',
            is_active: true,
            conditions: [{ field: 'unresolved_hours', operator: 'greater_than_or_equal', value: '4' }],
            actions: [{ type: 'escalate_to_manager', value: 'auto' }],
        });
    };

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.post(deskRoute('desk.automations.store', workspace), {
            onSuccess: () => form.reset('name'),
        });
    };

    const formatConditionText = (c: { field: string; operator: string; value: string }) => {
        const fieldLabel = fields.find((f) => f.value === c.field)?.label ?? c.field;
        const opSymbol =
            c.operator === 'equals'
                ? '='
                : c.operator === 'not_equals'
                  ? '≠'
                  : c.operator === 'greater_than_or_equal'
                    ? '≥'
                    : c.operator === 'less_than_or_equal'
                      ? '≤'
                      : c.operator;

        let valLabel = c.value;
        if (c.field === 'priority') valLabel = PRIORITY_LABELS[c.value as keyof typeof PRIORITY_LABELS]?.toUpperCase() ?? c.value.toUpperCase();
        if (c.field === 'category') valLabel = CATEGORY_LABELS[c.value as keyof typeof CATEGORY_LABELS] ?? c.value;
        if (c.field === 'status') valLabel = STATUS_LABELS[c.value as keyof typeof STATUS_LABELS] ?? c.value;
        if (c.field === 'channel') valLabel = CHANNEL_LABELS[c.value as keyof typeof CHANNEL_LABELS] ?? c.value;
        if (c.field === 'team_id') valLabel = teams.find((t) => String(t.id) === c.value)?.name ?? c.value;
        if (c.field === 'assignee_id') {
            valLabel = c.value === 'unassigned' ? 'Unassigned' : (agents.find((a) => String(a.id) === c.value)?.name ?? c.value);
        }
        if (numericFields.has(c.field)) valLabel = `${c.value} hours`;

        return `${fieldLabel} ${opSymbol} ${valLabel}`;
    };

    const formatActionText = (a: { type: string; value: string }) => {
        switch (a.type) {
            case 'assign_team': {
                const teamName = teams.find((t) => String(t.id) === a.value)?.name ?? a.value;
                return `assign to ${teamName}`;
            }
            case 'assign_agent': {
                const agentName = agents.find((ag) => String(ag.id) === a.value)?.name ?? `agent #${a.value}`;
                return `assign to ${agentName}`;
            }
            case 'escalate_to_manager': {
                if (!a.value || a.value === 'auto') {
                    return 'escalate to Manager';
                }
                const mgr = agents.find((ag) => String(ag.id) === a.value)?.name ?? 'Manager';
                return `escalate to ${mgr}`;
            }
            case 'send_notification': {
                const targetLabel = notificationTargets.find((t) => t.value === a.value)?.label ?? a.value;
                return `send notification (${targetLabel.toLowerCase()})`;
            }
            case 'set_sla_hours':
                return `set SLA = ${a.value} hours`;
            case 'set_sla_policy': {
                const policyName = slaPolicies.find((p) => String(p.id) === a.value)?.name ?? `policy #${a.value}`;
                return `apply SLA policy "${policyName}"`;
            }
            case 'set_priority':
                return `set priority = ${a.value.toUpperCase()}`;
            case 'set_status':
                return `set status = ${STATUS_LABELS[a.value as keyof typeof STATUS_LABELS] ?? a.value}`;
            case 'set_category':
                return `set category = ${CATEGORY_LABELS[a.value as keyof typeof CATEGORY_LABELS] ?? a.value}`;
            case 'add_tag':
                return `add tag "${a.value}"`;
            case 'add_note':
                return `add note "${a.value}"`;
            default:
                return `${a.type} ${a.value}`;
        }
    };

    const managers = agents.filter((a) => a.role === 'manager' || a.role === 'admin' || a.role === 'owner');

    return (
        <AppLayout breadcrumbs={[{ title: 'Automations', href: deskRoute('desk.automations.index', workspace) }]}>
            <Head title="Automations" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                    <div>
                        <p className="stamp-label text-muted-foreground">Workflow engine</p>
                        <h1 className="font-display text-3xl font-semibold tracking-tight">Automations</h1>
                        <p className="text-muted-foreground text-sm">
                            Compose multi-condition WHEN / AND / THEN rules for instant routing, SLA overrides, notifications, and time-based escalations.
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => router.post(deskRoute('desk.automations.run-escalations', workspace), {}, { preserveScroll: true })}
                    >
                        <Play data-icon="inline-start" />
                        Run escalation check now
                    </Button>
                </div>

                <SettingsNav />

                <Card>
                    <CardHeader className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <div>
                            <CardTitle>New workflow rule</CardTitle>
                            <CardDescription>All WHEN / AND conditions must match for the THEN actions to execute.</CardDescription>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button type="button" variant="outline" size="sm" onClick={loadHighTechnicalPreset}>
                                <Sparkles data-icon="inline-start" />
                                Preset: High + Technical → Team + SLA
                            </Button>
                            <Button type="button" variant="outline" size="sm" onClick={loadUnresolvedEscalationPreset}>
                                <Sparkles data-icon="inline-start" />
                                Preset: Unresolved 4h → Escalate
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="flex flex-col gap-6">
                            <div className="grid gap-3 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="rule-name">Rule name</Label>
                                    <Input
                                        id="rule-name"
                                        placeholder="e.g. Route high-priority technical tickets"
                                        value={form.data.name}
                                        onChange={(event) => form.setData('name', event.target.value)}
                                        required
                                    />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Trigger</Label>
                                    <Select
                                        value={form.data.event}
                                        onValueChange={(value) => form.setData('event', value as AutomationRule['event'])}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem value="ticket_created">Ticket created</SelectItem>
                                                <SelectItem value="ticket_updated">Ticket updated</SelectItem>
                                                <SelectItem value="ticket_escalation">Scheduled check (time-based escalation)</SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div className="flex flex-col gap-3 rounded-sm border p-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="stamp-label text-stamp">WHEN / AND</p>
                                        <p className="text-muted-foreground text-xs">Every condition below must be true.</p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            form.setData('conditions', [
                                                ...form.data.conditions,
                                                { field: 'category', operator: 'equals', value: 'technical' },
                                            ])
                                        }
                                    >
                                        <Plus data-icon="inline-start" />
                                        Add AND condition
                                    </Button>
                                </div>

                                {form.data.conditions.map((condition, index) => {
                                    const availableOperators = operatorsForField(condition.field);
                                    return (
                                        <div key={index} className="grid items-center gap-2 md:grid-cols-[4.5rem_1fr_1fr_1fr_auto]">
                                            <span className="stamp-label text-muted-foreground text-xs">
                                                {index === 0 ? 'WHEN' : 'AND'}
                                            </span>
                                            <Select
                                                value={condition.field}
                                                onValueChange={(newField) => {
                                                    const nextOps = operatorsForField(newField);
                                                    const nextOp = nextOps.some((o) => o.value === condition.operator)
                                                        ? condition.operator
                                                        : nextOps[0].value;
                                                    const next = [...form.data.conditions];
                                                    next[index] = {
                                                        field: newField,
                                                        operator: nextOp,
                                                        value: defaultConditionValue(newField, teams, agents),
                                                    };
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
                                                        {availableOperators.map((operator) => (
                                                            <SelectItem key={operator.value} value={operator.value}>
                                                                {operator.label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>

                                            {condition.field === 'priority' ? (
                                                <Select
                                                    value={condition.value}
                                                    onValueChange={(value) => {
                                                        const next = [...form.data.conditions];
                                                        next[index] = { ...condition, value };
                                                        form.setData('conditions', next);
                                                    }}
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectGroup>
                                                            {Object.entries(PRIORITY_LABELS).map(([val, label]) => (
                                                                <SelectItem key={val} value={val}>
                                                                    {label.toUpperCase()}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectGroup>
                                                    </SelectContent>
                                                </Select>
                                            ) : condition.field === 'category' ? (
                                                <Select
                                                    value={condition.value}
                                                    onValueChange={(value) => {
                                                        const next = [...form.data.conditions];
                                                        next[index] = { ...condition, value };
                                                        form.setData('conditions', next);
                                                    }}
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectGroup>
                                                            {Object.entries(CATEGORY_LABELS).map(([val, label]) => (
                                                                <SelectItem key={val} value={val}>
                                                                    {label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectGroup>
                                                    </SelectContent>
                                                </Select>
                                            ) : condition.field === 'status' ? (
                                                <Select
                                                    value={condition.value}
                                                    onValueChange={(value) => {
                                                        const next = [...form.data.conditions];
                                                        next[index] = { ...condition, value };
                                                        form.setData('conditions', next);
                                                    }}
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectGroup>
                                                            {Object.entries(STATUS_LABELS).map(([val, label]) => (
                                                                <SelectItem key={val} value={val}>
                                                                    {label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectGroup>
                                                    </SelectContent>
                                                </Select>
                                            ) : condition.field === 'channel' ? (
                                                <Select
                                                    value={condition.value}
                                                    onValueChange={(value) => {
                                                        const next = [...form.data.conditions];
                                                        next[index] = { ...condition, value };
                                                        form.setData('conditions', next);
                                                    }}
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectGroup>
                                                            {Object.entries(CHANNEL_LABELS).map(([val, label]) => (
                                                                <SelectItem key={val} value={val}>
                                                                    {label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectGroup>
                                                    </SelectContent>
                                                </Select>
                                            ) : condition.field === 'team_id' ? (
                                                <Select
                                                    value={condition.value}
                                                    onValueChange={(value) => {
                                                        const next = [...form.data.conditions];
                                                        next[index] = { ...condition, value };
                                                        form.setData('conditions', next);
                                                    }}
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Choose team" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectGroup>
                                                            {teams.map((team) => (
                                                                <SelectItem key={team.id} value={String(team.id)}>
                                                                    {team.name}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectGroup>
                                                    </SelectContent>
                                                </Select>
                                            ) : condition.field === 'assignee_id' ? (
                                                <Select
                                                    value={condition.value}
                                                    onValueChange={(value) => {
                                                        const next = [...form.data.conditions];
                                                        next[index] = { ...condition, value };
                                                        form.setData('conditions', next);
                                                    }}
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectGroup>
                                                            <SelectItem value="unassigned">Unassigned</SelectItem>
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
                                                    type={numericFields.has(condition.field) ? 'number' : 'text'}
                                                    min={numericFields.has(condition.field) ? '1' : undefined}
                                                    placeholder={numericFields.has(condition.field) ? 'Hours (e.g. 4)' : 'Value'}
                                                    value={condition.value}
                                                    onChange={(event) => {
                                                        const next = [...form.data.conditions];
                                                        next[index] = { ...condition, value: event.target.value };
                                                        form.setData('conditions', next);
                                                    }}
                                                />
                                            )}

                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                disabled={form.data.conditions.length <= 1}
                                                onClick={() =>
                                                    form.setData(
                                                        'conditions',
                                                        form.data.conditions.filter((_, i) => i !== index),
                                                    )
                                                }
                                                aria-label="Remove condition"
                                            >
                                                <Trash2 />
                                            </Button>
                                            <InputError
                                                className="md:col-span-5"
                                                message={(form.errors as Record<string, string>)[`conditions.${index}.value`]}
                                            />
                                        </div>
                                    );
                                })}
                                <InputError message={form.errors.conditions} />
                            </div>

                            <div className="flex flex-col gap-3 rounded-sm border p-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="stamp-label text-stamp">THEN / AND</p>
                                        <p className="text-muted-foreground text-xs">Actions run in sequence when the rule matches.</p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            form.setData('actions', [
                                                ...form.data.actions,
                                                { type: 'send_notification', value: 'assigned_team' },
                                            ])
                                        }
                                    >
                                        <Plus data-icon="inline-start" />
                                        Add action
                                    </Button>
                                </div>

                                {form.data.actions.map((action, index) => (
                                    <div key={index} className="grid items-center gap-2 md:grid-cols-[4.5rem_1fr_1fr_auto]">
                                        <span className="stamp-label text-muted-foreground text-xs">
                                            {index === 0 ? 'THEN' : 'AND'}
                                        </span>
                                        <Select
                                            value={action.type}
                                            onValueChange={(newType) => {
                                                const next = [...form.data.actions];
                                                next[index] = {
                                                    type: newType,
                                                    value: defaultActionValue(newType, teams, agents, slaPolicies),
                                                };
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

                                        {action.type === 'assign_team' ? (
                                            <Select
                                                value={action.value}
                                                onValueChange={(value) => {
                                                    const next = [...form.data.actions];
                                                    next[index] = { ...action, value };
                                                    form.setData('actions', next);
                                                }}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Choose team" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        {teams.map((team) => (
                                                            <SelectItem key={team.id} value={String(team.id)}>
                                                                {team.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                        ) : action.type === 'assign_agent' ? (
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
                                        ) : action.type === 'escalate_to_manager' ? (
                                            <Select
                                                value={action.value || 'auto'}
                                                onValueChange={(value) => {
                                                    const next = [...form.data.actions];
                                                    next[index] = { ...action, value };
                                                    form.setData('actions', next);
                                                }}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        <SelectItem value="auto">Team / workspace Manager (automatic)</SelectItem>
                                                        {managers.map((mgr) => (
                                                            <SelectItem key={mgr.id} value={String(mgr.id)}>
                                                                {mgr.name} ({mgr.role})
                                                            </SelectItem>
                                                        ))}
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                        ) : action.type === 'send_notification' ? (
                                            <Select
                                                value={action.value}
                                                onValueChange={(value) => {
                                                    const next = [...form.data.actions];
                                                    next[index] = { ...action, value };
                                                    form.setData('actions', next);
                                                }}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        {notificationTargets.map((target) => (
                                                            <SelectItem key={target.value} value={target.value}>
                                                                {target.label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                        ) : action.type === 'set_sla_policy' ? (
                                            <Select
                                                value={action.value}
                                                onValueChange={(value) => {
                                                    const next = [...form.data.actions];
                                                    next[index] = { ...action, value };
                                                    form.setData('actions', next);
                                                }}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Choose SLA policy" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        {slaPolicies.map((policy) => (
                                                            <SelectItem key={policy.id} value={String(policy.id)}>
                                                                {policy.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                        ) : action.type === 'set_priority' ? (
                                            <Select
                                                value={action.value}
                                                onValueChange={(value) => {
                                                    const next = [...form.data.actions];
                                                    next[index] = { ...action, value };
                                                    form.setData('actions', next);
                                                }}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        {Object.entries(PRIORITY_LABELS).map(([val, label]) => (
                                                            <SelectItem key={val} value={val}>
                                                                {label.toUpperCase()}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                        ) : action.type === 'set_status' ? (
                                            <Select
                                                value={action.value}
                                                onValueChange={(value) => {
                                                    const next = [...form.data.actions];
                                                    next[index] = { ...action, value };
                                                    form.setData('actions', next);
                                                }}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        {Object.entries(STATUS_LABELS).map(([val, label]) => (
                                                            <SelectItem key={val} value={val}>
                                                                {label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                        ) : action.type === 'set_category' ? (
                                            <Select
                                                value={action.value}
                                                onValueChange={(value) => {
                                                    const next = [...form.data.actions];
                                                    next[index] = { ...action, value };
                                                    form.setData('actions', next);
                                                }}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        {Object.entries(CATEGORY_LABELS).map(([val, label]) => (
                                                            <SelectItem key={val} value={val}>
                                                                {label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                        ) : (
                                            <Input
                                                type={action.type === 'set_sla_hours' ? 'number' : 'text'}
                                                min={action.type === 'set_sla_hours' ? '0.5' : undefined}
                                                step={action.type === 'set_sla_hours' ? '0.5' : undefined}
                                                value={action.value}
                                                placeholder={
                                                    action.type === 'set_sla_hours'
                                                        ? 'Hours (e.g. 2)'
                                                        : action.type === 'add_tag'
                                                          ? 'Tag name (e.g. vip)'
                                                          : 'Internal note text'
                                                }
                                                onChange={(event) => {
                                                    const next = [...form.data.actions];
                                                    next[index] = { ...action, value: event.target.value };
                                                    form.setData('actions', next);
                                                }}
                                            />
                                        )}

                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            disabled={form.data.actions.length <= 1}
                                            onClick={() =>
                                                form.setData(
                                                    'actions',
                                                    form.data.actions.filter((_, i) => i !== index),
                                                )
                                            }
                                            aria-label="Remove action"
                                        >
                                            <Trash2 />
                                        </Button>
                                        <InputError
                                            className="md:col-span-4"
                                            message={(form.errors as Record<string, string>)[`actions.${index}.value`]}
                                        />
                                    </div>
                                ))}
                                <InputError message={form.errors.actions} />
                            </div>

                            <div>
                                <Button type="submit" disabled={form.processing}>
                                    Create workflow rule
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <div className="grid gap-3">
                    {rules.map((rule, ruleIdx) => (
                        <Card key={rule.id}>
                            <CardContent className="flex flex-col justify-between gap-4 pt-6 md:flex-row md:items-start">
                                <div className="flex flex-col gap-2">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="stamp-label text-stamp">#{ruleIdx + 1}</span>
                                        <p className="font-display text-lg font-semibold tracking-tight">{rule.name}</p>
                                        <Badge variant="outline" className="font-mono text-[10px] uppercase">
                                            {EVENT_LABELS[rule.event] ?? rule.event}
                                        </Badge>
                                        <span className="text-muted-foreground text-xs">
                                            {rule.runs_count
                                                ? `Ran ${rule.runs_count} time${rule.runs_count === 1 ? '' : 's'} · last ${relativeTime(rule.runs_max_created_at ?? null)}`
                                                : 'Not triggered yet'}
                                        </span>
                                    </div>

                                    <div className="mt-1 grid gap-1.5 text-sm">
                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <span className="stamp-label text-muted-foreground w-12">WHEN</span>
                                            {rule.conditions.length === 0 ? (
                                                <Badge variant="secondary" className="font-mono text-xs">
                                                    Always
                                                </Badge>
                                            ) : (
                                                rule.conditions.map((c, idx) => (
                                                    <span key={idx} className="inline-flex items-center gap-1.5">
                                                        {idx > 0 && <span className="stamp-label text-muted-foreground">AND</span>}
                                                        <Badge variant="secondary" className="font-mono text-xs font-normal">
                                                            {formatConditionText(c)}
                                                        </Badge>
                                                    </span>
                                                ))
                                            )}
                                        </div>

                                        <div className="flex flex-wrap items-center gap-1.5">
                                            <span className="stamp-label text-stamp w-12">THEN</span>
                                            {rule.actions.map((a, idx) => (
                                                <span key={idx} className="inline-flex items-center gap-1.5">
                                                    {idx > 0 && <span className="stamp-label text-muted-foreground">AND</span>}
                                                    <Badge variant="outline" className="border-foreground/30 font-mono text-xs font-normal">
                                                        {formatActionText(a)}
                                                    </Badge>
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                                <div className="flex shrink-0 gap-2">
                                    <Button
                                        variant={rule.is_active ? 'default' : 'outline'}
                                        size="sm"
                                        onClick={() =>
                                            router.patch(
                                                deskRoute('desk.automations.update', workspace, { rule: rule.id }),
                                                { is_active: !rule.is_active },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {rule.is_active ? 'Active' : 'Paused'}
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            if (confirm('Delete this workflow rule?')) {
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

                <Card>
                    <CardHeader>
                        <CardTitle>Recent activity</CardTitle>
                        <CardDescription>The last 15 times a workflow rule changed a ticket.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-2">
                        {recentRuns.length === 0 && <p className="text-muted-foreground text-sm">No rule has fired yet.</p>}
                        {recentRuns.map((run) => (
                            <div
                                key={run.id}
                                className="flex flex-col justify-between gap-1 rounded-sm border p-3 text-sm sm:flex-row sm:items-start"
                            >
                                <div className="min-w-0">
                                    <p className="font-medium">
                                        {run.rule_name}
                                        {run.ticket_id && (
                                            <>
                                                {' → '}
                                                <Link
                                                    href={deskRoute('desk.tickets.show', workspace, { ticket: run.ticket_id })}
                                                    className="hover:underline"
                                                >
                                                    <span className="text-stamp font-mono text-xs">#{run.ticket_number}</span> {run.ticket_subject}
                                                </Link>
                                            </>
                                        )}
                                    </p>
                                    <p className="text-muted-foreground truncate text-xs">{run.actions.join(' · ')}</p>
                                </div>
                                <span className="text-muted-foreground shrink-0 text-xs">{relativeTime(run.created_at)}</span>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
