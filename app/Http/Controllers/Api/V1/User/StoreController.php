<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\SubscriptionLimitService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

#[Group('Users', description: 'Tenant user management (owner/admin).')]
class StoreController extends Controller
{
    /**
     * Create a user in the authenticated user's company.
     */
    public function __invoke(StoreUserRequest $request, SubscriptionLimitService $limits): JsonResponse
    {
        $this->authorize('create', User::class);

        $actor = $request->user('api');

        $user = $limits->enforce($actor->company, 'max_users', function () use ($request, $actor): User {
            $user = new User;
            $user->forceFill([
                'name' => $request->string('name')->toString(),
                'email' => $request->string('email')->toString(),
                'password' => $request->string('password')->toString(),
                'company_id' => $actor->company_id,
                'role' => $request->string('role')->toString(),
                'email_verified_at' => now(),
            ]);
            $user->save();

            return $user;
        });

        return response()->json([
            'user' => new UserResource($user),
        ], SymfonyResponse::HTTP_CREATED);
    }
}
