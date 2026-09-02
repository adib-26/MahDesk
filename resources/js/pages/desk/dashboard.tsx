import { ChannelBadge, PriorityBadge, StatusBadge } from '@/components/desk-badges';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { deskRoute, formatMinutes, relativeTime } from '@/lib/desk';
import { type Analytics, type SharedData, type Ticket } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

interface Props {
    analytics: Analytics;
    recentTickets: Ticket[];
}

function Metric({ label, value, hint }: { label: string; value: string | number; hint?: string }) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-3xl">{value}</CardTitle>
            </CardHeader>
            {hint && <CardContent className="text-muted-foreground text-xs">{hint}</CardContent>}
        </Card>
    );
}

export default function DeskDashboard({ analytics, recentTickets }: Props) {
    const { currentWorkspace } = usePage<SharedData>().props;
    const workspace = currentWorkspace!;
    const maxSeries = Math.max(1, ...analytics.series.map((day) => Math.max(day.created, day.resolved)));

    return (
        <AppLayout breadcrumbs={[{ title: 'Dashboard', href: deskRoute('desk.dashboard', workspace) }]}>
            <Head title="Dashboard" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-2xl font-semibold">Support overview</h1>
                    <p className="text-muted-foreground text-sm">Volume, SLA health, and agent load for the last 30 days.</p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Metric label="Open tickets" value={analytics.kpis.open} hint={`${analytics.kpis.unassigned} unassigned`} />
                    <Metric label="Created today" value={analytics.kpis.createdToday} hint={`${analytics.kpis.resolvedThisWeek} resolved this week`} />
                    <Metric
                        label="Avg first response"
                        value={formatMinutes(analytics.kpis.avgFirstResponseMinutes)}
                        hint={`${analytics.kpis.breachingSoon} approaching SLA`}
                    />
                    <Metric
                        label="SLA compliance"
                        value={analytics.kpis.slaCompliance === null ? '—' : `${analytics.kpis.slaCompliance}%`}
                        hint={`Avg resolve ${formatMinutes(analytics.kpis.avgResolutionMinutes)}`}
                    />
                </div>

                <div className="grid gap-4 xl:grid-cols-3">
                    <Card className="xl:col-span-2">
                        <CardHeader>
                            <CardTitle>Created vs resolved</CardTitle>
                            <CardDescription>Daily ticket volume for the last 30 days</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex h-48 items-end gap-1">
                                {analytics.series.map((day) => (
                                    <div key={day.date} className="flex flex-1 flex-col justify-end gap-0.5" title={day.date}>
                                        <div
                                            className="bg-primary/80 w-full rounded-t-sm"
                                            style={{ height: `${(day.created / maxSeries) * 100}%`, minHeight: day.created ? 3 : 0 }}
                                        />
                                        <div
                                            className="w-full rounded-t-sm bg-emerald-500/70"
                                            style={{ height: `${(day.resolved / maxSeries) * 100}%`, minHeight: day.resolved ? 3 : 0 }}
                                        />
                                    </div>
                                ))}
                            </div>
                            <div className="text-muted-foreground mt-3 flex gap-4 text-xs">
                                <span className="flex items-center gap-1.5">
                                    <span className="bg-primary/80 size-2 rounded-sm" /> Created
                                </span>
                                <span className="flex items-center gap-1.5">
                                    <span className="size-2 rounded-sm bg-emerald-500/70" /> Resolved
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Open by status</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {analytics.byStatus.length === 0 && <p className="text-muted-foreground text-sm">No open tickets.</p>}
                            {analytics.byStatus.map((row) => (
                                <div key={row.name} className="flex items-center justify-between text-sm">
                                    <StatusBadge status={row.name as Ticket['status']} />
                                    <span className="font-medium">{row.count}</span>
                                </div>
                            ))}
                            <div className="mt-2 flex flex-col gap-2">
                                <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">Priority</p>
                                {analytics.byPriority.map((row) => (
                                    <div key={row.name} className="flex items-center justify-between text-sm">
                                        <PriorityBadge priority={row.name as Ticket['priority']} />
                                        <span>{row.count}</span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent tickets</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {recentTickets.length === 0 && <p className="text-muted-foreground text-sm">No tickets yet.</p>}
                            {recentTickets.map((ticket) => (
                                <Link
                                    key={ticket.id}
                                    href={deskRoute('desk.tickets.show', workspace, { ticket: ticket.id })}
                                    className="hover:bg-muted/60 flex items-start justify-between gap-3 rounded-lg border p-3"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">
                                            #{ticket.number} {ticket.subject}
                                        </p>
                                        <p className="text-muted-foreground truncate text-xs">
                                            {ticket.contact?.name} · {relativeTime(ticket.created_at)}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 flex-col items-end gap-1">
                                        <StatusBadge status={ticket.status} />
                                        <ChannelBadge channel={ticket.channel} />
                                    </div>
                                </Link>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Agent performance</CardTitle>
                            <CardDescription>Resolved in the last 30 days</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {analytics.agents.map((agent) => (
                                <div key={agent.id} className="flex items-center justify-between gap-3 rounded-lg border p-3 text-sm">
                                    <div>
                                        <p className="font-medium">{agent.name}</p>
                                        <p className="text-muted-foreground text-xs capitalize">
                                            {agent.role} · {agent.openTickets} open
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="font-medium">{agent.resolvedLast30Days} resolved</p>
                                        <p className="text-muted-foreground text-xs">avg {formatMinutes(agent.avgResolutionMinutes)}</p>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
