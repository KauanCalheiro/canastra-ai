<?php

namespace App\Http\Controllers\Api;

use App\Actions\Sequence\CreateSequence;
use App\Actions\Sequence\ExtendSequence;
use App\Actions\Sequence\SwapSequenceCard;
use App\Data\Sequence\CreateSequenceData;
use App\Data\Sequence\ExtendSequenceData;
use App\Data\Sequence\SwapSequenceCardData;
use App\Http\Controllers\Controller;
use App\Http\Resources\SequenceResource;
use App\Models\Game;
use App\Models\Sequence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SequenceController extends Controller
{
    public function store(Game $game, CreateSequenceData $data): JsonResponse
    {
        $sequence = CreateSequence::run($game, $data);

        return SequenceResource::make($sequence->fresh('cards'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function extend(Sequence $sequence, ExtendSequenceData $data): JsonResponse
    {
        $sequence = ExtendSequence::run($sequence, $data);

        return SequenceResource::make($sequence)->response();
    }

    public function swap(Sequence $sequence, int $position, SwapSequenceCardData $data): JsonResponse
    {
        $sequence = SwapSequenceCard::run($sequence, $position, $data);

        return SequenceResource::make($sequence)->response();
    }
}
