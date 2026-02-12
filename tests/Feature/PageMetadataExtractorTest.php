<?php

use App\Services\SiteCategoryDetector;

beforeEach(function () {
    $this->detector = new SiteCategoryDetector;
});

it('extracts page title from html', function () {
    $html = '<html><head><title>My AI Platform</title></head><body></body></html>';

    expect($this->detector->extractTitle($html))->toBe('My AI Platform');
});

it('extracts title with html entities', function () {
    $html = '<html><head><title>AI &amp; Machine Learning</title></head></html>';

    expect($this->detector->extractTitle($html))->toBe('AI & Machine Learning');
});

it('returns null for missing title', function () {
    $html = '<html><head></head><body>No title here</body></html>';

    expect($this->detector->extractTitle($html))->toBeNull();
});

it('returns null for empty title', function () {
    $html = '<html><head><title>   </title></head></html>';

    expect($this->detector->extractTitle($html))->toBeNull();
});

it('truncates very long titles', function () {
    $longTitle = str_repeat('A', 300);
    $html = "<html><head><title>{$longTitle}</title></head></html>";

    expect(mb_strlen($this->detector->extractTitle($html)))->toBe(255);
});

it('extracts meta description', function () {
    $html = '<html><head><meta name="description" content="A powerful AI tool for developers"></head></html>';

    expect($this->detector->extractDescription($html))->toBe('A powerful AI tool for developers');
});

it('extracts meta description with content before name', function () {
    $html = '<html><head><meta content="Build with AI" name="description"></head></html>';

    expect($this->detector->extractDescription($html))->toBe('Build with AI');
});

it('returns null for missing meta description', function () {
    $html = '<html><head><title>Test</title></head></html>';

    expect($this->detector->extractDescription($html))->toBeNull();
});

it('decodes html entities in description', function () {
    $html = '<html><head><meta name="description" content="AI &amp; ML &mdash; The Future"></head></html>';

    expect($this->detector->extractDescription($html))->toBe('AI & ML — The Future');
});
