<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Users', description: 'Tenant user management (owner/admin).')]
class UpdateController extends Controller
{
    /**
     * Update a user of the authenticated user's company.
     */
    public function __invoke(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = User::query()
            ->forCompany($request->user('api')->company_id)
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
}
