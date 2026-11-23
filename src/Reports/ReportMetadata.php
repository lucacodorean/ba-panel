<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Reports;

use Composer\InstalledVersions;
use DateTimeImmutable;
use DateTimeInterface;
use Lucacodorean\BaPanel\Reports\Enums\ReportStatus;
use OutOfBoundsException;

/**
 * The system fields of a report. PHP fills these fields. The model does not see them.
 */
final readonly class ReportMetadata
{
    public const string REPORT_VERSION = '1.0';

    public const string AUTHOR = 'Feasibility agent';

    public const string GENERATOR_NAME = 'ba-panel';

    public const string PACKAGE_NAME = 'lucacodorean/ba-panel';

    /**
     * The generator version when Composer does not know the package version.
     */
    public const string UNKNOWN_VERSION = 'dev';

    /**
     * The report id format. The arguments are the date (YYYYMMDD) and 6 random hex characters.
     */
    private const string ID_FORMAT = 'FR-%s-%s';

    private const string ID_DATE_FORMAT = 'Ymd';

    private const int ID_RANDOM_BYTES = 3;

    public function __construct(
        public string $reportId,
        public string $reportVersion,
        public ReportStatus $reportStatus,
        public string $author,
        public string $createdAt,
        public string $updatedAt,
        public string $generatedAt,
        public string $generatorName,
        public string $generatorVersion,
    ) {
    }

    /**
     * Create the metadata of a new draft report. All dates are the given time, in ISO-8601 format.
     */
    public static function create(?DateTimeImmutable $now = null): self
    {
        $now ??= new DateTimeImmutable();
        $timestamp = $now->format(DateTimeInterface::ATOM);

        return new self(
            reportId: sprintf(self::ID_FORMAT, $now->format(self::ID_DATE_FORMAT), bin2hex(random_bytes(self::ID_RANDOM_BYTES))),
            reportVersion: self::REPORT_VERSION,
            reportStatus: ReportStatus::Draft,
            author: self::AUTHOR,
            createdAt: $timestamp,
            updatedAt: $timestamp,
            generatedAt: $timestamp,
            generatorName: self::GENERATOR_NAME,
            generatorVersion: self::generatorVersion(),
        );
    }

    /**
     * @return array{
     *     report_id: string,
     *     report_version: string,
     *     report_status: string,
     *     author: string,
     *     created_at: string,
     *     updated_at: string,
     *     generated_at: string,
     *     generator_name: string,
     *     generator_version: string
     * }
     */
    public function toArray(): array
    {
        return [
            'report_id' => $this->reportId,
            'report_version' => $this->reportVersion,
            'report_status' => $this->reportStatus->value,
            'author' => $this->author,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'generated_at' => $this->generatedAt,
            'generator_name' => $this->generatorName,
            'generator_version' => $this->generatorVersion,
        ];
    }

    /**
     * Return the installed package version. Return "dev" when Composer does not know the package.
     */
    private static function generatorVersion(): string
    {
        try {
            return InstalledVersions::getPrettyVersion(self::PACKAGE_NAME) ?? self::UNKNOWN_VERSION;
        } catch (OutOfBoundsException) {
            return self::UNKNOWN_VERSION;
        }
    }
}
