<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Reports;

use Lucacodorean\BaPanel\Core\Reports\Enums\ReportStatus;

/**
 * The system fields of a report. PHP fills these fields. The model does not see them.
 * ReportMetadataFactory creates the metadata of a new report.
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
    ) {}

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
}
