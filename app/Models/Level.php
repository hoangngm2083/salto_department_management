<?php

namespace App\Models;

use App\Enums\ActiveStatus;
use Database\Factories\LevelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'rank', 'probation_salary_percentage', 'status'])]
class Level extends Model
{
    /** @use HasFactory<LevelFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Default attribute values, so an in-memory instance (factory ->make(),
     * `new Level()`) has the same status the DB column default would give a
     * persisted row — the enum cast needs a real value, not null.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ActiveStatus::class,
        ];
    }

    /**
     * Scope a query to only include active levels.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ActiveStatus::Active);
    }
}
