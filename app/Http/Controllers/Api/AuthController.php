<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $d = $this->normalizeEmail($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', PasswordRule::min(8), 'confirmed'],
        ], [
            'name.required' => 'Enter your full name.',
            'name.min' => 'Your name must be at least 2 characters.',
            'email.required' => 'Enter your email address.',
            'email.email' => 'Enter a valid email address.',
            'email.unique' => 'An account already uses this email. Sign in or reset your password.',
            'password.min' => 'Use at least 8 characters for your password.',
            'password.confirmed' => 'The passwords do not match.',
        ]);

        $user = User::create([
            'name' => trim($validated['name']),
            'email' => $validated['email'],
            'password' => $validated['password'],
        ])->refresh();

        return response()->json([
            'message' => 'Your account was created. An administrator must assign staff access before you can sign in to the admin panel.',
            'user' => UserResource::make($user)->resolve($request),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['sometimes', 'boolean'],
        ], [
            'email.required' => 'Enter your email address.',
            'email.email' => 'Enter a valid email address.',
            'password.required' => 'Enter your password.',
        ]);

        if (! Auth::guard('web')->attempt([
            'email' => $validated['email'],
            'password' => $validated['password'],
        ], $request->boolean('remember'))) {
            return response()->json([
                'message' => 'We could not match that email and password. Check your details and try again.',
            ], 401);
        }

        $request->session()->regenerate();
        $user = $request->user();

        if ($user instanceof User && ! $user->is_active) {
            $this->endSession($request);

            return response()->json([
                'message' => 'This account has been blocked. Please contact an administrator.',
            ], 403);
        }

        if (! $user instanceof User || ! $user->hasAdminAccess()) {
            $this->endSession($request);

            return response()->json([
                'message' => 'This account does not have staff access yet. Please contact an administrator.',
            ], 403);
        }

        return response()->json([
            'user' => UserResource::make($user)->resolve($request),
        ]);
    }

    public function currentUser(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->is_active) {
            $this->endSession($request);

            return response()->json([
                'message' => 'This account has been blocked. Please contact an administrator.',
            ], 403);
        }

        if (! $user instanceof User || ! $user->hasAdminAccess()) {
            $this->endSession($request);

            return response()->json([
                'message' => 'Your account does not have staff access. Please contact an administrator.',
            ], 403);
        }

        return response()->json([
            'user' => UserResource::make($user)->resolve($request),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->endSession($request);

        return response()->json([
            'message' => 'You have been signed out.',
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);
        $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
        ], [
            'email.required' => 'Enter your email address.',
            'email.email' => 'Enter a valid email address.',
        ]);

        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'If an account exists for that email, a password reset link has been sent.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', PasswordRule::min(8), 'confirmed'],
        ], [
            'token.required' => 'This password reset link is incomplete. Request a new one.',
            'email.required' => 'Enter your email address.',
            'email.email' => 'Enter a valid email address.',
            'password.min' => 'Use at least 8 characters for your password.',
            'password.confirmed' => 'The passwords do not match.',
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'This password reset link has expired or is invalid. Request a new one.',
            ], 422);
        }

        return response()->json([
            'message' => 'Your password has been reset. You can now sign in.',
        ]);
    }

    private function normalizeEmail(Request $request): void
    {
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }
    }

    private function endSession(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
