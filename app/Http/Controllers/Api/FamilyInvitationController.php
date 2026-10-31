<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\FamilyInvitation;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FamilyInvitationController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'role' => 'required|in:parent,child',
        ]);

        $email = Str::lower(trim($validated['email']));
        $user = $request->user();

        // Only a parent can invite a family member.
        $membership = $user->familyMemberships()
            ->where('role', 'parent')
            ->first();

        if (! $membership) {
            return response()->json([
                'message' => 'Only a parent can invite family members.',
            ], 403);
        }

        // Reject emails that already have a Darna account.
        $existingUser = User::whereRaw(
            'LOWER(email) = ?',
            [$email]
        )->exists();

        if ($existingUser) {
            return response()->json([
                'message' => 'This email already has a Darna account. Use a new email address.',
            ], 422);
        }

        // Avoid creating duplicate pending invitations for the same family.
        $pendingInvitation = FamilyInvitation::where(
            'family_id',
            $membership->family_id
        )->whereRaw(
            'LOWER(email) = ?',
            [$email]
        )->whereNull('accepted_at')
          ->where('expires_at', '>', now())
          ->exists();

        if ($pendingInvitation) {
            return response()->json([
                'message' => 'A valid invitation already exists for this email.',
            ], 422);
        }

        $plainToken = Str::random(64);

        $invitation = FamilyInvitation::create([
            'family_id' => $membership->family_id,
            'invited_by' => $user->id,
            'email' => $email,
            'role' => $validated['role'],
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(3),
        ]);

        // The token is returned for local development and testing.
        // In production, send it through a verified invitation email.
        return response()->json([
            'message' => 'Invitation created successfully.',
            'invitation' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'expires_at' => $invitation->expires_at,
            ],
            'invitation_token' => $plainToken,
        ], 201);
    }

    public function accept(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'token' => 'required|string',
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $email = Str::lower(trim($validated['email']));

        $user = DB::transaction(function () use ($validated, $email) {
            // Lock the invitation row while accepting it.
            $invitation = FamilyInvitation::where(
                'email',
                $email
            )->where(
                'token_hash',
                hash('sha256', $validated['token'])
            )->whereNull('accepted_at')
              ->where('expires_at', '>', now())
              ->lockForUpdate()
              ->first();

            if (! $invitation) {
                throw ValidationException::withMessages([
                    'invitation' => [
                        'The invitation is invalid, expired, or already used.',
                    ],
                ]);
            }

            // Recheck inside the transaction.
            $existingUser = User::whereRaw(
                'LOWER(email) = ?',
                [$email]
            )->exists();

            if ($existingUser) {
                throw ValidationException::withMessages([
                    'email' => [
                        'This email already has a Darna account.',
                    ],
                ]);
            }

            $user = User::create([
                'name' => $validated['name'],
                'email' => $email,
                'password' => $validated['password'],
            ]);

            FamilyMember::create([
                'family_id' => $invitation->family_id,
                'user_id' => $user->id,
                'role' => $invitation->role,
            ]);

            $invitation->update([
                'accepted_at' => now(),
            ]);

            return $user;
        });

        $token = $user->createToken('darna-app')->plainTextToken;

        return response()->json([
            'message' => 'Invitation accepted successfully.',
            'user' => $user,
            'token' => $token,
        ], 201);
    }
}