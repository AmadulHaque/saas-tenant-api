<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListUsersRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class UserController extends Controller
{
    /**
     * List the company's users with pagination and filtering.
     */
    public function index(ListUsersRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->select(['id', 'name', 'email', 'role', 'company_id', 'created_at'])
            ->where('company_id', $request->user('api')->company_id)
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

    /**
     * Create a user in the authenticated user's company.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = new User;
        $user->forceFill([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'company_id' => $request->user('api')->company_id,
            'role' => $request->string('role')->toString(),
            'email_verified_at' => now(),
        ]);
        $user->save();

        return response()->json([
            'user' => new UserResource($user),
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Show a user of the authenticated user's company.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = User::query()
            ->select(['id', 'name', 'email', 'role', 'company_id', 'created_at'])
            ->where('company_id', $request->user('api')->company_id)
            ->find($id);

        abort_if($user === null, 404);

        $this->authorize('view', $user);

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Update a user of the authenticated user's company.
     */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = User::query()
            ->where('company_id', $request->user('api')->company_id)
            ->find($id);

        abort_if($user === null, 404);

        $this->authorize('update', $user);

        $user->fill($request->validated());

        if ($request->filled('role')) {
            $user->forceFill(['role' => $request->string('role')->toString()]);
        }

        $user->save();

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Soft-delete a user of the authenticated user's company.
     */
    public function destroy(Request $request, int $id): Response
    {
        $user = User::query()
            ->where('company_id', $request->user('api')->company_id)
            ->find($id);

        abort_if($user === null, 404);

        $this->authorize('delete', $user);

        $user->tokens()->update(['revoked' => true]);
        $user->delete();

        return response()->noContent();
    }
}
