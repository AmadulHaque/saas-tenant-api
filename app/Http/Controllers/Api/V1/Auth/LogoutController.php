<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
