<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Auth\Totp;
use App\Domain\Billing\TanzanianPhone;
use App\Domain\Tenancy\AuditLogger;
use App\Http\Requests\Console\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController
{
    /**
     * Issues a Sanctum token for the console.
     */
    public function login(LoginRequest $request, AuditLogger $audit): JsonResponse
    {
        $this->ensureNotRateLimited($request);

        $login = trim($request->string('email')->value());
        $user = User::query()->where('email', $login)->first();

        if ($user === null && TanzanianPhone::isValid($login)) {
            $user = User::query()->where('phone', TanzanianPhone::toE164($login))->first();
        }

        /*
         * A single failure message for an unknown email, a wrong password and a
         * deactivated account, so the endpoint cannot be used to enumerate which
         * addresses have accounts. Hash::check still runs against a dummy hash
         * when the user is missing, keeping the response time uniform.
         */
        $passwordMatches = $user === null
            ? Hash::check($request->string('password'), '$2y$12$'.str_repeat('x', 53))
            : Hash::check($request->string('password'), $user->password);

        if ($user === null || ! $passwordMatches || ! $user->is_active) {
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'email' => 'Those credentials do not match our records.',
            ]);
        }

        if ($user->hasTwoFactor()) {
            $code = $request->string('two_factor_code')->value();

            if ($code === '') {
                return response()->json([
                    'requires_two_factor' => true,
                    'message' => 'Enter the authenticator code.',
                ], 403);
            }

            if (! Totp::verify($user->two_factor_secret, $code)) {
                RateLimiter::hit($this->throttleKey($request));

                throw ValidationException::withMessages([
                    'two_factor_code' => 'That authenticator code is not valid.',
                ]);
            }
        }

        RateLimiter::clear($this->throttleKey($request));

        /*
         * One token per named device. Re-issuing replaces the previous one so a
         * repeated login does not leave a trail of valid tokens behind.
         */
        $user->tokens()->where('name', $request->string('device_name'))->delete();

        $token = $user->createToken(
            $request->string('device_name')->value(),
            $this->abilitiesFor($user),
        );

        $user->forceFill(['last_login_at' => now()])->save();

        $audit->record(
            action: 'auth.login',
            subject: $user,
            context: ['device_name' => $request->string('device_name')->value()],
            actor: $user,
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => new UserResource($this->consoleUser($user)),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($this->consoleUser($request->user()));
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Nenosiri la sasa si sahihi.',
            ]);
        }

        $user->forceFill(['password' => $validated['password']])->save();

        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $user->tokens()->where('id', '!=', $token->id)->delete();
        }

        return response()->json(['message' => 'Nenosiri limebadilishwa.']);
    }

    /**
     * Agents need assigned sites on every /me so the desk switcher works
     * without waiting for the first desk payload.
     */
    private function consoleUser(User $user): User
    {
        $user->load('tenant');

        if ($user->isAgent()) {
            $user->load('sites');
        }

        return $user;
    }

    /**
     * Token abilities mirror the user's role, so a stolen agent token cannot be
     * used against management endpoints even if the route middleware changes.
     *
     * @return array<int, string>
     */
    private function abilitiesFor(User $user): array
    {
        return $user->isAgent()
            ? ['vouchers:print', 'desk']
            : ['*'];
    }

    private function ensureNotRateLimited(LoginRequest $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), maxAttempts: 5)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => sprintf(
                'Too many attempts. Try again in %d seconds.',
                RateLimiter::availableIn($this->throttleKey($request)),
            ),
        ]);
    }

    private function throttleKey(LoginRequest $request): string
    {
        return 'login:'.strtolower((string) $request->string('email')).'|'.$request->ip();
    }
}
