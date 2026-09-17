<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * Return the authenticated user with their company context.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user('api');
        $user->loadMissing('company');

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }
}
