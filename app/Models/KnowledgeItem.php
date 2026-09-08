<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class KnowledgeItem extends Model
{
    use BelongsToTenant;

    /** Máximo de caracteres por item. */
    public const MAX_ITEM_CHARS = 4000;

    /**
     * Máximo total de caracteres de todos los items activos que se inyectan al
     * system prompt del agente. ~12k caracteres ≈ 3k tokens: deja margen cómodo
     * dentro del contexto sin encarecer cada llamada.
     */
    public const MAX_TOTAL_CHARS = 12000;

    protected $fillable = ['tenant_id', 'title', 'content', 'active'];

    protected $casts = ['active' => 'boolean'];

    /** Total de caracteres de los items activos del tenant actual. */
    public static function activeCharsTotal(): int
    {
        return static::query()
            ->where('active', true)
            ->get(['title', 'content'])
            ->sum(fn (self $item): int => mb_strlen($item->title) + mb_strlen($item->content));
    }
}
