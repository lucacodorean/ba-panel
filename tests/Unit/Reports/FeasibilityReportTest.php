<?php

declare(strict_types=1);

use Lucacodorean\BaPanel\Reports\Enums\OverallVerdict;
use Lucacodorean\BaPanel\Reports\Enums\RequestType;
use Lucacodorean\BaPanel\Reports\FeasibilityReport;
use Lucacodorean\BaPanel\Reports\ReportMetadata;
use Lucacodorean\BaPanel\Tests\Support\FeasibilityReportFixture;
use NeuronAI\StructuredOutput\Validation\Validator;

/**
 * Return the fixture report with fixed metadata.
 *
 * @param  array<string, mixed>|null  $data
 */
function feasibilityReportWithMetadata(?array $data = null): FeasibilityReport
{
    return FeasibilityReportFixture::report($data)
        ->attachMetadata(ReportMetadata::create(new DateTimeImmutable('2026-11-23T10:15:30+00:00')));
}

/**
 * Return the Markdown lines that start with the prefix.
 *
 * @return list<string>
 */
function feasibilityReportLines(string $markdown, string $prefix): array
{
    return array_values(array_filter(explode("\n", $markdown), static fn (string $line): bool => str_starts_with($line, $prefix)));
}

/**
 * Group the consecutive table lines of the Markdown text.
 *
 * @return list<list<string>>
 */
function feasibilityReportTables(string $markdown): array
{
    $tables = [];
    $current = [];

    foreach (explode("\n", $markdown) as $line) {
        if (str_starts_with($line, '|')) {
            $current[] = $line;

            continue;
        }

        if ($current !== []) {
            $tables[] = $current;
            $current = [];
        }
    }

    if ($current !== []) {
        $tables[] = $current;
    }

    return $tables;
}

/**
 * Count the pipe characters that are not escaped.
 */
function feasibilityReportPipes(string $line): int
{
    return (int) preg_match_all('/(?<!\\\\)\|/', $line);
}

it('deserializes the fixture into a valid report', function (): void {
    $report = FeasibilityReportFixture::report();

    expect($report->requestType)->toBe(RequestType::FeatureRequest)
        ->and($report->overallVerdict)->toBe(OverallVerdict::FeasibleWithConditions)
        ->and(Validator::validate($report))->toBe([]);
});

it('sums the task estimates as the total effort', function (): void {
    expect(FeasibilityReportFixture::report()->effortTotalHours())->toBe(8.5);
});

it('returns a total effort of zero when no task is given', function (): void {
    expect(FeasibilityReportFixture::report(FeasibilityReportFixture::emptyListsData())->effortTotalHours())->toBe(0.0);
});

it('creates draft metadata when no metadata is attached', function (): void {
    $report = FeasibilityReportFixture::report();

    expect($report->metadata()->reportId)->toMatch('/^FR-\d{8}-[a-f0-9]{6}$/')
        ->and($report->metadata())->toBe($report->metadata());
});

it('returns the attached metadata', function (): void {
    $report = FeasibilityReportFixture::report();
    $metadata = ReportMetadata::create();

    expect($report->attachMetadata($metadata))->toBe($report)
        ->and($report->metadata())->toBe($metadata);
});

it('does not read metadata from the model output', function (): void {
    $report = FeasibilityReportFixture::report(FeasibilityReportFixture::data([
        'metadata' => ['reportId' => 'FR-FAKE'],
        'reportId' => 'FR-FAKE',
    ]));

    expect($report->metadata()->reportId)->not->toBe('FR-FAKE');
});

it('returns the report as an array with snake_case keys', function (): void {
    $array = feasibilityReportWithMetadata()->toArray();

    expect($array)->toHaveKeys([
        'metadata', 'request_title', 'request_type', 'priority', 'linked_tickets', 'acceptance_criteria',
        'alternatives', 'affected_components', 'data_model_changes', 'api_changes', 'dependencies',
        'non_functional_impact', 'effort_breakdown', 'effort_total_hours', 'risks', 'feasibility',
        'overall_verdict', 'verdict_confidence', 'open_questions', 'references',
    ]);

    foreach (array_keys($array) as $key) {
        expect($key)->toMatch('/^[a-z0-9_]+$/');
    }
});

it('returns the enum values in the array', function (): void {
    $array = feasibilityReportWithMetadata()->toArray();

    expect($array['request_type'])->toBe('feature_request')
        ->and($array['priority'])->toBe('high')
        ->and($array['complexity'])->toBe('medium')
        ->and($array['overall_verdict'])->toBe('feasible_with_conditions')
        ->and($array['verdict_confidence'])->toBe('medium');
});

