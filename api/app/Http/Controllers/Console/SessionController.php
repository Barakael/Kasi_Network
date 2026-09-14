<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Radius\CoaDispatcher;
use App\Domain\Tenancy\AuditLogger;
use App\Models\NasDevice;
use App\Models\RadAcct;
use App\Models\VoucherUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController
{
    public function index(Request $request): JsonResponse
    {
        $usages = VoucherUsage::query()->pluck('username');

        $sessions = RadAcct::query()
            ->open()
            ->when($usages->isNotEmpty(), fn ($q) => $q->whereIn('username', $usages))
            ->orderByDesc('acctstarttime')
            ->limit($request->integer('per_page', 100))
            ->get();

        return response()->json(['data' => $sessions->map(fn (RadAcct $s) => $this->serialize($s))]);
    }

    public function disconnect(Request $request, CoaDispatcher $coa, AuditLogger $audit): JsonResponse
    {
        $validated = $request->validate([
            'acctuniqueid' => ['required', 'string'],
        ]);

        $session = RadAcct::query()
            ->open()
            ->where('acctuniqueid', $validated['acctuniqueid'])
            ->firstOrFail();

        $nas = NasDevice::query()->where('nasname', $session->nasipaddress)->firstOrFail();

        $ok = $coa->disconnect($session, $nas, 'operator-disconnect');

        $audit->record('session.disconnected', $nas, ['acctuniqueid' => $session->acctuniqueid]);

        return response()->json(['disconnected' => $ok]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(RadAcct $session): array
    {
        return [
            'acctuniqueid' => $session->acctuniqueid,
            'username' => $session->username,
            'nasipaddress' => $session->nasipaddress,
            'callingstationid' => $session->callingstationid,
            'framedipaddress' => $session->framedipaddress,
            'acctstarttime' => $session->acctstarttime?->toIso8601String(),
            'seconds' => $session->elapsedSeconds(),
            'bytes' => $session->bytesTotal(),
        ];
    }
}
