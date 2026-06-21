<?php

namespace App\Support\Sequence;

use App\Exceptions\Sequence\InvalidAceTrincaCardException;
use App\Exceptions\Sequence\InvalidSequenceCardException;
use App\Exceptions\Sequence\MaxWildJokerExceededException;
use App\Exceptions\Sequence\MaxWildTwoExceededException;
use App\Exceptions\Sequence\SequenceRankOutOfBoundsException;
use App\Exceptions\Sequence\WildcardCoexistenceException;
use App\Support\Cards\CardCode;
use App\Support\Cards\RankOrder;

class SequenceLegality
{
    public static function expectedRankAt(int $startIndex, int $offset): string
    {
        $index = $startIndex + $offset;
        $rank = RankOrder::rankAt($index);

        if ($rank === null) {
            throw new SequenceRankOutOfBoundsException($index);
        }

        return $rank;
    }

    public static function resolveRole(string $code, string $expectedRank, string $suit): string
    {
        if (CardCode::isJoker($code)) {
            return 'wild';
        }

        if (CardCode::rank($code) === $expectedRank && CardCode::suit($code) === $suit) {
            return 'face';
        }

        if (CardCode::rank($code) === '2') {
            return 'wild';
        }

        throw new InvalidSequenceCardException($code, $expectedRank, $suit);
    }

    public static function resolveAceTrincaRole(string $code): string
    {
        if (CardCode::isJoker($code) || CardCode::rank($code) !== 'A') {
            throw new InvalidAceTrincaCardException($code);
        }

        return 'face';
    }

    /**
     * @param  array<int, array{code: string, role: string}>  $roledCards
     */
    public static function validateWildcardLimits(array $roledCards): void
    {
        $wildJokers = 0;
        $wildTwos = 0;

        foreach ($roledCards as $entry) {
            if ($entry['role'] !== 'wild') {
                continue;
            }

            if (CardCode::isJoker($entry['code'])) {
                $wildJokers++;
            } else {
                $wildTwos++;
            }
        }

        if ($wildJokers > 1) {
            throw new MaxWildJokerExceededException();
        }

        if ($wildTwos > 1) {
            throw new MaxWildTwoExceededException();
        }

        if ($wildJokers >= 1 && $wildTwos >= 1) {
            throw new WildcardCoexistenceException();
        }
    }

    /**
     * @param  array<int, array{code: string, role: string}>  $roledCards
     */
    public static function computeStatus(array $roledCards): string
    {
        if (count($roledCards) < 7) {
            return 'forming';
        }

        foreach ($roledCards as $entry) {
            if ($entry['role'] === 'wild' && ! CardCode::isJoker($entry['code'])) {
                return 'dirty';
            }
        }

        return 'clean';
    }
}
