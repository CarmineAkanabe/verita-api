<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Department extends Model
{
    /** @use HasFactory<\Database\Factories\DepartmentFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['name'];

    public function departmentHeads(): HasMany
    {
        return $this->hasMany(User::class)->where('role', Role::DEPARTMENT_HEAD);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(CaseRecord::class);
    }
}
