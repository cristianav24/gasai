<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'cash_session_id', 'user_id', 'type', 'amount', 'reason',
    ];

    protected $casts = ['amount' => 'decimal:2'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }
}
