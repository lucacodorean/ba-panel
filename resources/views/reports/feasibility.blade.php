@use('Lucacodorean\BaPanel\Reports\MarkdownText', 'Md')
{{--
  Feasibility report: change requests and feature requests.
  The output is Markdown, not HTML. Use raw echoes. Send each value through MarkdownText:
  - Md::cell() for a value in a table cell. It escapes "|" and removes line breaks.
  - Md::inline() for a value on one line, for example in a heading or a bullet.
  - Md::text() for a block of text.
  - Md::bullets() for a list. It shows "- None" when the list is empty.
  Variables: $report (FeasibilityReport) and $metadata (ReportMetadata).
--}}
# Feasibility Report: {!! Md::inline($report->requestTitle) !!}

## 1. Metadata

| Field          | Value |
|----------------|-------|
| Report ID      | {!! Md::cell($metadata->reportId) !!} |
| Report version | {!! Md::cell($metadata->reportVersion) !!} |
| Request type   | {!! Md::cell($report->requestType) !!} |{{-- change_request | feature_request --}}
| Status         | {!! Md::cell($metadata->reportStatus) !!} |{{-- draft | in_review | approved | rejected | superseded --}}
| Priority       | {!! Md::cell($report->priority) !!} |{{-- critical | high | medium | low --}}
| Requested by   | {!! Md::cell($report->requester) !!} |
| Analysed by    | {!! Md::cell($metadata->author) !!} |
| Created at     | {!! Md::cell($metadata->createdAt) !!} |
| Last updated   | {!! Md::cell($metadata->updatedAt) !!} |
| Target release | {!! Md::cell($report->targetRelease) !!} |
| Linked tickets | {!! Md::cellList($report->linkedTickets) !!} |

---

## 2. Request Summary

### 2.1 Description
{!! Md::text($report->requestDescription) !!}

### 2.2 Current Behaviour
{!! Md::text($report->currentBehaviour) !!}

### 2.3 Requested Behaviour
{!! Md::text($report->requestedBehaviour) !!}

### 2.4 Technical Motivation
{!! Md::text($report->technicalMotivation) !!}

### 2.5 Acceptance Criteria
{!! Md::bullets($report->acceptanceCriteria) !!}

---

## 3. Scope

### 3.1 In Scope
{!! Md::bullets($report->inScope) !!}

### 3.2 Out of Scope
{!! Md::bullets($report->outOfScope) !!}

### 3.3 Assumptions
{!! Md::bullets($report->assumptions) !!}

### 3.4 Constraints
{!! Md::bullets($report->constraints) !!}

---

## 4. Technical Analysis

### 4.1 Current Architecture Context
{!! Md::text($report->currentArchitecture) !!}

### 4.2 Proposed Solution
{!! Md::text($report->proposedSolution) !!}

### 4.3 Alternatives Considered

| # | Approach | Pros | Cons | Effort | Selected |
|---|----------|------|------|--------|----------|
@forelse ($report->alternatives as $alternative)
| {{ $loop->iteration }} | {!! Md::cell($alternative->approach) !!} | {!! Md::cell($alternative->pros) !!} | {!! Md::cell($alternative->cons) !!} | {!! Md::cell($alternative->effort) !!} | {!! Md::cell($alternative->selected) !!} |
@empty
| - | {{ Md::NONE }} | | | | |
@endforelse

**Selection rationale:** {!! Md::inline($report->alternativeSelectionRationale) !!}

### 4.4 Affected Components

| Component | Layer | Change type | Impact | Owner |
|---|---|---|---|---|
@forelse ($report->affectedComponents as $component)
| {!! Md::cell($component->component) !!} | {!! Md::cell($component->layer) !!} | {!! Md::cell($component->changeType) !!} | {!! Md::cell($component->impact) !!} | {!! Md::cell($component->owner) !!} |
@empty
| {{ Md::NONE }} | | | | |
@endforelse{{-- Layer: frontend | backend | database | infra | integration | ci_cd. Change type: new | modify | deprecate | remove. Impact: high | medium | low. --}}