it('returns the metadata and the calculated fields in the array', function (): void {
    $report = feasibilityReportWithMetadata();
    $array = $report->toArray();

    expect($array['metadata'])->toBe($report->metadata()->toArray())
        ->and($array['effort_total_hours'])->toBe(8.5)
        ->and($array['risks'][0]['severity'])->toBe(8)
        ->and($array['story_points'])->toBe(5);
});

it('returns the nested objects as arrays', function (): void {
    $array = feasibilityReportWithMetadata()->toArray();

    expect($array['non_functional_impact']['security'])->toBe(['level' => 'medium', 'details' => 'The export must respect the invoice permissions.'])
        ->and($array['feasibility']['schedule'])->toBe(['rating' => 'feasible_with_conditions', 'justification' => 'The release date is close.'])
        ->and($array['affected_components'][0])->toBe([
            'component' => 'InvoiceController',
            'layer' => 'backend',
            'change_type' => 'modify',
            'impact' => 'medium',
            'owner' => 'Billing team',
        ])
        ->and($array['effort_breakdown'][1])->toBe([
            'task' => 'Frontend button',
            'role' => 'Frontend developer',
            'estimate_hours' => 2.5,
            'confidence' => 'high',
        ]);
});

it('starts the Markdown with the report title and ends with one line break', function (): void {
    $markdown = feasibilityReportWithMetadata()->toMarkdown();

    expect($markdown)->toStartWith('# Feasibility Report: Export invoices as CSV')
        ->and($markdown)->toEndWith("</sub>\n")
        ->and($markdown)->not->toEndWith("\n\n");
});

it('replaces every placeholder in the Markdown', function (): void {
    $markdown = feasibilityReportWithMetadata()->toMarkdown();

    expect($markdown)->not->toContain('{{')
        ->and($markdown)->not->toContain('}}')
        ->and($markdown)->not->toContain('{!!')
        ->and($markdown)->not->toContain('!!}')
        ->and($markdown)->not->toContain('@forelse')
        ->and($markdown)->not->toContain('@empty')
        ->and($markdown)->not->toContain('@endforelse')
        ->and($markdown)->not->toContain('Md::');
});

it('does not put comments in the Markdown', function (): void {
    $markdown = feasibilityReportWithMetadata()->toMarkdown();

    expect($markdown)->not->toContain('<!--')
        ->and($markdown)->not->toContain('-->')
        ->and($markdown)->not->toContain('change_request | feature_request');
});

it('has the sections in order', function (): void {
    $markdown = feasibilityReportWithMetadata()->toMarkdown();
    $headings = array_map(static fn (string $line): string => substr($line, 3), feasibilityReportLines($markdown, '## '));

    expect($headings)->toBe([
        '1. Metadata',
        '2. Request Summary',
        '3. Scope',
        '4. Technical Analysis',
        '5. Non-Functional Impact',
        '6. Effort Estimation',
        '7. Risk Assessment',
        '8. Technical Debt',
        '9. Feasibility Verdict',
        '10. Open Questions',
        '11. References',
    ]);
});

it('shows the metadata values in the Markdown', function (): void {
    $report = feasibilityReportWithMetadata();
    $metadata = $report->metadata();
    $markdown = $report->toMarkdown();

    expect($markdown)->toContain('| Report ID      | '.$metadata->reportId.' |')
        ->and($markdown)->toContain('| Report version | 1.0 |')
        ->and($markdown)->toContain('| Status         | draft |')
        ->and($markdown)->toContain('| Analysed by    | Feasibility agent |')
        ->and($markdown)->toContain('| Created at     | 2026-11-23T10:15:30+00:00 |')
        ->and($markdown)->toContain('| Last updated   | 2026-11-23T10:15:30+00:00 |')
        ->and($markdown)->toContain('<sub>Generated by ba-panel v'.$metadata->generatorVersion.' on 2026-11-23T10:15:30+00:00</sub>');
});

it('shows the report values in the Markdown', function (): void {
    $markdown = feasibilityReportWithMetadata()->toMarkdown();

    expect($markdown)->toContain('| Request type   | feature_request |')
        ->and($markdown)->toContain('| Linked tickets | FIN-101, FIN-102 |')
        ->and($markdown)->toContain("### 2.5 Acceptance Criteria\n- The export contains all filtered invoices.\n- The file uses UTF-8.")
        ->and($markdown)->toContain('| 1 | Synchronous export | Simple | Slow for large sets | Low | Yes |')
        ->and($markdown)->toContain('| 2 | Queued export | Scales | More parts | Medium | No |')
        ->and($markdown)->toContain('| invoices | Add an index on issued_at | Yes | Yes | No |')
        ->and($markdown)->toContain('- Threats identified:'."\n".'  - CSV injection')
        ->and($markdown)->toContain('| Total estimate           | 8.5 h |')
        ->and($markdown)->toContain('| Estimate range (min–max) | 6–12 h |')
        ->and($markdown)->toContain('| Backend endpoint | Backend developer | 6 | medium |')
        ->and($markdown)->toContain('**Overall verdict:** feasible_with_conditions')
        ->and($markdown)->toContain("## 11. References\n- docs/invoices.md");
});

