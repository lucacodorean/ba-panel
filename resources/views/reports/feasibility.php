<?php
/*
  Feasibility report: change requests and feature requests.
  The output is Markdown, not HTML. Use raw echoes. Send each value through MarkdownText:
  - Md::cell() for a value in a table cell. It escapes "|" and removes line breaks.
  - Md::inline() for a value on one line, for example in a heading or a bullet.
  - Md::text() for a block of text.
  - Md::bullets() for a list. It shows "- None" when the list is empty.
  Variables: $report (FeasibilityReport), $metadata (ReportMetadata) and the $e escape helper.
  PHP removes the line break directly after "?>". An echo at the end of a line adds "\n" to keep the line break.
  A line that has only a PHP block makes no output.
*/

use Lucacodorean\BaPanel\Core\Reports\MarkdownText as Md;

/** @var \Lucacodorean\BaPanel\Core\Reports\FeasibilityReport $report */
/** @var \Lucacodorean\BaPanel\Core\Reports\ReportMetadata $metadata */
/** @var \Closure(string|int|float|bool|\Stringable|null): string $e */
?>
# Feasibility Report: <?= Md::inline($report->requestTitle), "\n" ?>

## 1. Metadata

<?php /* Request type: change_request | feature_request. Status: draft | in_review | approved | rejected | superseded. Priority: critical | high | medium | low. */ ?>
| Field          | Value |
|----------------|-------|
| Report ID      | <?= Md::cell($metadata->reportId) ?> |
| Report version | <?= Md::cell($metadata->reportVersion) ?> |
| Request type   | <?= Md::cell($report->requestType) ?> |
| Status         | <?= Md::cell($metadata->reportStatus) ?> |
| Priority       | <?= Md::cell($report->priority) ?> |
| Requested by   | <?= Md::cell($report->requester) ?> |
| Analysed by    | <?= Md::cell($metadata->author) ?> |
| Created at     | <?= Md::cell($metadata->createdAt) ?> |
| Last updated   | <?= Md::cell($metadata->updatedAt) ?> |
| Target release | <?= Md::cell($report->targetRelease) ?> |
| Linked tickets | <?= Md::cellList($report->linkedTickets) ?> |

---

## 2. Request Summary

### 2.1 Description
<?= Md::text($report->requestDescription), "\n" ?>

### 2.2 Current Behaviour
<?= Md::text($report->currentBehaviour), "\n" ?>

### 2.3 Requested Behaviour
<?= Md::text($report->requestedBehaviour), "\n" ?>

### 2.4 Technical Motivation
<?= Md::text($report->technicalMotivation), "\n" ?>

### 2.5 Acceptance Criteria
<?= Md::bullets($report->acceptanceCriteria), "\n" ?>

---

## 3. Scope

### 3.1 In Scope
<?= Md::bullets($report->inScope), "\n" ?>

### 3.2 Out of Scope
<?= Md::bullets($report->outOfScope), "\n" ?>

### 3.3 Assumptions
<?= Md::bullets($report->assumptions), "\n" ?>

### 3.4 Constraints
<?= Md::bullets($report->constraints), "\n" ?>

---

## 4. Technical Analysis

### 4.1 Current Architecture Context
<?= Md::text($report->currentArchitecture), "\n" ?>

### 4.2 Proposed Solution
<?= Md::text($report->proposedSolution), "\n" ?>

### 4.3 Alternatives Considered

| # | Approach | Pros | Cons | Effort | Selected |
|---|----------|------|------|--------|----------|
<?php foreach ($report->alternatives as $index => $alternative): ?>
| <?= $e($index + 1) ?> | <?= Md::cell($alternative->approach) ?> | <?= Md::cell($alternative->pros) ?> | <?= Md::cell($alternative->cons) ?> | <?= Md::cell($alternative->effort) ?> | <?= Md::cell($alternative->selected) ?> |
<?php endforeach; ?>
<?php if ($report->alternatives === []): ?>
| - | <?= $e(Md::NONE) ?> | | | | |
<?php endif; ?>

**Selection rationale:** <?= Md::inline($report->alternativeSelectionRationale), "\n" ?>

### 4.4 Affected Components

