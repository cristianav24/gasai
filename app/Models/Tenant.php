<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name', 'slug', 'rubro', 'tracks_containers', 'currency', 'timezone',
        'business_hours', 'onboarding_step', 'onboarding_completed_at',
        'onboarding_checklist_dismissed',
    ];

    protected $casts = [
        'business_hours' => 'array',
        'onboarding_completed_at' => 'datetime',
        'onboarding_checklist_dismissed' => 'boolean',
        'tracks_containers' => 'boolean',
    ];

    public function onboardingCompleted(): bool
    {
        return $this->onboarding_completed_at !== null;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}
