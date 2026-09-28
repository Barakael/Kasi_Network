<?php

declare(strict_types=1);

namespace App\Http\Controllers\Console;

use App\Domain\Tenancy\AuditLogger;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CampaignController
{
    public function index(): AnonymousResourceCollection
    {
        return CampaignResource::collection(
            Campaign::query()->orderByDesc('id')->get(),
        );
    }

    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        abort_unless($request->user()?->managesTenant(), 403);

        $campaign = Campaign::create($this->validated($request));

        $audit->record('campaign.created', $campaign, ['title' => $campaign->title]);

        return (new CampaignResource($campaign))->response()->setStatusCode(201);
    }

    public function update(Request $request, Campaign $campaign, AuditLogger $audit): CampaignResource
    {
        abort_unless($request->user()?->managesTenant(), 403);

        $campaign->update($this->validated($request, partial: true));

        $audit->record('campaign.updated', $campaign);

        return new CampaignResource($campaign->fresh());
    }

    public function destroy(Request $request, Campaign $campaign, AuditLogger $audit): JsonResponse
    {
        abort_unless($request->user()?->managesTenant(), 403);

        $campaign->delete();
        $audit->record('campaign.deleted', $campaign);

        return response()->json(['message' => 'Campaign removed.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial = false): array
    {
        $sometimes = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$sometimes, 'string', 'max:120'],
            'body' => [$sometimes, 'string', 'max:500'],
            'audience' => ['sometimes', 'in:all,kimya'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
