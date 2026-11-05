<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /** Public registration always creates a CUSTOMER. Roles are never taken from the request. */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = new User($request->safe()->only(['name', 'email', 'password', 'company_name', 'phone']));
        $user->role = UserRole::Customer;
        $user->is_active = true;
        $user->save();

        return response()->json([
            'data' => [
                'user' => $this->payload($user),
                'token' => $user->createToken('api')->plainTextToken,
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->input('email'))->first();

        // Same message for "no such user", "wrong password" and "inactive": do not reveal which one.
        if (! $user || ! Hash::check($request->input('password'), $user->password) || $user->is_active === false) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        return response()->json([
            'data' => [
                'user' => $this->payload($user),
                'token' => $user->createToken($request->input('device_name', 'api'))->plainTextToken,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload($request->user())]);
    }

    private function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->value,
            'company_name' => $user->company_name,
        ];
    }
}
