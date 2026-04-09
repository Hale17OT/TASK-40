<?php

use App\Domain\Risk\ProfanityFilter;

test('blocks exact word match', function () {
    $filter = new ProfanityFilter(['badword', 'scam']);
    $result = $filter->check('badword');
    expect($result['blocked'])->toBeTrue();
    expect($result['matched_word'])->toBe('badword');
});

test('blocks case insensitive match', function () {
    $filter = new ProfanityFilter(['badword']);
    $result = $filter->check('BADWORD');
    expect($result['blocked'])->toBeTrue();
});

test('blocks substring match', function () {
    $filter = new ProfanityFilter(['scam']);
    $result = $filter->check('this is a scammer query');
    expect($result['blocked'])->toBeTrue();
    expect($result['matched_word'])->toBe('scam');
});

test('allows clean queries', function () {
    $filter = new ProfanityFilter(['badword', 'scam']);
    $result = $filter->check('delicious burger');
    expect($result['blocked'])->toBeFalse();
    expect($result['matched_word'])->toBeNull();
});

test('handles empty query', function () {
    $filter = new ProfanityFilter(['badword']);
    $result = $filter->check('');
    expect($result['blocked'])->toBeFalse();
});

test('returns message when blocked', function () {
    $filter = new ProfanityFilter(['badword']);
    $result = $filter->check('badword');
    expect($result['message'])->toContain('not allowed');
});

test('getSuggestion returns trending terms', function () {
    $filter = new ProfanityFilter([]);
    $suggestion = $filter->getSuggestion(['burger', 'pizza', 'salad', 'wings', 'tacos', 'sushi']);
    expect($suggestion)->toContain('burger');
    expect($suggestion)->toContain('pizza');
    expect($suggestion)->toStartWith('Try: ');
});

test('getSuggestion with empty terms', function () {
    $filter = new ProfanityFilter([]);
    $suggestion = $filter->getSuggestion([]);
    expect($suggestion)->toContain('browsing');
});
