# 0001. Validate the environment contract at boot when the panel is enabled

- **Status**: Accepted
- **Date**: 2026-11-22
- **Supersedes**: None.

## Context

The package reads its configuration from environment variables in the client application.
The contract has five variables: `BAP_ENABLED`, `BAP_AI_PROVIDER`, `BAP_AI_MODEL`, `BAP_AI_KEY`, and `BAP_DOCS_ARRAY`.
The panel cannot operate with a missing AI setting or a missing documentation path.

The client installs the package before it sets the variables.
Composer runs Laravel package discovery during installation.
Package discovery boots the service provider.
A check at this point must not fail on an empty configuration.

A configuration error must be visible early, not in a production request.
A client can have more than one error at a time.
The client must see all errors together, not one error per attempt.

The set of rules is small and fixed.
The rules are "required string" and "path exists".

The package config file lives in the root `config/` directory.
This follows the Laravel package convention.
The domain-engineer owns this file.

## Decision

We will validate the environment contract fail-fast at boot, only when `BAP_ENABLED` is true, and collect all errors in one `InvalidConfigurationException`.

- `BaPanelServiceProvider::boot()` calls `ConfigValidator::validate()` only when `ba-panel.enabled` is `true`.
- `ConfigValidator` checks all rules before it throws.
- `InvalidConfigurationException::errors()` returns the list of all error messages.

## Alternatives

- **Always validate**: The team rejected this option. It breaks the installation and the package discovery before the client sets the variables.
- **Lazy validation at first use**: The team rejected this option. The errors surface late, in production requests.
- **Laravel `Validator`**: The team rejected this option. It adds coupling to the validation component for a small, fixed set of rules.

## Consequences

### Positive

- The installation and the package discovery work before the client sets the variables.
- A configuration error stops the application at boot, before it serves requests.
- The client sees all errors in one exception.
- The validator has no dependency on the Laravel validation component.

### Negative

- The validation runs on each boot when the panel is enabled. This includes the check for each documentation path on the file system.
- A client with `BAP_ENABLED=false` gets no warning about an incomplete configuration.
- New rules need custom code in `ConfigValidator`.

## References

- Ticket BAP-003.
- `src/BaPanelServiceProvider.php`
- `src/Config/ConfigValidator.php`
- `src/Exceptions/InvalidConfigurationException.php`
- `config/ba-panel.php`
