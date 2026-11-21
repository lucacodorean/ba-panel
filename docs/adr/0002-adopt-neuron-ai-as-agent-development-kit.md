# 0002. Adopt neuron-ai as the agent development kit

- **Status**: Accepted
- **Date**: 2026-11-22
- **Supersedes**: None.

## Context

The panel needs an AI agent. The agent assesses business analyst requests against the project documentation.
The package needs a PHP agent development kit (ADK) to connect to the AI provider.

The client selects the provider with `BAP_AI_PROVIDER`.
This lets the client application choose the vendor. The package must not lock the client to one vendor.
The ADK must run in PHP, in the same process as the Laravel package.
The client sets the model with `BAP_AI_MODEL` and the API key with `BAP_AI_KEY`.
The contract from ADR 0001 requires all three values when `BAP_ENABLED` is true.
The contract requires an API key. Some providers do not use an API key, for example Ollama, Bedrock, Vertex, and Azure.

The provider classes in the ADK have different constructor argument orders.

Package discovery boots the service provider before the client sets the variables.
The package must not build a provider at this point.

The service provider loads the panel route from `routes/web.php` only when `BAP_ENABLED` is true.
The configuration validation runs in the same condition.
A client request can reach the panel only after a successful validation.

## Decision

We will use `neuron-core/neuron-ai` `^4.1` as the ADK, and map `BAP_AI_PROVIDER` to a neuron provider through one `ProviderFactory`.

- `ProviderFactory::SUPPORTED_PROVIDERS` is the single source of truth for the supported values: `anthropic`, `openai`, `gemini`, `mistral`, `deepseek`, and `xai`.
- The provider name ignores case and outer whitespace.
- `ProviderFactory` builds each provider with named arguments. This removes the dependency on the constructor argument order.
- `BaPanelAgent` extends `NeuronAI\Agent\Agent`. It receives the provider through its constructor.
- The container binds `BaPanelAgent` lazily. It builds a new agent on each resolve from `config('ba-panel.ai')`.
- `ConfigValidator` rejects an unsupported provider at boot when `BAP_ENABLED` is true. It uses `ProviderFactory::supports()`.
- Providers without an API key are out of scope for now.

## Alternatives

- **Claude Agent SDK** (`claude-agent-sdk` for Python, `@anthropic-ai/claude-agent-sdk` for TypeScript): The team rejected this option.
  - It is Claude Code as a library. It has an agent loop, context management, built-in tools, MCP, subagents, and hooks.
  - It is available only for Python and TypeScript. There is no PHP Agent SDK.
  - The package needs a separate Node.js or Python process (a sidecar) and an IPC or HTTP bridge to use it.
  - It calls Claude models only. This locks the package to one vendor.
  - Its built-in file system and shell tools (Read, Write, Edit, Bash, Glob, Grep, WebSearch, WebFetch) are a larger attack surface than the panel needs. The panel only reads documentation and chats.
- **Official Anthropic PHP SDK** (`anthropic-ai/sdk` on Composer): The team rejected this option.
  - It is native PHP.
  - It calls Claude models only. It can call them through Amazon Bedrock, Google Vertex AI, and Microsoft Foundry, but the models are always Claude models.
  - It does not support OpenAI, Gemini, Mistral, DeepSeek, or xAI models. This locks the package to one vendor.
  - It has a beta tool runner (`$client->beta->messages->toolRunner()`) and structured outputs.
  - It has no agent framework: no provider abstraction, no chat history or memory layer, and no RAG.
- **Positional constructor arguments**: The team rejected this option. The argument order is different between providers. A wrong order passes the key as the model, or the model as the key.
- **A separate list of supported providers in `ConfigValidator`**: The team rejected this option. Two lists can become different. The validator then accepts a provider that the factory cannot build.
- **Build the agent at register time**: The team rejected this option. Package discovery fails before the client sets the variables.
- **Support providers without an API key now**: The team rejected this option for now. These providers need a different configuration contract.

## Consequences

### Positive

- neuron-ai is native PHP. It runs in the same process as the package. No sidecar process is necessary.
- neuron-ai gives one `AIProviderInterface` for many vendors. This matches the `BAP_AI_PROVIDER` contract from BAP-003.
- A client that wants Claude can use it through the neuron-ai Anthropic provider, with `BAP_AI_PROVIDER=anthropic`.
- One list controls the supported providers for the factory, the validator, and the error message.
- An unsupported provider stops the application at boot, before it serves requests.
- The error message lists all supported values.
- Package discovery and a disabled panel build no provider and no agent.
- A new provider needs one constant, one entry in `SUPPORTED_PROVIDERS`, and one `match` arm.
- The panel route does not exist when `BAP_ENABLED` is false.

### Negative

- The package depends on the neuron-ai API. A major neuron-ai release can need changes in `ProviderFactory` and `BaPanelAgent`.
- Clients cannot use Ollama, Bedrock, Vertex, or Azure.
- The container builds a new provider and a new agent on each resolve. The agent does not keep state between resolves.
- `ProviderFactory::fromConfig()` checks the AI values again at build time. This duplicates part of the boot validation.
- If the package decides to support only Claude, the team must evaluate the official Anthropic PHP SDK again.

## References

- Ticket BAP-004.
- [ADR 0001](0001-validate-env-contract-at-boot.md)
- `composer.json`
- `src/Ai/ProviderFactory.php`
- `src/Ai/BaPanelAgent.php`
- `src/BaPanelServiceProvider.php`
- `src/Config/ConfigValidator.php`
- `routes/web.php`
