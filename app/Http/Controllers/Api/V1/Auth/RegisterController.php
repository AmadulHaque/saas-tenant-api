<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\UserResource;
use App\Services\TenantRegistrationService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

#[Group('Authentication', description: 'Register, login, and logout with Passport personal access tokens.')]
class RegisterController extends Controller
{
    /**
     * Register a new company with its owner and issue an access token.
     */
    public function __invoke(RegisterRequest $request, TenantRegistrationService $service): JsonResponse
    {
        $result = $service->register($request->validated());
        $result['user']->setRelation('company', $result['company']);

        return response()->json([
            'user' => new UserResource($result['user']),
            'company' => new CompanyResource($result['company']),
            'token' => $result['accessToken'],
        ], SymfonyResponse::HTTP_CREATED);
    }
}