### 4.5 Data Model Changes

| Entity / Table | Change | Migration required | Reversible | Data backfill |
|---|---|---|---|---|
@forelse ($report->dataModelChanges as $change)
| {!! Md::cell($change->entity) !!} | {!! Md::cell($change->change) !!} | {!! Md::cell($change->migrationRequired) !!} | {!! Md::cell($change->reversible) !!} | {!! Md::cell($change->dataBackfill) !!} |
@empty
| {{ Md::NONE }} | | | | |
@endforelse

**Migration notes:** {!! Md::inline($report->migrationNotes) !!}

### 4.6 API / Contract Changes

| Endpoint / Interface | Method | Change | Breaking | Versioning strategy |
|---|---|---|---|---|
@forelse ($report->apiChanges as $apiChange)
| {!! Md::cell($apiChange->endpoint) !!} | {!! Md::cell($apiChange->method) !!} | {!! Md::cell($apiChange->change) !!} | {!! Md::cell($apiChange->breaking) !!} | {!! Md::cell($apiChange->versioningStrategy) !!} |
@empty
| {{ Md::NONE }} | | | | |
@endforelse

### 4.7 Dependencies

| Dependency | Type | Version | Status | Risk |
|---|---|---|---|---|
@forelse ($report->dependencies as $dependency)
| {!! Md::cell($dependency->name) !!} | {!! Md::cell($dependency->type) !!} | {!! Md::cell($dependency->version) !!} | {!! Md::cell($dependency->status) !!} | {!! Md::cell($dependency->risk) !!} |
@empty
| {{ Md::NONE }} | | | | |
@endforelse{{-- Type: library | internal_service | external_api | team | infrastructure. Status: available | needs_upgrade | needs_procurement | blocked. --}}

---

## 5. Non-Functional Impact

| Area                       | Impact | Details |
|----------------------------|--------|---------|
| Backward compatibility     | {!! Md::cell($report->backwardCompatibility->level) !!} | {!! Md::cell($report->backwardCompatibility->details) !!} |
| Performance                | {!! Md::cell($report->performance->level) !!} | {!! Md::cell($report->performance->details) !!} |
| Scalability                | {!! Md::cell($report->scalability->level) !!} | {!! Md::cell($report->scalability->details) !!} |
| Security                   | {!! Md::cell($report->security->level) !!} | {!! Md::cell($report->security->details) !!} |
| Privacy / Compliance       | {!! Md::cell($report->privacy->level) !!} | {!! Md::cell($report->privacy->details) !!} |
| Availability / Reliability | {!! Md::cell($report->availability->level) !!} | {!! Md::cell($report->availability->details) !!} |
| Observability              | {!! Md::cell($report->observability->level) !!} | {!! Md::cell($report->observability->details) !!} |
| Maintainability            | {!! Md::cell($report->maintainability->level) !!} | {!! Md::cell($report->maintainability->details) !!} |{{-- Impact levels: none | low | medium | high --}}

### 5.1 Performance Budget
- Expected load: {!! Md::inline($report->expectedLoad) !!}
- Latency target (p95): {!! Md::inline($report->latencyTargetP95) !!}
- Resource overhead (CPU / memory / storage): {!! Md::inline($report->resourceOverhead) !!}

### 5.2 Security Considerations
- Authentication / authorisation changes: {!! Md::inline($report->authzChanges) !!}
- Data classification touched: {!! Md::inline($report->dataClassification) !!}
- Threats identified:{!! Md::nestedBullets($report->securityThreats) !!}

### 5.3 Configuration & Infrastructure Changes
{!! Md::bullets($report->infraChanges) !!}

---

## 6. Effort Estimation

