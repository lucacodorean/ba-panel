<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Reports\Enums\ReportStatus;
use Lucacodorean\BaPanel\Reports\ReportMetadata;

it('creates the metadata of a new draft report', function (): void {
    $metadata = ReportMetadata::create(new DateTimeImmutable('2026-11-23T10:15:30+02:00'));

    expect($metadata->reportId)->toMatch('/^FR-20261123-[a-f0-9]{6}$/')
        ->and($metadata->reportVersion)->toBe('1.0')
        ->and($metadata->reportStatus)->toBe(ReportStatus::Draft)
        ->and($metadata->author)->toBe('Feasibility agent')
        ->and($metadata->generatorName)->toBe('ba-panel');
});

it('uses the given time in ISO-8601 format for all dates', function (): void {
    $metadata = ReportMetadata::create(new DateTimeImmutable('2026-11-23T10:15:30+02:00'));

    expect($metadata->createdAt)->toBe('2026-11-23T10:15:30+02:00')
        ->and($metadata->updatedAt)->toBe('2026-11-23T10:15:30+02:00')
        ->and($metadata->generatedAt)->toBe('2026-11-23T10:15:30+02:00');
});

it('uses the current time when no time is given', function (): void {
    $before = new DateTimeImmutable('-1 second');
    $metadata = ReportMetadata::create();
    $after = new DateTimeImmutable('+1 second');

    $created = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $metadata->createdAt);

    expect($created)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($created >= $before && $created <= $after)->toBeTrue()
        ->and($metadata->reportId)->toStartWith('FR-'.$created->format('Ymd').'-');
});

it('creates a new report id each time', function (): void {
    $now = new DateTimeImmutable('2026-11-23T10:15:30+00:00');

    $ids = array_map(static fn (): string => ReportMetadata::create($now)->reportId, range(1, 20));

    expect(array_unique($ids))->toHaveCount(20);
});

it('has a generator version', function (): void {
    expect(trim(ReportMetadata::create()->generatorVersion))->not->toBe('');
});

it('returns the metadata as an array with snake_case keys', function (): void {
    $metadata = ReportMetadata::create(new DateTimeImmutable('2026-11-23T10:15:30+00:00'));

    expect($metadata->toArray())->toBe([
        'report_id' => $metadata->reportId,
        'report_version' => '1.0',
        'report_status' => 'draft',
        'author' => 'Feasibility agent',
        'created_at' => '2026-11-23T10:15:30+00:00',
        'updated_at' => '2026-11-23T10:15:30+00:00',
        'generated_at' => '2026-11-23T10:15:30+00:00',
        'generator_name' => 'ba-panel',
        'generator_version' => $metadata->generatorVersion,
    ]);
});

it('is read only', function (): void {
    expect((new ReflectionClass(ReportMetadata::class))->isReadOnly())->toBeTrue();
});
