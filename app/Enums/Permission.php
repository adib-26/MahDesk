<?php

namespace App\Enums;

/**
 * Everything a workspace role can be allowed to do.
 *
 * Which role gets which permission is decided in one place,
 * App\Authorization\RolePermissions. Each permission is registered as a Gate
 * that takes the workspace, for example:
 *
 *   Gate::authorize(Permission::ManageTeams->value, $workspace);
 *   Route::...->can(Permission::ManageTeams->value, 'workspace');
 *
 * Permissions say what a role may do in general. Policies (TicketPolicy,
 * ContactPolicy) then add record-level rules, such as a manager only
 * assigning tickets in their own teams or a customer only seeing their own
 * tickets.
 */
enum Permission: string
{
    // Agent desk
    case AccessDesk = 'desk.access';
    case ViewAllTickets = 'tickets.view-all';
    case CreateTickets = 'tickets.create';
    case AssignTickets = 'tickets.assign';
    case DeleteTickets = 'tickets.delete';
    case ViewInternalNotes = 'tickets.internal-notes';

    // Customer portal: customers act on tickets they own.
    case AccessPortal = 'portal.access';

    case ManageContacts = 'contacts.manage';
    case ManageTags = 'tags.manage';

    case ViewKnowledgeBase = 'kb.view';
    case ManageKnowledgeBase = 'kb.manage';

    case ViewAnalytics = 'analytics.view';

    // Workspace administration
    case ManageWorkspace = 'workspace.manage';
    case DeleteWorkspace = 'workspace.delete';
    case ManageMembers = 'members.manage';
    case ManageTeams = 'teams.manage';
    case ManageSla = 'sla.manage';
    case ManageAutomations = 'automations.manage';

    public function label(): string
    {
        return match ($this) {
            self::AccessDesk => 'Use the agent desk',
            self::ViewAllTickets => 'See every ticket in the workspace',
            self::CreateTickets => 'Create tickets',
            self::AssignTickets => 'Assign and move tickets',
            self::DeleteTickets => 'Delete tickets',
            self::ViewInternalNotes => 'Read and write internal notes',
            self::AccessPortal => 'Use the customer portal',
            self::ManageContacts => 'Create, edit and delete customers',
            self::ManageTags => 'Manage tags',
            self::ViewKnowledgeBase => 'Read the internal knowledge base',
            self::ManageKnowledgeBase => 'Write and publish knowledge base articles',
            self::ViewAnalytics => 'View reports',
            self::ManageWorkspace => 'Change workspace settings',
            self::DeleteWorkspace => 'Delete the workspace',
            self::ManageMembers => 'Invite and manage members',
            self::ManageTeams => 'Manage teams',
            self::ManageSla => 'Manage SLA policies',
            self::ManageAutomations => 'Manage automation rules',
        };
    }
}
