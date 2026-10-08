<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index(): Response
    {
        return Inertia::render('Users/Index', [
            'users' => User::with('teacher')->orderBy('name')->get(),
            'teachers' => Teacher::orderBy('lastname')->get(),
            'roles' => array_map(
                fn (UserRole $role): array => ['value' => $role->value, 'label' => $role->label()],
                UserRole::cases(),
            ),
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'teacher_id' => ['nullable', 'integer', Rule::exists('teachers', 'id')],
        ]);

        $teacherId = $validated['teacher_id'] ?? null;
        unset($validated['teacher_id']);

        $user = DB::transaction(function () use ($validated, $teacherId): User {
            $user = User::create($validated);

            $this->linkTeacher($user, $teacherId);

            return $user;
        });

        return redirect()->route('users.index')->with('success', "User {$user->name} created.");
    }

    /**
     * Update the given user.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'teacher_id' => ['nullable', 'integer', Rule::exists('teachers', 'id')],
        ]);

        $teacherId = $validated['teacher_id'] ?? null;
        unset($validated['teacher_id']);

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        DB::transaction(function () use ($user, $validated, $teacherId): void {
            $user->update($validated);

            $this->linkTeacher($user, $teacherId);
        });

        return redirect()->route('users.index')->with('success', "User {$user->name} updated.");
    }

    /**
     * Remove the given user.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->with('error', 'You cannot delete your own account from here.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted.');
    }

    /**
     * Link a teacher directory record to the given user.
     */
    private function linkTeacher(User $user, ?int $teacherId): void
    {
        Teacher::where('user_id', $user->id)->update(['user_id' => null]);

        if ($teacherId !== null) {
            Teacher::whereKey($teacherId)->update(['user_id' => $user->id]);
        }
    }
}
