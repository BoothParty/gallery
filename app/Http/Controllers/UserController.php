<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::query()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => (bool) $user->is_admin,
                'email_verified_at' => $user->email_verified_at?->format('Y-m-d H:i:s'),
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
            ]);

        return Inertia::render('users', [
            'users' => $users,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->update($request->validated());

        return redirect()->back()->with('success', 'User updated successfully.');
    }

    public function toggleAdmin(User $user): RedirectResponse
    {
        $newAdminStatus = ! (bool) $user->is_admin;
        
        $user->update([
            'is_admin' => $newAdminStatus,
        ]);

        $message = $newAdminStatus
            ? "User {$user->name} is now an admin."
            : "Admin privileges removed from {$user->name}.";

        return redirect()->back()->with('success', $message);
    }

    public function destroy(User $user): RedirectResponse
    {
        $name = $user->name;
        $user->delete();

        return redirect()->back()->with('success', "User {$name} has been deleted.");
    }
}
