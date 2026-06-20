<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['id', 'decks', 'target_score'])]
class Game extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }
}
