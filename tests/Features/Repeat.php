<?php

$GLOBALS['lastIteration'] = 0;

beforeEach(function () {
    $this->internalCount = 0;
});

afterAll(function() {
    expect($GLOBALS['lastIteration'])->toBe(10);
    $GLOBALS['lastIteration'] = 0;
});

it('can repeat a test a set number of times', function () {
    $GLOBALS['lastIteration']++;
    expect($this->getIteration()->iteration)->toBe($GLOBALS['lastIteration']);
})->repeat(10);

it('works in higher order tests')
    ->expect(true)->toBeTrue()
    ->repeat(2);

it('works with dataproviders', function () {
    expect(true)->toBeTrue();
})->repeat(10)->with([['foo'], ['bar']]);