it('shows the calculated severity in the risk table', function (): void {
    $markdown = feasibilityReportWithMetadata()->toMarkdown();

    expect($markdown)->toContain('| R1 | Large exports time out. | 2 | 4 | 8 | Limit the rows. | Billing team |');
});

it('escapes the pipe character and removes line breaks in table cells', function (): void {
    $markdown = feasibilityReportWithMetadata(FeasibilityReportFixture::data([
        'risks' => [
            ['risk' => "Rows | columns\nbreak", 'probability' => 3, 'impact' => 3, 'mitigation' => 'a|b', 'owner' => 'Team'],
        ],
    ]))->toMarkdown();

    expect($markdown)->toContain('| R1 | Rows \| columns break | 3 | 3 | 9 | a\|b | Team |');
});

it('keeps the same column count in each table row', function (): void {
    $data = FeasibilityReportFixture::data([
        'requester' => "Team | A\nB",
        'risks' => [
            ['risk' => 'A | B', 'probability' => 1, 'impact' => 1, 'mitigation' => "x\ny", 'owner' => '|'],
        ],
    ]);

    foreach ([$data, FeasibilityReportFixture::emptyListsData()] as $variant) {
        $tables = feasibilityReportTables(feasibilityReportWithMetadata($variant)->toMarkdown());

        expect($tables)->toHaveCount(12);

        foreach ($tables as $table) {
            $columns = feasibilityReportPipes($table[0]);

            foreach ($table as $line) {
                expect(feasibilityReportPipes($line))->toBe($columns, 'Line: '.$line);
            }
        }
    }
});

it('shows "- None" for each empty list', function (): void {
    $markdown = feasibilityReportWithMetadata(FeasibilityReportFixture::emptyListsData())->toMarkdown();

    expect($markdown)->toContain("### 2.5 Acceptance Criteria\n- None")
        ->and($markdown)->toContain("### 3.1 In Scope\n- None")
        ->and($markdown)->toContain("### 3.2 Out of Scope\n- None")
        ->and($markdown)->toContain("### 3.3 Assumptions\n- None")
        ->and($markdown)->toContain("### 3.4 Constraints\n- None")
        ->and($markdown)->toContain("### 5.3 Configuration & Infrastructure Changes\n- None")
        ->and($markdown)->toContain("**Conditions for approval:**\n- None")
        ->and($markdown)->toContain("## 11. References\n- None")
        ->and($markdown)->toContain('- Threats identified: None')
        ->and($markdown)->toContain('- Follow-up tasks: None')
        ->and($markdown)->toContain('| Linked tickets | None |');
});

it('shows a "None" row for each empty table', function (): void {
    $markdown = feasibilityReportWithMetadata(FeasibilityReportFixture::emptyListsData())->toMarkdown();

    $lines = array_count_values(explode("\n", $markdown));

    expect($lines['| - | None | | | | |'] ?? 0)->toBe(1)
        ->and($lines['| - | None | | | | | |'] ?? 0)->toBe(1)
        ->and($lines['| - | None | | | |'] ?? 0)->toBe(1)
        ->and($lines['| None | | | | |'] ?? 0)->toBe(4)
        ->and($lines['| None | | | |'] ?? 0)->toBe(1);
});

it('shows "Not specified" for a missing story point estimate', function (): void {
    $markdown = feasibilityReportWithMetadata(FeasibilityReportFixture::data(['storyPoints' => null]))->toMarkdown();

    expect($markdown)->toContain('| Story points             | Not specified |');
});

it('shows the total effort of zero when no task is given', function (): void {
    $markdown = feasibilityReportWithMetadata(FeasibilityReportFixture::emptyListsData())->toMarkdown();

    expect($markdown)->toContain('| Total estimate           | 0 h |');
});

it('does not escape HTML in the Markdown', function (): void {
    $markdown = feasibilityReportWithMetadata(FeasibilityReportFixture::data(['recommendation' => 'Use <b>care</b> & test.']))->toMarkdown();

    expect($markdown)->toContain('Use <b>care</b> & test.')
        ->and($markdown)->not->toContain('&lt;b&gt;')
        ->and($markdown)->not->toContain('&amp;');
});
