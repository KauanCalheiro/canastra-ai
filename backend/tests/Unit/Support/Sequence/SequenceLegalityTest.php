<?php

use App\Exceptions\Sequence\InvalidAceTrincaCardException;
use App\Exceptions\Sequence\InvalidSequenceCardException;
use App\Exceptions\Sequence\MaxWildJokerExceededException;
use App\Exceptions\Sequence\MaxWildTwoExceededException;
use App\Exceptions\Sequence\SequenceRankOutOfBoundsException;
use App\Exceptions\Sequence\WildcardCoexistenceException;
use App\Support\Sequence\SequenceLegality;

it('computes the expected rank at a position from the start index', function () {
    expect(SequenceLegality::expectedRankAt(2, 0))->toBe('3'); // index 2 = '3'
    expect(SequenceLegality::expectedRankAt(2, 1))->toBe('4');
});

it('throws when a position would fall before A or after K', function () {
    expect(fn () => SequenceLegality::expectedRankAt(0, -1))->toThrow(SequenceRankOutOfBoundsException::class);
    expect(fn () => SequenceLegality::expectedRankAt(11, 2))->toThrow(SequenceRankOutOfBoundsException::class); // index 11 = Q, +2 = 13 (out of bounds)
});

it('resolves a joker as wild regardless of expected rank/suit', function () {
    expect(SequenceLegality::resolveRole('W', '6', 'H'))->toBe('wild');
});

it('resolves a card matching the expected rank and suit as face', function () {
    expect(SequenceLegality::resolveRole('6H', '6', 'H'))->toBe('face');
});

it('resolves a 2 of any suit placed at a non-2 position as wild', function () {
    expect(SequenceLegality::resolveRole('2D', '6', 'H'))->toBe('wild');
});

it('resolves a 2 of the sequence suit at the 2 position as face', function () {
    expect(SequenceLegality::resolveRole('2H', '2', 'H'))->toBe('face');
});

it('throws when a card does not match the position and is not a valid wildcard', function () {
    expect(fn () => SequenceLegality::resolveRole('9C', '6', 'H'))->toThrow(InvalidSequenceCardException::class);
});

it('resolves an ace as face for an ace-trinca', function () {
    expect(SequenceLegality::resolveAceTrincaRole('AS'))->toBe('face');
});

it('throws when an ace-trinca receives a non-ace or a wildcard', function () {
    expect(fn () => SequenceLegality::resolveAceTrincaRole('2S'))->toThrow(InvalidAceTrincaCardException::class);
    expect(fn () => SequenceLegality::resolveAceTrincaRole('W'))->toThrow(InvalidAceTrincaCardException::class);
});

it('allows at most one wild joker', function () {
    $roled = [
        ['code' => 'W', 'role' => 'wild'],
        ['code' => 'W', 'role' => 'wild'],
    ];
    expect(fn () => SequenceLegality::validateWildcardLimits($roled))->toThrow(MaxWildJokerExceededException::class);
});

it('allows at most one wild two', function () {
    $roled = [
        ['code' => '2D', 'role' => 'wild'],
        ['code' => '2C', 'role' => 'wild'],
    ];
    expect(fn () => SequenceLegality::validateWildcardLimits($roled))->toThrow(MaxWildTwoExceededException::class);
});

it('forbids a wild joker and a wild two coexisting', function () {
    $roled = [
        ['code' => 'W', 'role' => 'wild'],
        ['code' => '2D', 'role' => 'wild'],
    ];
    expect(fn () => SequenceLegality::validateWildcardLimits($roled))->toThrow(WildcardCoexistenceException::class);
});

it('allows a face two alongside a wild joker', function () {
    $roled = [
        ['code' => 'W', 'role' => 'wild'],
        ['code' => '2H', 'role' => 'face'],
    ];
    SequenceLegality::validateWildcardLimits($roled);
})->throwsNoExceptions();

it('computes forming for fewer than 7 cards', function () {
    $roled = array_fill(0, 6, ['code' => '3H', 'role' => 'face']);
    expect(SequenceLegality::computeStatus($roled))->toBe('forming');
});

it('computes clean for 7+ cards with no wild two', function () {
    $roled = array_merge(array_fill(0, 6, ['code' => '3H', 'role' => 'face']), [['code' => 'W', 'role' => 'wild']]);
    expect(SequenceLegality::computeStatus($roled))->toBe('clean');
});

it('computes dirty for 7+ cards with a wild two', function () {
    $roled = array_merge(array_fill(0, 6, ['code' => '3H', 'role' => 'face']), [['code' => '2D', 'role' => 'wild']]);
    expect(SequenceLegality::computeStatus($roled))->toBe('dirty');
});
