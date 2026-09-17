<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\TenantRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthController extends Controller
{
    /**
     * Register a new company with its owner and issue an access token.
     */
    public function register(RegisterRequest $request, TenantRegistrationService $service): JsonResponse
    {
        $result = $service->register($request->validated());
        $result['user']->setRelation('company', $result['company']);

        return response()->json([
            'user' => new UserResource($result['user']),
            'company' => new CompanyResource($result['company']),
            'token' => $result['accessToken'],
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Authenticate a user and issue a personal access token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->toString())->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        $user->loadMissing('company');

        return response()->json([
            'user' => new UserResource($user),
            'token' => $user->createToken('api')->accessToken,
        ]);
    }

    /**
     * Revoke the access token used for the current request.
     */
    public function logout(Request $request): Response
    {
        $request->user('api')?->token()?->revoke();

        return response()->noContent();
    }
}
