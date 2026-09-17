<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShowController extends Controller
{
    /**
     * Show a user of the authenticated user's company.
     */
    public function __invoke(Request $request, int $id): JsonResponse
    {
        $user = User::query()
            ->select(['id', 'name', 'email', 'role', 'company_id', 'created_at'])
            ->forCompany($request->user('api')->company_id)
            ->find($id);

        abort_if($user === null, 404);

        $this->authorize('view', $user);

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }
}
