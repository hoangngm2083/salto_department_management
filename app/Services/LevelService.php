<?php

namespace App\Services;

use App\Models\Level;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LevelService
{
    /**
     * Spacing left between consecutive ranks so a new level can usually be
     * inserted between two existing ones without renumbering anything else.
     */
    private const RANK_GAP = 10;

    /**
     * Scratch range every rank is bounced through mid-rebalance, safely
     * above any rank this gap-based scheme would ever produce in normal
     * use, so phase one of the rebalance can never collide with a rank a
     * later row still holds.
     */
    private const REBALANCE_SCRATCH_OFFSET = 60000;

    /**
     * Get paginated levels with status filtering using cursor pagination (no OFFSET).
     */
    public function getPaginated(string $status = 'active', int $perPage = 15): CursorPaginator
    {
        $query = Level::query();

        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'inactive') {
            $query->where('status', 'inactive');
        }

        return $query->orderBy('rank')->cursorPaginate($perPage);
    }

    /**
     * Create or update a level record. On create, `rank` isn't part of
     * `$data` - it's derived from `insert_position`/`reference_level_id`
     * (see `resolveRankForInsert()`), keeping the picked-position UX from
     * ever exposing the raw integer to whoever is creating the level.
     */
    public function upsert(array $data, ?Level $level = null): Level
    {
        if ($level !== null) {
            $level->update($data);

            return $level->fresh();
        }

        return DB::transaction(function () use ($data) {
            $insertPosition = $data['insert_position'];
            $referenceLevelId = $data['reference_level_id'] ?? null;
            unset($data['insert_position'], $data['reference_level_id']);

            $data['rank'] = $this->resolveRankForInsert($insertPosition, $referenceLevelId);

            return Level::create($data);
        });
    }

    /**
     * Delete a level record.
     */
    public function delete(Level $level): bool
    {
        return (bool) $level->delete();
    }

    /**
     * Work out the rank for a new level from where the caller wants it
     * inserted, taking the midpoint between its two future neighbours. If
     * there's no integer room left between them (they're already adjacent),
     * rebalance every existing rank into even RANK_GAP steps first and
     * retry once - guaranteed to succeed since ranks post-rebalance are
     * always at least RANK_GAP apart.
     */
    private function resolveRankForInsert(string $position, ?int $referenceLevelId, bool $retried = false): int
    {
        [$before, $after] = match ($position) {
            'start' => [null, Level::query()->orderBy('rank')->first()],
            'end' => [Level::query()->orderByDesc('rank')->first(), null],
            'before' => $this->neighboursOf(Level::query()->findOrFail($referenceLevelId), placingBefore: true),
            'after' => $this->neighboursOf(Level::query()->findOrFail($referenceLevelId), placingBefore: false),
        };

        $rank = match (true) {
            $before === null && $after === null => self::RANK_GAP,
            $before === null => intdiv($after->rank, 2),
            $after === null => $before->rank + self::RANK_GAP,
            default => intdiv($before->rank + $after->rank, 2),
        };

        $hasRoom = ($before === null || $rank > $before->rank) && ($after === null || $rank < $after->rank);

        if (! $hasRoom) {
            if ($retried) {
                throw new RuntimeException('Unable to resolve a rank for the new level even after rebalancing.');
            }

            $this->rebalanceRanks();

            return $this->resolveRankForInsert($position, $referenceLevelId, retried: true);
        }

        return $rank;
    }

    /**
     * @return array{0: ?Level, 1: ?Level} [level immediately before the
     *                                     reference, level immediately after it] - "before"/"after" placement
     *                                     slots the new level between the reference and whichever of these
     *                                     sits on that side of it.
     */
    private function neighboursOf(Level $reference, bool $placingBefore): array
    {
        $immediatelyBefore = Level::query()->where('rank', '<', $reference->rank)->orderByDesc('rank')->first();
        $immediatelyAfter = Level::query()->where('rank', '>', $reference->rank)->orderBy('rank')->first();

        return $placingBefore ? [$immediatelyBefore, $reference] : [$reference, $immediatelyAfter];
    }

    /**
     * Renumber every level to RANK_GAP, 2×RANK_GAP, 3×RANK_GAP... in its
     * current rank order. Done in two passes through a high scratch range
     * rather than directly to the final values: reassigning ascending in
     * one pass would momentarily duplicate a rank whenever an earlier row's
     * target equals a later row's still-current rank, which the `rank`
     * column's unique constraint rejects.
     */
    private function rebalanceRanks(): void
    {
        $levels = Level::query()->orderBy('rank')->lockForUpdate()->get(['id']);

        $levels->each(fn (Level $level, int $index) => $level->update([
            'rank' => self::REBALANCE_SCRATCH_OFFSET + $index,
        ]));

        $levels->each(fn (Level $level, int $index) => $level->update([
            'rank' => ($index + 1) * self::RANK_GAP,
        ]));
    }
}
