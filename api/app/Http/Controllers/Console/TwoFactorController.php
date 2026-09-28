<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Auth\Totp;
use App\Domain\Tenancy\AuditLogger;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TwoFactorController
{
    public function start(Request $request): JsonResponse
    {
        $user = $request->user();
        $secret = Totp::secret();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
        ])->save();

        $url = Totp::otpauthUrl($secret, $user->email);

        return response()->json([
            'secret' => $secret,
            'otpauth_url' => $url,
            'qr_svg' => $this->qrSvg($url),
        ]);
    }

    public function confirm(Request $request, AuditLogger $audit): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:8'],
        ]);

        $user = $request->user();

        if (! filled($user->two_factor_secret) || ! Totp::verify($user->two_factor_secret, $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => 'That code is not valid.',
            ]);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $audit->record('auth.two_factor_enabled', $user);

        return response()->json(['two_factor_enabled' => true]);
    }

    public function destroy(Request $request, AuditLogger $audit): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Password is incorrect.',
            ]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $audit->record('auth.two_factor_disabled', $user);

        return response()->json(['two_factor_enabled' => false]);
    }

    private function qrSvg(string $payload): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle(size: 192, margin: 2),
            new SvgImageBackEnd,
        ));

        $svg = $writer->writeString($payload);

        return (string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);
    }
}
