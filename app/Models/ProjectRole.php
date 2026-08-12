<?php

namespace App\Models;

use App\Enums\ActiveStatus;
use Database\Factories\ProjectRoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'description', 'status'])]
class ProjectRole extends Model
{
    /** @use HasFactory<ProjectRoleFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Default attribute values, so an in-memory instance (factory ->make(),
     * `new ProjectRole()`) has the same status the DB column default would
     * give a persisted row — the enum cast needs a real value, not null.
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
     * Scope a query to only include active project roles.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ActiveStatus::Active);
    }
}
