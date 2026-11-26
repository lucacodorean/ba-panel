<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\Reports\Enums;

/**
 * The review status of the report.
 */
enum ReportStatus: string
{
    case Draft = 'draft';

    case InReview = 'in_review';

    case Approved = 'approved';

    case Rejected = 'rejected';

    case Superseded = 'superseded';
}
