# 0009. Load the feasibility agent persona from an editable prompt file

- **Status**: Accepted
- **Date**: 2026-11-26
- **Supersedes**: None.

## Context

The system prompt of the feasibility agent was one constant in `FeasibilityAgent`. A host could not change it.

Hosts want to change the tone and the role of the agent. Examples: a different audience, a different language style, or a domain-specific role.

Some constraints apply:

- The structured output of `FeasibilityAssessor` depends on some prompt rules. These rules are the output contract:
  - Use only the documentation files of the request.
  - Write "Not specified in the documentation" for missing information. Add an open question.
  - Give a verdict for each dimension and an overall verdict. Use `needs_more_info` when the files do not give enough information.
  - List only the used file paths in the references.
- A host must not be able to break the output contract.
- Neuron AI has no built-in loader for a prompt file. `Agent::instructions()` returns a `SystemMessage`.
- A `SystemMessage` can hold more than one content block. `SystemContent` is deprecated. Neuron AI recommends `TextContent`.
- `BAP_DOCS_ARRAY` resolves a relative path against the application base path. See the README.
- The Laravel shell can publish package files with `vendor:publish`. The Symfony shell has no publish command.
- The package classes get their services through dependency injection. See ADR 0006.

## Decision

We will load the persona of the feasibility agent from an editable Markdown file and keep the output contract in code, appended after the persona.

- **Persona file**: The package prompt file is `resources/prompts/feasibility.md`. It contains the role, the assessment rules, and the style.
- **Contract in code**: The `CONTRACT` constant of `FeasibilityAgent` contains the output contract. The host cannot change it.
- **Message shape**: `FeasibilityAgent::instructions()` returns one `SystemMessage` with two `TextContent` blocks. The persona block comes first. The contract block comes last. Thus the contract rules apply when the persona conflicts with them.
- **Loader**: `Core\Ai\PromptLoader` selects the file, reads it, and trims the text. `FeasibilityAgentFactory` gets the loader through its constructor.
- **Configuration**: The key is `ai.instructions_path`. The environment variable is `BAP_AI_INSTRUCTIONS_PATH`. The default is `null`.
- **Precedence**:
  1. The configured path.
  2. Laravel only: the published copy `resources/prompts/vendor/ba-panel/feasibility.md`, when the file exists. The publish tag is `ba-panel-prompts`.
  3. The package prompt file.
- **Path resolution**: A relative path resolves against the application base path. Laravel gives `basePath()`. Symfony gives `%kernel.project_dir%`. An absolute path is used as given.
- **No fallback for a configured path**: When the configured file is missing, not readable, or empty, `PromptLoader` throws `InvalidConfigurationException`. It does not try the published copy or the package file.

## Alternatives

- **Keep the full prompt in code**: The host cannot change the tone or the role. Rejected.
- **Put the full prompt, with the contract, in the file**: A host edit can remove a contract rule. The structured report then breaks. Rejected.
- **Put the contract before the persona**: A later persona rule can conflict with the contract. The model then can follow the persona. Rejected.
- **Use `SystemContent` blocks**: `SystemContent` is deprecated in Neuron AI. Rejected.
- **Resolve a relative path against the current working directory**: The working directory changes between artisan commands, queue workers, and web requests. The same value then gives different files. Rejected. The application base path is stable and matches the rule of `BAP_DOCS_ARRAY`.
- **An automatic override location in Symfony**: Symfony has no publish command, so the package cannot fix the location of a copy. The location saves only one configuration line. It also adds a hidden source of prompt changes. Rejected. The package can add it later without a breaking change.
- **Fall back to the published copy or the package file when the configured file is missing**: The agent then runs with a prompt that the host did not select. The misconfiguration is not visible. Rejected.

## Consequences

### Positive

- A host can change the role, the assessment rules, and the style without a code change.
- The output contract stays the same for all hosts. The structured report keeps working.
- A Laravel host needs no configuration value after `vendor:publish --tag=ba-panel-prompts`.
- A relative path gives the same file in all entry points of the application.
- A wrong configured path gives a clear error with the resolved path.

### Negative

- The prompt has two sources: the file and the code. A reader must read both to see the full prompt.
- A Symfony host must copy the file manually and set one configuration value.
- The boot validation does not check the prompt file. A wrong path is visible only when the package builds the agent. The endpoint then returns `503`.
- A published copy does not get the changes of a later package version. The host must update the copy.
- The package reads the prompt file each time it builds an agent.

## References

- `src/Core/Ai/PromptLoader.php`
- `src/Core/Ai/FeasibilityAgent.php`
- `resources/prompts/feasibility.md`
- ADR 0001. Validate the environment contract at boot when the panel is enabled.
- ADR 0006. Inject services through constructors and methods.
- [Customize the agent prompt](../../README.md#customize-the-agent-prompt)