| Task | Role | Estimate (h) | Confidence |
|---|---|---|---|
@forelse ($report->effortBreakdown as $task)
| {!! Md::cell($task->task) !!} | {!! Md::cell($task->role) !!} | {!! Md::cell($task->estimateHours) !!} | {!! Md::cell($task->confidence) !!} |
@empty
| {{ Md::NONE }} | | | |
@endforelse{{-- Confidence: high | medium | low --}}

| Metric                   | Value |
|--------------------------|-------|
| Total estimate           | {!! Md::cell($report->effortTotalHours()) !!} h |
| Estimate range (min–max) | {!! Md::cell($report->effortMinHours) !!}–{!! Md::cell($report->effortMaxHours) !!} h |
| Story points             | {!! Md::cell($report->storyPoints) !!} |
| Complexity               | {!! Md::cell($report->complexity) !!} |{{-- trivial | low | medium | high | very_high --}}
| Estimation method        | {!! Md::cell($report->estimationMethod) !!} |

---

## 7. Risk Assessment

| ID | Risk | Probability | Impact | Severity | Mitigation | Owner |
|----|------|-------------|--------|----------|------------|-------|
@forelse ($report->risks as $risk)
| R{{ $loop->iteration }} | {!! Md::cell($risk->risk) !!} | {!! Md::cell($risk->probability) !!} | {!! Md::cell($risk->impact) !!} | {!! Md::cell($risk->severity()) !!} | {!! Md::cell($risk->mitigation) !!} | {!! Md::cell($risk->owner) !!} |
@empty
| - | {{ Md::NONE }} | | | | | |
@endforelse{{-- Probability / Impact: 1–5. Severity = Probability × Impact. PHP calculates the severity. --}}

---

## 8. Technical Debt

- Debt introduced: {!! Md::inline($report->techDebtIntroduced) !!}
- Debt resolved: {!! Md::inline($report->techDebtResolved) !!}
- Follow-up tasks:{!! Md::nestedBullets($report->techDebtFollowups) !!}

---

## 9. Feasibility Verdict

| Dimension   | Rating | Justification |
|-------------|--------|---------------|
| Technical   | {!! Md::cell($report->technicalFeasibility->rating) !!} | {!! Md::cell($report->technicalFeasibility->justification) !!} |
| Operational | {!! Md::cell($report->operationalFeasibility->rating) !!} | {!! Md::cell($report->operationalFeasibility->justification) !!} |
| Schedule    | {!! Md::cell($report->scheduleFeasibility->rating) !!} | {!! Md::cell($report->scheduleFeasibility->justification) !!} |
| Resource    | {!! Md::cell($report->resourceFeasibility->rating) !!} | {!! Md::cell($report->resourceFeasibility->justification) !!} |{{-- Rating: feasible | feasible_with_conditions | not_feasible --}}

**Overall verdict:** {!! Md::inline($report->overallVerdict) !!}{{-- feasible | feasible_with_conditions | not_feasible | needs_more_info --}}

**Confidence:** {!! Md::inline($report->verdictConfidence) !!}{{-- high | medium | low --}}

**Conditions for approval:**
{!! Md::bullets($report->approvalConditions) !!}

**Recommendation:**
{!! Md::text($report->recommendation) !!}

---

## 10. Open Questions

| # | Question | Addressed to | Blocking | Status |
|---|---|---|---|---|
@forelse ($report->openQuestions as $question)
| {{ $loop->iteration }} | {!! Md::cell($question->question) !!} | {!! Md::cell($question->addressedTo) !!} | {!! Md::cell($question->blocking) !!} | {!! Md::cell($question->status) !!} |
@empty
| - | {{ Md::NONE }} | | | |
@endforelse

---

## 11. References
{!! Md::bullets($report->references) !!}

---
<sub>Generated by {!! Md::inline($metadata->generatorName) !!} v{!! Md::inline($metadata->generatorVersion) !!} on {!! Md::inline($metadata->generatedAt) !!}</sub>
