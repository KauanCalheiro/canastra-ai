<?php

use App\Exceptions\DomainException;

class ExampleNotFoundException extends DomainException
{
    public function __construct()
    {
        parent::__construct('example not found');
    }
}

it('derives the error code from the class name, stripping the Exception suffix', function () {
    $exception = new ExampleNotFoundException();

    expect($exception->errorCode())->toBe('example_not_found');
});

it('defaults status to 422 and context to an empty array', function () {
    $exception = new ExampleNotFoundException();

    expect($exception->status())->toBe(422);
    expect($exception->context())->toBe([]);
});
