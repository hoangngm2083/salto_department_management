<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\EmployeeStatus;
use App\Enums\ProjectAssignmentStatus;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'department_id', 'birthday', 'position', 'current_level_id', 'manager_employee_id', 'status'])]
#[Hidden(['password', 'remember_token'])]
class Employee extends Authenticatable
{
    /** @use HasFactory<EmployeeFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Escape character for LIKE patterns. Deliberately not a backslash, which
     * the MySQL and SQLite string parsers treat differently.
     */
    private const LIKE_ESCAPE_CHARACTER = '!';

    /**
     * Default attribute values, so an in-memory instance (factory ->make(),
     * `new Employee()`) has the same status the DB column default would give
     * a persisted row — the enum cast needs a real value, not null.
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
            'email_verified_at' => 'datetime',
            'birthday' => 'date',
            'password' => 'hashed',
            'status' => EmployeeStatus::class,
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function currentLevel(): BelongsTo
    {
        return $this->belongsTo(Level::class, 'current_level_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id');
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function directReports(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_employee_id');
    }

    /**
     * @return HasMany<ProjectAssignment, $this>
     */
    public function projectAssignments(): HasMany
    {
        return $this->hasMany(ProjectAssignment::class);
    }

    /**
     * @return HasMany<ProjectAssignment, $this>
     */
    public function activeProjectAssignments(): HasMany
    {
        return $this->projectAssignments()->where('status', ProjectAssignmentStatus::Active);
    }

    /**
     * Filter to employees whose name contains the given text anywhere.
     *
     * LIKE wildcards in the search text are escaped so a name typed with a
     * literal "%" or "_" is matched as typed rather than widening the search.
     * The ESCAPE character is declared explicitly rather than left to the
     * driver default: MySQL treats a backslash as one, SQLite has none at all.
     *
     * @param  Builder<Employee>  $query
     */
    #[Scope]
    protected function nameContains(Builder $query, string $name): void
    {
        $escaped = str_replace(
            [self::LIKE_ESCAPE_CHARACTER, '%', '_'],
            [self::LIKE_ESCAPE_CHARACTER.self::LIKE_ESCAPE_CHARACTER, self::LIKE_ESCAPE_CHARACTER.'%', self::LIKE_ESCAPE_CHARACTER.'_'],
            $name
        );

        $query->whereRaw(
            $query->qualifyColumn('name')." like ? escape '".self::LIKE_ESCAPE_CHARACTER."'",
            ["%{$escaped}%"]
        );
    }
}
