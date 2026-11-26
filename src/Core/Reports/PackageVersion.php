<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Reports;

use Composer\InstalledVersions;
use OutOfBoundsException;

/**
 * Read the installed version of the package from Composer.
 */
final readonly class PackageVersion
{
    /**
     * Return the installed package version. Return "dev" when Composer does not know the package.
     */
    public function version(): string
    {
        try {
            return InstalledVersions::getPrettyVersion(ReportMetadata::PACKAGE_NAME) ?? ReportMetadata::UNKNOWN_VERSION;
        } catch (OutOfBoundsException) {
            return ReportMetadata::UNKNOWN_VERSION;
        }
    }
}
