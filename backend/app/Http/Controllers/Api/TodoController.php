<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Todo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TodoController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            $todos = DB::table('admin_todos_view')
                ->orderByDesc('id')
                ->get()
                ->map(function ($todo) {
                    $todo->completed = (bool) $todo->completed;

                    return $todo;
                });

            return response()->json($todos);
        }

        return response()->json(
            $user->todos()->latest('id')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'user_id' => 'sometimes|required|integer|exists:users,id',
        ]);

        $ownerId = $request->user()->isAdmin()
            ? ($data['user_id'] ?? $request->user()->id)
            : $request->user()->id;

        $todo = Todo::create([
            'user_id' => $ownerId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
        ]);

        return response()->json($todo, 201);
    }

    public function show(Todo $todo)
    {
        Gate::authorize('view', $todo);

        return response()->json($todo);
    }

    public function update(Request $request, Todo $todo)
    {
        Gate::authorize('update', $todo);

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'completed' => 'sometimes|boolean',
        ]);

        $todo->update($data);

        return response()->json($todo);
    }

    public function destroy(Todo $todo)
    {
        Gate::authorize('delete', $todo);

        $todo->delete();

        return response()->json([
            'message' => 'A feladat törölve.',
        ]);
    }
}