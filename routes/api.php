
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FamilyInvitationController;
use App\Http\Controllers\Api\FamilyMemberController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Get authenticated user
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Protected routes
Route::middleware('auth:sanctum')->group(function () {

    // Tasks
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::get('/tasks/completed', [TaskController::class, 'completed']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::put('/tasks/{task}', [TaskController::class, 'update']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
    Route::patch('/tasks/{task}/complete', [TaskController::class, 'complete']);

    // Family members
    Route::get('/family/members', [FamilyMemberController::class, 'index']);
    Route::delete('/family/members/{familyMember}', [FamilyMemberController::class, 'destroy']);

    // Family invitations
    Route::post('/family-invitations', [FamilyInvitationController::class, 'store']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
});

// Accept invitation (public route)
Route::post('/family-invitations/accept', [FamilyInvitationController::class, 'accept']);

// Authentication
Route::post('/register-parent', [AuthController::class, 'registerParent']);
Route::post('/login', [AuthController::class, 'login']);
