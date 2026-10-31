<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Family;
use App\Models\FamilyMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function registerParent(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'family_name' => 'required|string|max:255',
        ]);

        $result = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            $family = Family::create([
                'name' => $validated['family_name'],
                'created_by' => $user->id,
            ]);

            FamilyMember::create([
                'family_id' => $family->id,
                'user_id' => $user->id,
                'role' => 'parent',
            ]);

            return [$user, $family];
        });

        [$user, $family] = $result;

        $token = $user->createToken('darna-app')->plainTextToken;

        return response()->json([
            'message' => 'Family created successfully',
            'user' => $user,
            'family' => $family,
            'token' => $token,
        
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (
            ! $user ||
            ! \Illuminate\Support\Facades\Hash::check(
                $validated['password'],
                $user->password
            )
        ) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        $token = $user->createToken('darna-app')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
        ]);
    }
}
