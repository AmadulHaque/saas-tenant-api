<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Me', description: 'Authenticated user context.')]
class ShowController extends Controller
{
    /**
     * Return the authenticated user with their company context.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user('api');
        $user->loadMissing('company');

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }
}
