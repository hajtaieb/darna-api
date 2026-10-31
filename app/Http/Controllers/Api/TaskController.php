<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    // Display pending tasks
    public function index(Request $request)
    {
        $familyIds = $request->user()
            ->familyMemberships()
            ->pluck('family_id');

        if ($familyIds->isEmpty()) {
            return response()->json([
                'message' => 'You do not belong to a family.',
            ], 403);
        }

        return Task::whereIn('family_id', $familyIds)
            ->where('status', 'pending')
            ->get();
    }

    // Display completed tasks
    public function completed(Request $request)
    {
        $familyIds = $request->user()
            ->familyMemberships()
            ->pluck('family_id');

        if ($familyIds->isEmpty()) {
            return response()->json([
                'message' => 'You do not belong to a family.',
            ], 403);
        }

        return Task::whereIn('family_id', $familyIds)
            ->where('status', 'completed')
            ->get();
    }

    // Create a task (parents only)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'assigned_to' => 'nullable|integer',
            'due_date' => 'nullable|date',
        ]);

        $membership = $request->user()
            ->familyMemberships()
            ->where('role', 'parent')
            ->first();

        if (! $membership) {
            return response()->json([
                'message' => 'Only a parent can create tasks.',
            ], 403);
        }

        if (! empty($validated['assigned_to'])) {
            $childExists = $membership->family
                ->members()
                ->where('user_id', $validated['assigned_to'])
                ->where('role', 'child')
                ->exists();

            if (! $childExists) {
                return response()->json([
                    'message' => 'The selected child is not in your family.',
                ], 422);
            }
        }

        $task = Task::create([
            'family_id' => $membership->family_id,
            'assigned_to' => $validated['assigned_to'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => 'pending',
            'due_date' => $validated['due_date'] ?? null,
        ]);

        // Notify the child when a task is assigned
        if (! empty($validated['assigned_to'])) {
            $child = User::find($validated['assigned_to']);

            $child->notify(new TaskAssignedNotification($task));
        }

        return response()->json([
            'message' => 'Task created successfully.',
            'task' => $task,
        ], 201);
    }

    // Complete a task
    public function complete(Request $request, Task $task)
    {
        $membership = $request->user()
            ->familyMemberships()
            ->where('family_id', $task->family_id)
            ->first();

        if (! $membership) {
            return response()->json([
                'message' => 'This task does not belong to your family.',
            ], 403);
        }

        if (
            $membership->role === 'child' &&
            (int) $task->assigned_to !== (int) $request->user()->id
        ) {
            return response()->json([
                'message' => 'You can only complete tasks assigned to you.',
            ], 403);
        }

        if ($task->status === 'completed') {
            return response()->json([
                'message' => 'This task is already completed.',
            ], 422);
        }

        $task->update([
            'status' => 'completed',
        ]);

        return response()->json([
            'message' => 'Task completed successfully.',
            'task' => $task,
        ]);
    }

    // Update a task (parents only)
    public function update(Request $request, Task $task)
    {
        $membership = $request->user()
            ->familyMemberships()
            ->where('family_id', $task->family_id)
            ->where('role', 'parent')
            ->first();

        if (! $membership) {
            return response()->json([
                'message' => 'Only a parent in this family can update this task.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string|max:2000',
            'assigned_to' => 'sometimes|nullable|integer',
            'due_date' => 'sometimes|nullable|date',
        ]);

        if (
            array_key_exists('assigned_to', $validated) &&
            $validated['assigned_to'] !== null
        ) {
            $childExists = $membership->family
                ->members()
                ->where('user_id', $validated['assigned_to'])
                ->where('role', 'child')
                ->exists();

            if (! $childExists) {
                return response()->json([
                    'message' => 'The selected child is not in your family.',
                ], 422);
            }
        }

        $task->update($validated);

        return response()->json([
            'message' => 'Task updated successfully.',
            'task' => $task,
        ]);
    }

    // Delete a task (parents only)
    public function destroy(Request $request, Task $task)
    {
        $membership = $request->user()
            ->familyMemberships()
            ->where('family_id', $task->family_id)
            ->where('role', 'parent')
            ->first();

        if (! $membership) {
            return response()->json([
                'message' => 'Only a parent in this family can delete this task.',
            ], 403);
        }

        $task->delete();

        return response()->json([
            'message' => 'Task deleted successfully.',
        ]);
    }
}