<?php /* Layer: frontend | backend | database | infra | integration | ci_cd. Change type: new | modify | deprecate | remove. Impact: high | medium | low. */ ?>
| Component | Layer | Change type | Impact | Owner |
|---|---|---|---|---|
<?php foreach ($report->affectedComponents as $component): ?>
| <?= Md::cell($component->component) ?> | <?= Md::cell($component->layer) ?> | <?= Md::cell($component->changeType) ?> | <?= Md::cell($component->impact) ?> | <?= Md::cell($component->owner) ?> |
<?php endforeach; ?>
<?php if ($report->affectedComponents === []): ?>
| <?= $e(Md::NONE) ?> | | | | |
<?php endif; ?>

### 4.5 Data Model Changes

| Entity / Table | Change | Migration required | Reversible | Data backfill |
|---|---|---|---|---|
<?php foreach ($report->dataModelChanges as $change): ?>
| <?= Md::cell($change->entity) ?> | <?= Md::cell($change->change) ?> | <?= Md::cell($change->migrationRequired) ?> | <?= Md::cell($change->reversible) ?> | <?= Md::cell($change->dataBackfill) ?> |
<?php endforeach; ?>
<?php if ($report->dataModelChanges === []): ?>
| <?= $e(Md::NONE) ?> | | | | |
<?php endif; ?>

**Migration notes:** <?= Md::inline($report->migrationNotes), "\n" ?>

### 4.6 API / Contract Changes

| Endpoint / Interface | Method | Change | Breaking | Versioning strategy |
|---|---|---|---|---|
<?php foreach ($report->apiChanges as $apiChange): ?>
| <?= Md::cell($apiChange->endpoint) ?> | <?= Md::cell($apiChange->method) ?> | <?= Md::cell($apiChange->change) ?> | <?= Md::cell($apiChange->breaking) ?> | <?= Md::cell($apiChange->versioningStrategy) ?> |
<?php endforeach; ?>
<?php if ($report->apiChanges === []): ?>
| <?= $e(Md::NONE) ?> | | | | |
<?php endif; ?>

### 4.7 Dependencies

<?php /* Type: library | internal_service | external_api | team | infrastructure. Status: available | needs_upgrade | needs_procurement | blocked. */ ?>
| Dependency | Type | Version | Status | Risk |
|---|---|---|---|---|
<?php foreach ($report->dependencies as $dependency): ?>
| <?= Md::cell($dependency->name) ?> | <?= Md::cell($dependency->type) ?> | <?= Md::cell($dependency->version) ?> | <?= Md::cell($dependency->status) ?> | <?= Md::cell($dependency->risk) ?> |
<?php endforeach; ?>
<?php if ($report->dependencies === []): ?>
| <?= $e(Md::NONE) ?> | | | | |
<?php endif; ?>

---

## 5. Non-Functional Impact

<?php /* Impact levels: none | low | medium | high. */ ?>
| Area                       | Impact | Details |
|----------------------------|--------|---------|
| Backward compatibility     | <?= Md::cell($report->backwardCompatibility->level) ?> | <?= Md::cell($report->backwardCompatibility->details) ?> |
| Performance                | <?= Md::cell($report->performance->level) ?> | <?= Md::cell($report->performance->details) ?> |
| Scalability                | <?= Md::cell($report->scalability->level) ?> | <?= Md::cell($report->scalability->details) ?> |
| Security                   | <?= Md::cell($report->security->level) ?> | <?= Md::cell($report->security->details) ?> |
| Privacy / Compliance       | <?= Md::cell($report->privacy->level) ?> | <?= Md::cell($report->privacy->details) ?> |
| Availability / Reliability | <?= Md::cell($report->availability->level) ?> | <?= Md::cell($report->availability->details) ?> |
| Observability              | <?= Md::cell($report->observability->level) ?> | <?= Md::cell($report->observability->details) ?> |
| Maintainability            | <?= Md::cell($report->maintainability->level) ?> | <?= Md::cell($report->maintainability->details) ?> |

### 5.1 Performance Budget
- Expected load: <?= Md::inline($report->expectedLoad), "\n" ?>
- Latency target (p95): <?= Md::inline($report->latencyTargetP95), "\n" ?>
- Resource overhead (CPU / memory / storage): <?= Md::inline($report->resourceOverhead), "\n" ?>

### 5.2 Security Considerations
- Authentication / authorisation changes: <?= Md::inline($report->authzChanges), "\n" ?>
- Data classification touched: <?= Md::inline($report->dataClassification), "\n" ?>
- Threats identified:<?= Md::nestedBullets($report->securityThreats), "\n" ?>

### 5.3 Configuration & Infrastructure Changes
<?= Md::bullets($report->infraChanges), "\n" ?>

