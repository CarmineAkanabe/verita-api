<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PresenceStatus;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
// use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

#[Fillable([
    'first_name',
    'last_name',
    'email',
    'password',
    'role',
    'department_id',
    'staff_id',
    'profile_picture',
    'presence_status',
])]

#[Hidden(['password'])]

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'presence_status' => PresenceStatus::class,
        ];
    }

    // Relationships
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignedCases(): HasMany
    {
        return $this->hasMany(CaseRecord::class, 'assigned_to');
    }

    public function receivedNotifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    // JWT Activities
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
    public function getJWTCustomClaims()
    {
        return ['role' => $this->role->value];
    }
}
