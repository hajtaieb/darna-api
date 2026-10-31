<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\FamilyMember;
use Illuminate\Http\Request;

class FamilyMemberController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $membership = $user->familyMemberships()->first();

        if (! $membership) {
            return response()->json([
                'message' => 'You do not belong to a family.',
            ], 404);
        }

        $members = FamilyMember::with('user:id,name,email')
            ->where('family_id', $membership->family_id)
            ->get();

        return response()->json([
            'family_id' => $membership->family_id,
            'members' => $members,
        ]);
    }

    public function destroy(Request $request, FamilyMember $familyMember)
    {
        $user = $request->user();

        $membership = $user->familyMemberships()
            ->where('role', 'parent')
            ->first();

        if (! $membership) {
            return response()->json([
                'message' => 'Only parents can remove family members.',
            ], 403);
        }

        if ($familyMember->family_id !== $membership->family_id) {
            return response()->json([
                'message' => 'This member does not belong to your family.',
            ], 403);
        }

        if ($familyMember->role !== 'child') {
            return response()->json([
                'message' => 'You cannot remove a parent.',
            ], 403);
        }

        $familyMember->delete();

        return response()->json([
            'message' => 'Family member removed successfully.',
        ]);
    }
}
