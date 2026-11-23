<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Reports;

use DateTimeImmutable;
use DateTimeInterface;
use Lucacodorean\BaPanel\Reports\Enums\ReportStatus;

/**
 * Create the metadata of a new draft report. Composer gives the generator version.
 */
final readonly class ReportMetadataFactory
{
    /**
     * The report id format. The arguments are the date (YYYYMMDD) and 6 random hex characters.
     */
    private const string ID_FORMAT = 'FR-%s-%s';

    private const string ID_DATE_FORMAT = 'Ymd';

    private const int ID_RANDOM_BYTES = 3;

    public function __construct(
        private PackageVersion $version,
    ) {
    }

    /**
     * Create the metadata of a new draft report. All dates are the given time, in ISO-8601 format.
     * The default time is the current system time. A caller can give a fixed time, for example in a test.
     */
    public function create(?DateTimeImmutable $now = null): ReportMetadata
    {
        $now ??= new DateTimeImmutable();
        $timestamp = $now->format(DateTimeInterface::ATOM);

        return new ReportMetadata(
            reportId: sprintf(self::ID_FORMAT, $now->format(self::ID_DATE_FORMAT), bin2hex(random_bytes(self::ID_RANDOM_BYTES))),
            reportVersion: ReportMetadata::REPORT_VERSION,
            reportStatus: ReportStatus::Draft,
            author: ReportMetadata::AUTHOR,
            createdAt: $timestamp,
            updatedAt: $timestamp,
            generatedAt: $timestamp,
            generatorName: ReportMetadata::GENERATOR_NAME,
            generatorVersion: $this->version->version(),
        );
    }
}
