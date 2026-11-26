<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Core\Reports\Enums\ReportStatus;
use Lucacodorean\BaPanel\Core\Reports\PackageVersion;
use Lucacodorean\BaPanel\Core\Reports\ReportMetadata;
use Lucacodorean\BaPanel\Core\Reports\ReportMetadataFactory;

/**
 * Build the metadata factory with the real package version.
 */
function reportMetadataFactory(): ReportMetadataFactory
{
    return new ReportMetadataFactory(new PackageVersion);
}

/**
 * Create metadata at the given fixed time.
 */
function reportMetadataAt(string $time = '2026-11-23T10:15:30+02:00'): ReportMetadata
{
    return reportMetadataFactory()->create(new DateTimeImmutable($time));
}

it('creates the metadata of a new draft report', function (): void {
    $metadata = reportMetadataAt();

    expect($metadata->reportId)->toMatch('/^FR-20261123-[a-f0-9]{6}$/')
        ->and($metadata->reportVersion)->toBe(ReportMetadata::REPORT_VERSION)
        ->and($metadata->reportVersion)->toBe('1.0')
        ->and($metadata->reportStatus)->toBe(ReportStatus::Draft)
        ->and($metadata->author)->toBe('Feasibility agent')
        ->and($metadata->generatorName)->toBe('ba-panel');
});

it('uses the given time in ISO-8601 format for all dates', function (): void {
    $metadata = reportMetadataAt('2026-01-02T03:04:05+00:00');

    expect($metadata->createdAt)->toBe('2026-01-02T03:04:05+00:00')
        ->and($metadata->updatedAt)->toBe('2026-01-02T03:04:05+00:00')
        ->and($metadata->generatedAt)->toBe('2026-01-02T03:04:05+00:00')
        ->and($metadata->reportId)->toStartWith('FR-20260102-');
});

it('uses the date of the given time in the report id', function (string $time, string $date): void {
    expect(reportMetadataAt($time)->reportId)->toMatch('/^FR-'.$date.'-[a-f0-9]{6}$/');
})->with([
    'start of year' => ['2027-01-01T00:00:00+00:00', '20270101'],
    'end of year' => ['2025-12-31T23:59:59+00:00', '20251231'],
    'leap day' => ['2028-02-29T12:00:00+00:00', '20280229'],
]);

it('uses the date in the time zone of the given time for the report id', function (): void {
    // In UTC this time is on 2026-11-22. The time zone of the given time gives 2026-11-23.
    $metadata = reportMetadataAt('2026-11-23T01:00:00+02:00');

    expect($metadata->reportId)->toStartWith('FR-20261123-')
        ->and($metadata->createdAt)->toBe('2026-11-23T01:00:00+02:00');
});

it('uses the current time when no time is given', function (): void {
    // The ISO-8601 format has no fractions of a second. Thus the start is rounded down to the second.
    $before = new DateTimeImmutable('@'.time());
    $metadata = reportMetadataFactory()->create();
    $after = new DateTimeImmutable;

    $created = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $metadata->createdAt);

    expect($created)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($created >= $before && $created <= $after)->toBeTrue()
        ->and($metadata->updatedAt)->toBe($metadata->createdAt)
        ->and($metadata->generatedAt)->toBe($metadata->createdAt)
        ->and($metadata->reportId)->toMatch('/^FR-\d{8}-[a-f0-9]{6}$/');
});

it('uses a new current time on each call without a time', function (): void {
    $factory = reportMetadataFactory();
    $first = $factory->create();
    $given = $factory->create(new DateTimeImmutable('2000-01-01T00:00:00+00:00'));
    $second = $factory->create();

    // A given time on one call does not change the time of the next call.
    expect($given->createdAt)->toBe('2000-01-01T00:00:00+00:00')
        ->and($second->createdAt)->not->toBe('2000-01-01T00:00:00+00:00')
        ->and($second->createdAt >= $first->createdAt)->toBeTrue();
});

it('creates a new report id each time', function (): void {
    $factory = reportMetadataFactory();

    $ids = array_map(static fn (): string => $factory->create()->reportId, range(1, 20));

    expect(array_unique($ids))->toHaveCount(20);
});

it('uses the package version as the generator version', function (): void {
    $version = (new PackageVersion)->version();

    expect(reportMetadataAt()->generatorVersion)->toBe($version)
        ->and(trim($version))->not->toBe('');
});

it('returns the metadata as an array with snake_case keys', function (): void {
    $metadata = reportMetadataAt('2026-11-23T10:15:30+00:00');

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
