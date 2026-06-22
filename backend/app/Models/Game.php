<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['id', 'decks', 'target_score', 'turn_index'])]
class Game extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    public function sequences(): HasMany
    {
        return $this->hasMany(Sequence::class);
    }
}
