<?php

namespace App\Http\Controllers;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role'     => UserRoleEnum::Buyer->value,
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'token'   => $token,
            'user'    => $this->safeUser($user),
        ], 201);
    }

    
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        $user  = Auth::user();
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token'   => $token,
            'user'    => $this->safeUser($user),
        ]);
    }

    
    public function logout(Request $request): JsonResponse
    {
        
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

  
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->safeUser($request->user()),
        ]);
    }


    private function safeUser(User $user): array
    {
        return [
            'id'                => $user->id,
            'name'              => $user->name,
            'email'             => $user->email,
            'role'              => $user->role->value,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'created_at'        => $user->created_at?->toISOString(),
            'updated_at'        => $user->updated_at?->toISOString(),
        ];
    }
}
