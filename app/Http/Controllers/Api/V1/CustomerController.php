<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MemberRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexCustomersRequest;
use App\Http\Requests\Api\V1\SaveCustomerRequest;
use App\Http\Resources\V1\CustomerResource;
use App\Models\Contact;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function index(IndexCustomersRequest $request, Workspace $organization): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Contact::class, $organization]);

        $user = $request->user();
        $role = $organization->roleOf($user);

        $query = $organization->contacts()
            ->withCount('tickets')
            // Outside org admins, people only see customers whose tickets they can see.
            ->when(! $user->isSuperAdmin() && ! $role?->isOrganizationAdmin(), fn ($q) => $q->whereHas(
                'tickets',
                fn ($tickets) => $tickets->visibleTo($user, $organization),
            ))
            ->whereContains(['name', 'email', 'company'], $request->filter('q'))
            ->when($request->filter('company'), fn ($q, $company) => $q->where('company', $company))
            ->when($request->has('has_account'), fn ($q) => $request->boolean('has_account')
                ? $q->whereNotNull('user_id')
                : $q->whereNull('user_id'));

        return CustomerResource::collection(
            $request->applySort($query)->paginate($request->perPage())->withQueryString(),
        );
    }

    public function store(SaveCustomerRequest $request, Workspace $organization): JsonResponse
    {
        $contact = $organization->contacts()->create([
            ...$request->validated(),
            'user_id' => $this->customerUserId($request->validated('email')),
        ]);
        $this->ensureCustomerMembership($organization, $contact);

        return CustomerResource::make($contact->loadCount('tickets'))->response()->setStatusCode(201);
    }

    public function show(Workspace $organization, Contact $contact): CustomerResource
    {
        Gate::authorize('view', $contact);

        return CustomerResource::make($contact->loadCount('tickets'));
    }

    public function update(SaveCustomerRequest $request, Workspace $organization, Contact $contact): CustomerResource
    {
        $validated = $request->validated();

        if (array_key_exists('email', $validated)) {
            $validated['user_id'] = $this->customerUserId($validated['email']);
        }

        $contact->update($validated);
        $this->ensureCustomerMembership($organization, $contact);

        return CustomerResource::make($contact->loadCount('tickets'));
    }

    /** Deleting a customer also deletes their tickets. */
    public function destroy(Workspace $organization, Contact $contact): JsonResponse
    {
        Gate::authorize('delete', $contact);

        $contact->delete();

        return response()->json(null, 204);
    }

    private function customerUserId(string $email): ?int
    {
        return User::query()->where('email', Str::lower($email))->value('id');
    }

    private function ensureCustomerMembership(Workspace $organization, Contact $contact): void
    {
        if ($contact->user_id && ! $organization->hasMember($contact->user_id)) {
            $organization->members()->attach($contact->user_id, ['role' => MemberRole::Customer->value]);
        }
    }
}
