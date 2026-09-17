<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListUsersRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Users', description: 'Tenant user management (owner/admin).')]
class IndexController extends Controller
{
    /**
     * List the company's users with pagination and filtering.
     */
    public function __invoke(ListUsersRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->select(['id', 'name', 'email', 'role', 'company_id', 'created_at'])
            ->forCompany($request->user('api')->company_id)
            ->when($request->filled('search'), fn ($query) => $query->search(
                $request->string('search')->toString(),
                ['name', 'email']
            ))
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')->toString()))
            ->orderBy('created_at')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return UserResource::collection($users);
    }
}
