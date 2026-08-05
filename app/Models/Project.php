<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'description', 'status', 'start_date', 'end_date'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Default attribute values, so an in-memory instance (factory ->make(),
     * `new Project()`) has the same status the DB column default would give
     * a persisted row — the enum cast needs a real value, not null.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'planned',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * @return HasMany<ProjectManager, $this>
     */
    public function managers(): HasMany
    {
        return $this->hasMany(ProjectManager::class);
    }

    /**
     * @return HasMany<ProjectManager, $this>
     */
    public function activeManagers(): HasMany
    {
        return $this->managers()->whereNull('end_date');
    }
}
