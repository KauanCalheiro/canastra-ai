<?php

namespace App\Http\Controllers\Api;

use App\Actions\Sequence\CreateSequence;
use App\Data\Sequence\CreateSequenceData;
use App\Http\Controllers\Controller;
use App\Http\Resources\SequenceResource;
use App\Models\Game;
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
}
