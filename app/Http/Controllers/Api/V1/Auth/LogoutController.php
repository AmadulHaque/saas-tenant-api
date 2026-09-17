<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

#[Group('Authentication', description: 'Register, login, and logout with Passport personal access tokens.')]
class LogoutController extends Controller
{
    /**
     * Revoke the access token used for the current request.
     */
    public function __invoke(Request $request): Response
    {
        $request->user('api')?->token()?->revoke();

        return response()->noContent();
    }
}
