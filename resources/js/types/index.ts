import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface WorkspaceSummary {
    id: number;
    name: string;
    slug: string;
    role?: string;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    workspaces: WorkspaceSummary[];
    currentWorkspace?: WorkspaceSummary | null;
    memberRole?: string | null;
    permissions?: string[];
    flash?: { success?: string | null; error?: string | null };
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    is_super_admin?: boolean;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

export interface InboundEmail {
    id: number;
    from: string;
    subject: string;
    body: string;
    html: string;
    status: string;
    status_reason: string | null;
    provider: string;
    created_at: string | null;
    ticket: { id: number; number: number } | null;
}

export type TicketStatus = 'open' | 'pending' | 'on_hold' | 'resolved' | 'closed';
export type TicketPriority = 'low' | 'normal' | 'high' | 'urgent';
export type TicketChannel = 'email' | 'web' | 'chat' | 'phone';
export type TicketCategory = 'general' | 'billing' | 'technical' | 'account' | 'shipping' | 'feature_request';

export interface Tag {
    id: number;
    name: string;
    color: string;
}

export interface Contact {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    company?: string | null;
    notes?: string | null;
    tickets_count?: number;
}

export interface Ticket {
    id: number;
    number: number;
    subject: string;
    status: TicketStatus;
    priority: TicketPriority;
    channel: TicketChannel;
    category: TicketCategory;
    contact_id: number;
    assignee_id: number | null;
    sla_policy_id: number | null;
    first_response_due_at: string | null;
    resolution_due_at: string | null;
    first_responded_at: string | null;
    resolved_at: string | null;
    closed_at: string | null;
    first_response_breached: boolean;
    resolution_breached: boolean;
    created_at: string;
    updated_at: string;
    contact?: Contact;
    assignee?: { id: number; name: string; email?: string } | null;
    tags?: Tag[];
    sla_policy?: SlaPolicy | null;
    messages?: TicketMessage[];
}

export interface TicketMessage {
    id: number;
    kind: 'reply' | 'note' | 'event';
    is_from_contact: boolean;
    body: string;
    created_at: string;
    author?: { id: number; name: string } | null;
    attachments?: { id: number; uuid: string; original_name: string; mime_type: string; size: number }[];
}

export interface SlaPolicy {
    id: number;
    name: string;
    description?: string | null;
    priority?: TicketPriority | null;
    first_response_minutes: number;
    resolution_minutes: number;
    is_default: boolean;
}

export interface AutomationRule {
    id: number;
    name: string;
    event: 'ticket_created' | 'ticket_updated' | 'ticket_escalation';
    conditions: { field: string; operator: string; value: string }[];
    actions: { type: string; value: string }[];
    is_active: boolean;
    position: number;
    runs_count?: number;
    runs_max_created_at?: string | null;
}

export interface AutomationRuleRun {
    id: number;
    rule_name: string | null;
    event: AutomationRule['event'];
    actions: string[];
    ticket_id: number | null;
    ticket_number: number | null;
    ticket_subject: string | null;
    created_at: string | null;
}

export interface KbCategory {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
    articles?: KbArticle[];
}

export interface KbArticle {
    id: number;
    kb_category_id: number;
    title: string;
    slug: string;
    excerpt?: string | null;
    body?: string;
    status: 'draft' | 'published';
    views: number;
    published_at?: string | null;
    updated_at: string;
    category?: { id: number; name: string; slug: string };
}

export interface Analytics {
    kpis: {
        totalTickets: number;
        open: number;
        resolvedTickets: number;
        slaBreaches: number;
        unassigned: number;
        breachingSoon: number;
        createdToday: number;
        resolvedThisWeek: number;
        avgFirstResponseMinutes: number | null;
        avgResolutionMinutes: number | null;
        slaCompliance: number | null;
        customerSatisfaction: number | null;
        csatResponses: number;
    };
    series: { date: string; created: number; resolved: number }[];
    byStatus: { name: string; count: number }[];
    /** Current open queue, retained separately from the reporting-period mix. */
    byPriority: { name: string; count: number }[];
    /** Tickets created in the same 30-day reporting period as `series`. */
    byPriorityInPeriod: { name: string; count: number }[];
    byChannel: { name: string; count: number }[];
    /** Tickets created in the reporting period, grouped by their category column. */
    byCategory: { name: TicketCategory; count: number }[];
    agents: {
        id: number;
        name: string;
        role: string;
        /** Current non-terminal tickets assigned to this agent. */
        ticketsAssigned: number;
        /** Resolved in the 30-day reporting window. */
        ticketsResolved: number;
        /** First responses on tickets attributed to the current assignee. */
        avgResponseMinutes: number | null;
        /** Resolution-SLA compliance for tickets resolved in the reporting window. */
        slaCompliance: number | null;
        /** No ratings are stored yet, so these are null and zero respectively. */
        customerRating: number | null;
        customerRatingResponses: number;
        /** Legacy fields retained for existing dashboard consumers. */
        openTickets: number;
        resolvedLast30Days: number;
        avgResolutionMinutes: number | null;
    }[];
}
