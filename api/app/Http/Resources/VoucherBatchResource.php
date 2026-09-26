<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Voucher\VoucherStatus;
use App\Models\VoucherBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VoucherBatch
 */
class VoucherBatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'quantity' => $this->quantity,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_printable' => $this->status->isPrintable() && $this->printableCount() > 0,
            'printable_count' => $this->printableCount(),
            'notes' => $this->notes,
            'printed_at' => $this->printed_at?->toIso8601String(),
            'print_count' => $this->print_count,
            'created_at' => $this->created_at?->toIso8601String(),

            'plan' => new PlanResource($this->whenLoaded('plan')),
            'site' => new SiteResource($this->whenLoaded('site')),
            'assigned_agent' => new UserResource($this->whenLoaded('assignedAgent')),
            'created_by' => new UserResource($this->whenLoaded('createdBy')),

            /*
             * How much of the batch has been sold through. Counted by the caller
             * with withCount rather than here, so a list of batches stays one
             * query instead of one per row. Read from the raw attributes because
             * the redeemed figure is an aliased count, which whenCounted cannot
             * find by relation name.
             */
            ...$this->progressCounts(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function progressCounts(): array
    {
        $issued = $this->getAttributes()['vouchers_count'] ?? null;
        $redeemed = $this->getAttributes()['redeemed_count'] ?? null;

        if ($issued === null || $redeemed === null) {
            return [];
        }

        return [
            'issued_count' => (int) $issued,
            'redeemed_count' => (int) $redeemed,
            'unused_count' => (int) $issued - (int) $redeemed,
        ];
    }

    /**
     * Unused codes that have never been on a sheet. Print is hidden once this
     * hits zero, otherwise the operator retries a 422 they already spent.
     */
    private function printableCount(): int
    {
        return (int) ($this->getAttributes()['printable_count'] ?? 0);
    }

    /**
     * The withCount clauses a batch listing needs to fill in its progress figures.
     *
     * Kept next to the resource that reads them so the two cannot drift apart.
     *
     * @return array<string, mixed>
     */
    public static function countsFor(): array
    {
        return [
            'vouchers',
            'vouchers as redeemed_count' => fn ($query) => $query->whereNot('status', VoucherStatus::Unused),
            'vouchers as printable_count' => fn ($query) => $query
                ->whereNull('printed_at')
                ->where('status', VoucherStatus::Unused),
        ];
    }
}
