import { SettingsNav } from '@/components/settings-nav';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { deskRoute, formatMinutes, PRIORITY_LABELS } from '@/lib/desk';
import type { SharedData, SlaPolicy, TicketPriority } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function SlaSettings({ policies }: { policies: SlaPolicy[] }) {
    const { currentWorkspace } = usePage<SharedData>().props;
    const workspace = currentWorkspace!;
    const form = useForm({
        name: '',
        description: '',
        priority: 'any',
        first_response_minutes: 480,
        resolution_minutes: 4320,
        is_default: false,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            priority: data.priority === 'any' ? null : data.priority,
        }));
        form.post(deskRoute('desk.sla.store', workspace), { onSuccess: () => form.reset('name', 'description') });
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'SLA', href: deskRoute('desk.sla.index', workspace) }]}>
            <Head title="SLA policies" />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">SLA policies</h1>
                    <p className="text-muted-foreground text-sm">Priority-specific targets win over the default policy.</p>
                </div>
                <SettingsNav />
                <Card>
                    <CardHeader>
                        <CardTitle>New policy</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-3 md:grid-cols-2">
                            <Field label="Name" error={form.errors.name}>
                                <Input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} required />
                            </Field>
                            <Field label="Applies to priority">
                                <Select value={form.data.priority} onValueChange={(value) => form.setData('priority', value)}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="any">Any (fallback)</SelectItem>
                                            {Object.entries(PRIORITY_LABELS).map(([value, label]) => (
                                                <SelectItem key={value} value={value}>
                                                    {label}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field label="First response (minutes)" error={form.errors.first_response_minutes}>
                                <Input type="number" value={form.data.first_response_minutes} onChange={(event) => form.setData('first_response_minutes', Number(event.target.value))} />
                            </Field>
                            <Field label="Resolution (minutes)" error={form.errors.resolution_minutes}>
                                <Input type="number" value={form.data.resolution_minutes} onChange={(event) => form.setData('resolution_minutes', Number(event.target.value))} />
                            </Field>
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={form.data.is_default} onChange={(event) => form.setData('is_default', event.target.checked)} />
                                Default policy
                            </label>
                            <div className="md:col-span-2">
                                <Button type="submit" disabled={form.processing}>
                                    Create policy
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
                <div className="grid gap-3">
                    {policies.map((policy) => (
                        <Card key={policy.id}>
                            <CardContent className="flex flex-col justify-between gap-3 pt-6 md:flex-row md:items-center">
                                <div>
                                    <p className="font-medium">{policy.name}</p>
                                    <p className="text-muted-foreground text-sm">
                                        {policy.priority ? PRIORITY_LABELS[policy.priority as TicketPriority] : 'Any priority'}
                                        {policy.is_default ? ' · Default' : ''}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        First response {formatMinutes(policy.first_response_minutes)} · Resolution {formatMinutes(policy.resolution_minutes)}
                                    </p>
                                </div>
                                <Button
                                    variant="ghost"
                                    onClick={() => {
                                        if (confirm('Delete this SLA policy?')) {
                                            router.delete(deskRoute('desk.sla.destroy', workspace, { slaPolicy: policy.id }));
                                        }
                                    }}
                                >
                                    Delete
                                </Button>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
