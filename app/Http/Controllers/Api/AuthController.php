<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\Auth\CreateUserRequest;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

use App\Services\AuthService;


class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {

        $this->authService = $authService;
    }

    /**
     * Create a new user by an Admin.
     *
     * @param  \App\Http\Requests\Auth\CreateUserRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createUser(CreateUserRequest $request)
    {


        $user = $this->authService->createUser($request->validated()); // Pass validated data array


        return response()->json([
            'message' => 'User created successfully by admin',
            'user' => $user,
        ], 201);
    }

    /**
     * Handle user login.
     *
     * @param  \App\Http\Requests\Auth\LoginRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();
        /** @var \App\Models\User $user */ // For IDE hinting

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user->tokens()->delete();
        $token = $user->createToken('authToken')->plainTextToken;

        return response()->json([
            'message' => 'Logged in successfully',
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Get the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function user(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    /**
     * Handle user logout.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        Log::info('Logout method started for user: ' . $request->user()->email);
        $tokenableId = $request->user()->id;
        $tokenableType = get_class($request->user());
        $tokenId = $request->user()->currentAccessToken()->id;

        $deleted = $request->user()->currentAccessToken()->delete();
        Log::info('Token deletion attempt for user: ' . $request->user()->email . ', Token ID: ' . $tokenId . ', Deleted: ' . ($deleted ? 'Yes' : 'No'));

        if (\DB::table('personal_access_tokens')->where('id', $tokenId)->exists()) {
            Log::warning('Token ID: ' . $tokenId . ' still exists in DB after delete attempt!');
        } else {
            Log::info('Token ID: ' . $tokenId . ' confirmed deleted from DB.');
        }

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }
}