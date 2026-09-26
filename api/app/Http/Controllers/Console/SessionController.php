<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Radius\CoaDispatcher;
use App\Domain\Radius\RadiusProvisioner;
use App\Domain\Support\MacAddress;
use App\Domain\Tenancy\AuditLogger;
use App\Domain\Voucher\MacBinder;
use App\Models\NasDevice;
use App\Models\RadAcct;
use App\Models\Voucher;
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

    public function disconnect(
        Request $request,
        CoaDispatcher $coa,
        AuditLogger $audit,
        MacBinder $binder,
        RadiusProvisioner $provisioner,
    ): JsonResponse {
        $validated = $request->validate([
            'acctuniqueid' => ['required', 'string'],
        ]);

        $session = RadAcct::query()
            ->open()
            ->where('acctuniqueid', $validated['acctuniqueid'])
            ->firstOrFail();

        $nas = NasDevice::query()->forRadiusIp($session->nasipaddress)->first();

        $kicked = false;

        if ($nas instanceof NasDevice) {
            $kicked = $coa->disconnect($session, $nas, 'operator-disconnect');
            $audit->record('session.disconnected', $nas, [
                'acctuniqueid' => $session->acctuniqueid,
                'coa' => $kicked,
            ]);
        } else {
            $audit->record('session.disconnected', null, [
                'acctuniqueid' => $session->acctuniqueid,
                'coa' => false,
                'nas_missing' => true,
            ]);
        }

        $this->closeAccounting($session);

        $voucher = $this->voucherForSession($session);

        if ($voucher instanceof Voucher) {
            $binder->release($voucher);
        } else {
            $provisioner->unbindCallingStation($session->username);
        }

        return response()->json([
            'disconnected' => $kicked,
            'session_closed' => true,
            'mac_released' => true,
        ]);
    }

    /**
     * Codes are encrypted on the voucher row, so the RADIUS username cannot be
     * used as a lookup. Usage and the bound MAC are stored in the clear.
     */
    private function voucherForSession(RadAcct $session): ?Voucher
    {
        $fromUsage = VoucherUsage::query()
            ->with('voucher')
            ->where('username', $session->username)
            ->first()?->voucher;

        if ($fromUsage instanceof Voucher) {
            return $fromUsage;
        }

        $mac = MacAddress::tryParse($session->callingstationid);

        if ($mac === null) {
            return null;
        }

        return Voucher::query()
            ->where('bound_mac', $mac->toString())
            ->first();
    }

    /**
     * Marks the accounting row closed so the console drops it even when CoA
     * cannot reach the router (typical behind CGNAT / a CPE that drops UDP).
     */
    private function closeAccounting(RadAcct $session): void
    {
        RadAcct::query()->where('radacctid', $session->radacctid)->update([
            'acctstoptime' => now(),
            'acctsessiontime' => $session->elapsedSeconds(),
            'acctterminatecause' => 'Admin-Reset',
        ]);
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
