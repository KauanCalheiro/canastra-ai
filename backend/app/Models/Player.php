<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id', 'game_id', 'seat_index', 'name'])]
class Player extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
