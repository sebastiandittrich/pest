<?php

$GLOBALS['lastCount'] = 0;
$GLOBALS['count']     = 0;

beforeEach(function () {
    $this->internalCount = 0;
});

it('can repeat a test a set number of times', function () {
    $GLOBALS['count']++;
    expect($this->internalCount)->toBe(0);
    $this->internalCount++;
    expect($GLOBALS['lastCount'])->toBe($GLOBALS['count'] - 1);
    $GLOBALS['lastCount']++;
})->repeat(10);

it('works in higher order tests')
    ->expect(true)->toBeTrue()
    ->repeat(2);

it('works with dataproviders', function () {
    expect(true)->toBeTrue();
})->repeat(10)->with([['foo'], ['bar']]);
