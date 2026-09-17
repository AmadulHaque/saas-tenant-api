<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListUsersRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
            ->when($request->filled('search'), fn ($query) => $query->where(function ($query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $query->where('name', 'like', $term)->orWhere('email', 'like', $term);
            }))
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')->toString()))
            ->orderBy('created_at')
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return UserResource::collection($users);
    }
}
