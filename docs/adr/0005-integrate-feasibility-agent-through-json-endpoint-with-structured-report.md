# 0005. Integrate the feasibility agent through a JSON endpoint with a structured-output report

- **Status**: Accepted
- **Date**: 2026-11-23
- **Supersedes**: None.

## Context

The panel page has an agent panel. The business analyst types a change request or a feature request there.
The agent must assess the request against the documentation files of the context.
The result must be a human-readable Markdown report. The business analyst must be able to download it.

The panel is a Blade page with vanilla JavaScript. The package has no front-end build step.
The agent does not call tools in the browser. The agent gives one report for one request.

The report contains system fields: the report id, the version, the status, the author, the dates, and the generator.
The model must not invent these fields.
The report also contains calculated values: the risk severity and the total effort.
The model can make arithmetic errors.

The context must contain only files from the documentation graph (ADR 0003).
The request must not give a file path to the server.

ADR 0002 selected neuron-ai and added `BaPanelAgent`. This task replaces `BaPanelAgent` with `FeasibilityAgent`. The choice of neuron-ai does not change.

## Decision

We will integrate the feasibility agent through one JSON request and one JSON response, and get the report from the model as structured output that a Blade view changes into Markdown.

- **Transport**: `POST /ba-panel/feasibility`, route name `ba-panel.feasibility`, `web` middleware. The request needs the CSRF token.
  - The request is `{"message": string, "context": [node id]}`.
  - The message has at most 5000 characters. The context has at most 50 node ids.
  - The response 200 is `{report, markdown, html}`.
  - An invalid request gets 422. A missing AI configuration gets 503. An agent or provider failure gets 502.
  - The 502 and 503 responses do not contain the exception details. The package logs the exception.
  - There is no streaming. There is no UI-protocol adapter.
- **Agent**: `FeasibilityAgent` extends `NeuronAI\Agent\Agent`. The container builds it from `config('ba-panel.ai')` through `ProviderFactory`. The context window is 150 000 tokens.
- **Conversation**: `FeasibilityAssessor` sets a new random thread id for each request. The agent keeps no chat history.
- **Prompt**: The user message has one block for each context file, without the front matter. The request is the last block.
- **Structured output**: `FeasibilityAssessor` calls `structured()` with `FeasibilityReport`.
  - `FeasibilityReport` follows the fields of the stub. It uses nested classes and 13 backed enums in `src/Reports/Enums`.
  - The system fields are in a private `ReportMetadata` object. PHP fills them. The JSON schema does not contain them.
  - PHP calculates the risk severity (probability multiplied by impact) and the total effort hours (the sum of the task estimates).
- **Markdown**: `FeasibilityReport::toMarkdown()` renders the Blade view `ba-panel::reports.feasibility`. The `ba-panel-views` tag publishes the view, so a host application can override it.
- **HTML**: The server converts the Markdown to HTML. The converter escapes raw HTML and removes unsafe links.
- **Context**: When the business analyst opens a document, the context is that document and its direct neighbours. The business analyst can remove each file. A new document replaces the context.
- **File access**: The server finds each node id only in the documentation graph. It reads no path from the request.

## Alternatives

- **Server-sent events with the neuron-ai native stream adapter**: The team rejected this option for now.
  - The report is one structured object. A partial object is not useful to the business analyst.
  - The vanilla JavaScript panel must then parse an event stream.
  - A later ADR can add streaming.
- **Vercel AI SDK, AG-UI, or CopilotKit**: The team rejected this option.
  - These clients need a JavaScript build step. The package has no build step.
  - The agent has no frontend tools and no approval flow. The protocols give no benefit here.
- **Free-text Markdown from the model**: The team rejected this option.
  - The model can skip sections or change the structure of the report.
  - The model can invent the report id, the dates, and the status.
  - The model can calculate the severity and the total effort incorrectly.
  - The package cannot validate free text.

## Consequences

### Positive

- The panel needs no new JavaScript library and no build step.
- The report always has the same sections. A host application can change the layout with a published view.
- The system fields and the calculated values are correct. The model does not write them.
- The JSON schema is smaller, because it does not contain the system fields.
- The response gives the report as data, as Markdown, and as safe HTML. The panel downloads the Markdown as `feasibility-<report_id>.md`.
- A request cannot read a file outside the documentation graph.

### Negative

- The business analyst sees no output until the report is complete. A long assessment can take a long time.
- The agent keeps no history. A follow-up request must repeat the details of the earlier request.
- A change in the stub needs a change in `FeasibilityReport`, in the enums, and in the Blade view.
- A large schema and many context files increase the token cost of each request.
- A host application that overrides the view must update it when the report fields change.
- There is no PDF export.

## References

- Ticket BAP-007.
- [ADR 0002](0002-adopt-neuron-ai-as-agent-development-kit.md)
- [ADR 0003](0003-build-documentation-graph-from-front-matter-relations.md)
- `src/Ai/FeasibilityAgent.php`
- `src/Ai/FeasibilityAssessor.php`
- `src/Reports/FeasibilityReport.php`
- `src/Reports/ReportMetadata.php`
- `src/Reports/Enums/`
- `src/Http/Controllers/FeasibilityController.php`
- `src/Markdown/SafeMarkdownConverter.php`
- `resources/views/reports/feasibility.blade.php`
- `resources/views/panel.blade.php`
- `routes/web.php`
