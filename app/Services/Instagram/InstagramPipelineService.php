<?php

namespace App\Services\Instagram;

use App\Models\Deal;
use App\Models\Pipeline;
use Illuminate\Support\Facades\Log;

/**
 * The Instagram lifecycle funnel (Phase 6). A dedicated "Instagram" pipeline —
 * separate from the workspace's own sales pipeline so we never reshuffle their
 * stages — whose ladder mirrors IG's native labels:
 *
 *   Lead → Ordered → Paid → Dispatched   (+ Cancelled)
 *
 * DM leads enter at Lead; orders enter at Ordered and auto-advance as the seller
 * moves the order through its fulfilment funnel. Dispatched is a Won stage,
 * Cancelled a Lost stage, so the Deal model's own stage→status sync marks them
 * automatically.
 */
class InstagramPipelineService
{
    /** key => [name, sort_order, color, is_won, is_lost, probability] */
    private const STAGES = [
        'lead'       => ['Lead',       0, '#8B5CF6', false, false, 15],
        'ordered'    => ['Ordered',    1, '#0EA5E9', false, false, 50],
        'paid'       => ['Paid',       2, '#F59E0B', false, false, 80],
        'dispatched' => ['Dispatched', 3, '#16A34A', true,  false, 100],
        'cancelled'  => ['Cancelled',  4, '#DC2626', false, true,  0],
    ];

    /** Order status → funnel stage key. */
    public const STATUS_STAGE = [
        'placed'     => 'ordered',
        'paid'       => 'paid',
        'dispatched' => 'dispatched',
        'cancelled'  => 'cancelled',
    ];

    /**
     * Ensure the workspace's Instagram pipeline exists (with its stages).
     *
     * Null when the host ships no CRM — the standalone product has no deals
     * table, and leads/orders are expected to stop short of creating a deal
     * there. Returning null says so; letting the class-not-found Error fall
     * into a caller's catch(\Throwable) made "no CRM installed" and "the CRM
     * is broken" produce the identical log line.
     */
    public function pipeline(int $wsId, ?string $currency = null): ?Pipeline
    {
        if (! InstagramGate::hasCore('crm')) return null;

        $p = Pipeline::where('workspace_id', $wsId)->where('name', 'Instagram')->first();
        if (! $p) {
            $next = (int) Pipeline::where('workspace_id', $wsId)->max('sort_order') + 1;
            $p = Pipeline::create([
                'workspace_id' => $wsId,
                'name'         => 'Instagram',
                'is_default'   => false,
                'currency'     => $currency ?: 'INR',
                'sort_order'   => $next,
            ]);
        }
        if ($p->stages()->count() === 0) {
            foreach (self::STAGES as $s) {
                $p->stages()->create([
                    'workspace_id' => $wsId,
                    'name'         => $s[0],
                    'sort_order'   => $s[1],
                    'color'        => $s[2],
                    'is_won'       => $s[3],
                    'is_lost'      => $s[4],
                    'probability'  => $s[5],
                ]);
            }
        }
        return $p;
    }

    /** Resolve a stage id on a pipeline by its canonical key. */
    public function stageId(Pipeline $p, string $key): ?int
    {
        $name = self::STAGES[$key][0] ?? null;
        if (! $name) return null;
        return optional($p->stages()->where('name', $name)->first())->id;
    }

    /**
     * Move a deal to a named IG stage. Setting stage_id is enough — the Deal
     * model syncs status/won_at/lost_at + fires the stage-change flow trigger.
     */
    public function moveDeal(?int $dealId, string $key): void
    {
        if (! $dealId || ! InstagramGate::hasCore('crm')) return;
        try {
            $deal = Deal::find($dealId);
            if (! $deal) return;
            $p = Pipeline::find($deal->pipeline_id);
            if (! $p) return;
            $sid = $this->stageId($p, $key);
            if ($sid && (int) $deal->stage_id !== $sid) {
                $deal->stage_id = $sid;
                $deal->save();
            }
        } catch (\Throwable $e) {
            Log::warning('[IG-PIPE] moveDeal failed: ' . $e->getMessage());
        }
    }
}