---

## 6. Effort Estimation

<?php /* Confidence: high | medium | low. Complexity: trivial | low | medium | high | very_high. */ ?>
| Task | Role | Estimate (h) | Confidence |
|---|---|---|---|
<?php foreach ($report->effortBreakdown as $task): ?>
| <?= Md::cell($task->task) ?> | <?= Md::cell($task->role) ?> | <?= Md::cell($task->estimateHours) ?> | <?= Md::cell($task->confidence) ?> |
<?php endforeach; ?>
<?php if ($report->effortBreakdown === []): ?>
| <?= $e(Md::NONE) ?> | | | |
<?php endif; ?>

| Metric                   | Value |
|--------------------------|-------|
| Total estimate           | <?= Md::cell($report->effortTotalHours()) ?> h |
| Estimate range (min–max) | <?= Md::cell($report->effortMinHours) ?>–<?= Md::cell($report->effortMaxHours) ?> h |
| Story points             | <?= Md::cell($report->storyPoints) ?> |
| Complexity               | <?= Md::cell($report->complexity) ?> |
| Estimation method        | <?= Md::cell($report->estimationMethod) ?> |

---

## 7. Risk Assessment

<?php /* Probability / Impact: 1–5. Severity = Probability × Impact. PHP calculates the severity. */ ?>
| ID | Risk | Probability | Impact | Severity | Mitigation | Owner |
|----|------|-------------|--------|----------|------------|-------|
<?php foreach ($report->risks as $index => $risk): ?>
| R<?= $e($index + 1) ?> | <?= Md::cell($risk->risk) ?> | <?= Md::cell($risk->probability) ?> | <?= Md::cell($risk->impact) ?> | <?= Md::cell($risk->severity()) ?> | <?= Md::cell($risk->mitigation) ?> | <?= Md::cell($risk->owner) ?> |
<?php endforeach; ?>
<?php if ($report->risks === []): ?>
| - | <?= $e(Md::NONE) ?> | | | | | |
<?php endif; ?>

---

## 8. Technical Debt

- Debt introduced: <?= Md::inline($report->techDebtIntroduced), "\n" ?>
- Debt resolved: <?= Md::inline($report->techDebtResolved), "\n" ?>
- Follow-up tasks:<?= Md::nestedBullets($report->techDebtFollowups), "\n" ?>

---

## 9. Feasibility Verdict

<?php /* Rating: feasible | feasible_with_conditions | not_feasible. Overall verdict: the ratings and needs_more_info. Confidence: high | medium | low. */ ?>
| Dimension   | Rating | Justification |
|-------------|--------|---------------|
| Technical   | <?= Md::cell($report->technicalFeasibility->rating) ?> | <?= Md::cell($report->technicalFeasibility->justification) ?> |
| Operational | <?= Md::cell($report->operationalFeasibility->rating) ?> | <?= Md::cell($report->operationalFeasibility->justification) ?> |
| Schedule    | <?= Md::cell($report->scheduleFeasibility->rating) ?> | <?= Md::cell($report->scheduleFeasibility->justification) ?> |
| Resource    | <?= Md::cell($report->resourceFeasibility->rating) ?> | <?= Md::cell($report->resourceFeasibility->justification) ?> |

**Overall verdict:** <?= Md::inline($report->overallVerdict), "\n" ?>

**Confidence:** <?= Md::inline($report->verdictConfidence), "\n" ?>

**Conditions for approval:**
<?= Md::bullets($report->approvalConditions), "\n" ?>

**Recommendation:**
<?= Md::text($report->recommendation), "\n" ?>

---

## 10. Open Questions

| # | Question | Addressed to | Blocking | Status |
|---|---|---|---|---|
<?php foreach ($report->openQuestions as $index => $question): ?>
| <?= $e($index + 1) ?> | <?= Md::cell($question->question) ?> | <?= Md::cell($question->addressedTo) ?> | <?= Md::cell($question->blocking) ?> | <?= Md::cell($question->status) ?> |
<?php endforeach; ?>
<?php if ($report->openQuestions === []): ?>
| - | <?= $e(Md::NONE) ?> | | | |
<?php endif; ?>

---

## 11. References
<?= Md::bullets($report->references), "\n" ?>

---
<sub>Generated by <?= Md::inline($metadata->generatorName) ?> v<?= Md::inline($metadata->generatorVersion) ?> on <?= Md::inline($metadata->generatedAt) ?></sub>
