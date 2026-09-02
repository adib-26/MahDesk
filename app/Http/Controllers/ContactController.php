<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\Contact;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request, Workspace $workspace): Response
    {
        Gate::authorize('viewAny', [Contact::class, $workspace]);

        $q = $request->query('q');
        $user = $request->user();
        $role = $workspace->roleOf($user);

        $contacts = $workspace->contacts()
            ->withCount('tickets')
            ->when(! $user->isSuperAdmin() && ! $role?->isOrganizationAdmin(), fn ($query) => $query->whereHas(
                'tickets',
                fn ($tickets) => $tickets->visibleTo($user, $workspace),
            ))
            ->when($q, fn ($query, $search) => $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('company', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('desk/contacts/index', [
            'contacts' => $contacts,
            'filters' => ['q' => $q],
        ]);
    }

    public function show(Workspace $workspace, Contact $contact): Response
    {
        abort_unless($contact->workspace_id === $workspace->id, 404);
        Gate::authorize('view', $contact);

        $tickets = $contact->tickets()
            ->visibleTo(request()->user(), $workspace)
            ->with(['assignee:id,name', 'tags:id,name,color'])
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('desk/contacts/show', [
            'contact' => $contact,
            'tickets' => $tickets,
        ]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        Gate::authorize('create', [Contact::class, $workspace]);

        $validated = $this->validated($request, $workspace);
        $validated['user_id'] = $this->customerUserId($validated['email']);

        $contact = $workspace->contacts()->create($validated);
        $this->ensureCustomerMembership($workspace, $contact);

        return back()->with('success', 'Contact created.');
    }

    public function update(Request $request, Workspace $workspace, Contact $contact): RedirectResponse
    {
        abort_unless($contact->workspace_id === $workspace->id, 404);
        Gate::authorize('update', $contact);

        $validated = $this->validated($request, $workspace, $contact);
        $validated['user_id'] = $this->customerUserId($validated['email']);

        $contact->update($validated);
        $this->ensureCustomerMembership($workspace, $contact->fresh());

        return back()->with('success', 'Contact updated.');
    }

    public function destroy(Workspace $workspace, Contact $contact): RedirectResponse
    {
        abort_unless($contact->workspace_id === $workspace->id, 404);
        Gate::authorize('delete', $contact);

        $contact->delete();

        return redirect()
            ->route('desk.contacts.index', $workspace)
            ->with('success', 'Contact deleted along with their tickets.');
    }

    private function validated(Request $request, Workspace $workspace, ?Contact $contact = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'email', 'max:150',
                Rule::unique('contacts', 'email')
                    ->where('workspace_id', $workspace->id)
                    ->ignore($contact?->id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'company' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function customerUserId(string $email): ?int
    {
        return User::query()
            ->where('email', Str::lower($email))
            ->value('id');
    }

    private function ensureCustomerMembership(Workspace $workspace, ?Contact $contact): void
    {
        if (! $contact?->user_id || $workspace->hasMember($contact->user_id)) {
            return;
        }

        $workspace->members()->attach($contact->user_id, ['role' => \App\Enums\MemberRole::Customer->value]);
    }
}
