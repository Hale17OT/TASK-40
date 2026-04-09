<?php

use App\Domain\Cart\CartValidator;

test('valid note passes', function () {
    $v = new CartValidator();
    expect($v->validateNote('Extra ketchup please'))->toBeEmpty();
});

test('null note passes', function () {
    $v = new CartValidator();
    expect($v->validateNote(null))->toBeEmpty();
});

test('note at exactly 140 chars passes', function () {
    $v = new CartValidator();
    $note = str_repeat('a', 140);
    expect($v->validateNote($note))->toBeEmpty();
});

test('note over 140 chars fails', function () {
    $v = new CartValidator();
    $note = str_repeat('a', 141);
    $errors = $v->validateNote($note);
    expect($errors)->not->toBeEmpty();
    expect($errors[0])->toContain('140');
});

test('unicode note counts correctly', function () {
    $v = new CartValidator();
    // Each emoji is 1 mb character
    $note = str_repeat("\u{1F600}", 141);
    expect($v->validateNote($note))->not->toBeEmpty();
});

test('valid quantity passes', function () {
    $v = new CartValidator();
    expect($v->validateQuantity(1))->toBeEmpty();
    expect($v->validateQuantity(50))->toBeEmpty();
    expect($v->validateQuantity(99))->toBeEmpty();
});

test('zero quantity fails', function () {
    $v = new CartValidator();
    expect($v->validateQuantity(0))->not->toBeEmpty();
});

test('negative quantity fails', function () {
    $v = new CartValidator();
    expect($v->validateQuantity(-1))->not->toBeEmpty();
});

test('quantity over 99 fails', function () {
    $v = new CartValidator();
    expect($v->validateQuantity(100))->not->toBeEmpty();
});

test('getMaxNoteLength returns 140', function () {
    expect((new CartValidator())->getMaxNoteLength())->toBe(140);
});
