# 0007. Declare immutable classes readonly and leaf classes final

- **Status**: Accepted
- **Date**: 2026-11-23
- **Supersedes**: None.

## Context

The user set two new rules for the classes in `src/`:

- A class whose state never changes is `readonly`.
- A class that nothing extends is `final`.

Before this decision, the classes did not follow one rule:

- Most classes were `final`, but only some value objects were `readonly`. Examples: `Node`, `Relationship`, and `SearchResult`.
- Services such as `GraphBuilder`, `NameSearch`, and `ConfigValidator` used `private readonly` on each property, but the class was not `readonly`.
- `Graph` was not `readonly`. Its constructor filled the `$nodes` property one item at a time.
- The nested report DTOs declared their public properties in the class body. They had no constructor.

Some constraints apply:

- PHP does not permit a `readonly` class to extend a class that is not `readonly`. PHP also does not permit a class that is not `readonly` to extend a `readonly` class.
- Neuron AI creates `FeasibilityReport` and its nested DTOs with its `Deserializer`. Neuron builds the JSON schema from the public properties. A change in the class shape must not change the schema or the deserialization.
- The tests replace the AI provider of the agent through `FeasibilityAgentFactory`. The test class `Tests\Support\FakeFeasibilityAgentFactory` extends it. See ADR 0006.
- `FeasibilityReport::attachMetadata()` sets the metadata after the deserialization.

## Decision

We will declare each class in `src/` `final` when nothing extends it, and `readonly` when its state never changes, and we will enforce both rules with a Pest architecture test.

- **The rules**: Each class in `src/` is `final`, with one exception. Each class in `src/` is `readonly`, with the exceptions below.
- **Not final**: `Ai\FeasibilityAgentFactory`. It is the test seam. `FakeFeasibilityAgentFactory` extends it.
- **The test seam is `readonly`**: `FeasibilityAgentFactory` is a `readonly class`, but it is not `final`. Its state never changes. PHP requires each subclass of a `readonly` class to be `readonly`. Thus a host or a test subclass must be declared `readonly`. The test class `FakeFeasibilityAgentFactory` is `final readonly`.
- **Not readonly**: Each exception has a reason.
  - `Ai\FeasibilityAgent`: its parent, the Neuron `Agent`, is not `readonly`.
  - `BaPanelServiceProvider`: its parent, the Laravel `ServiceProvider`, is not `readonly`.
  - `Exceptions\InvalidConfigurationException` and `Exceptions\InvalidFeasibilityRequestException`: their parents, `RuntimeException` and `InvalidArgumentException`, are not `readonly`.
  - `Reports\FeasibilityReport`: `attachMetadata()` changes its state.
- **All other classes are `final readonly`**: Examples: `Graph`, `GraphBuilder`, `NameSearch`, `Tokenizer`, `Vectorizer`, `DocumentRenderer`, `FrontMatterParser`, `SafeMarkdownConverter`, `ConfigValidator`, `ProviderFactory`, `FeasibilityAssessor`, `ReportMetadata`, `ReportMetadataFactory`, `PackageVersion`, `MarkdownText`, `PanelController`, and `FeasibilityController`.
- **`Graph`**: The constructor builds the keyed node list in a local variable. Then it sets the `$nodes` property one time.
- **Nested report DTOs**: The 10 nested classes of `FeasibilityReport` are `final readonly`. They use promoted constructor properties. The classes are `AffectedComponent`, `Alternative`, `ApiChange`, `DataModelChange`, `Dependency`, `EffortTask`, `FeasibilityDimension`, `NonFunctionalImpact`, `OpenQuestion`, and `Risk`.
- **Deserializer check**: The Neuron `Deserializer` does not call the constructor. It sets the properties through reflection. A `readonly` property that is not initialized can get its value one time through reflection. The team verified that Neuron fills the promoted properties. The team also verified that the JSON schema of `FeasibilityReport` is byte-identical to the schema before the change.
- **Enforcement**: The Pest architecture test `tests/Unit/ArchitectureTest.php` checks the package namespace. It does not check the `Tests` namespace.
  - It checks the `final` and `readonly` rules and their exceptions.
  - It checks that `FeasibilityAgentFactory` is `readonly`.
  - It checks that the package does not use `Psr\Clock`.
  - It checks that the package does not call the global helpers `app`, `config`, `view`, `report`, `resolve`, `base_path`, and `logger`. The helper rule comes from ADR 0006.
  - `tests/Unit/Reports/ReportDtoReadonlyTest.php` checks that a nested report DTO throws an `Error` when code changes a property after the deserialization.

## Alternatives

- **Keep `private readonly` on each property and no `readonly` class**: The team rejected this option. It does not follow the rule of the user. A new property without `readonly` can then add mutable state, and no check finds it.
- **Make `FeasibilityAgentFactory` final and add an interface for the test seam**: The team rejected this option for now. ADR 0006 keeps one class without an interface. A later ADR can add the interface. Then the factory can be `final readonly`.
- **Keep the DTO properties in the class body with no constructor**: The team rejected this option. Only the class itself can initialize a `readonly` property. Code that creates a DTO with `new` cannot then set the values. Promoted constructor properties let the tests and the code create a DTO with all values in one call.
- **Make `FeasibilityReport` readonly and give the metadata in a new copy of the report**: The team rejected this option. Neuron creates the report, so the report has no constructor for the metadata. A copy of all properties adds code and can lose a field.
- **Check the rules only in the code review**: The team rejected this option. A reviewer can miss a missing keyword. The architecture test fails at once.

## Consequences

### Positive

- The class declaration shows if the state can change.
- PHP stops each write to a property after the constructor in a `readonly` class.
- Nothing outside the package can extend a `final` class. The package can change the internal code without a break for the hosts.
- Code can create each nested report DTO with its constructor, for example in a test.
- The architecture test finds a missing `final` or `readonly` keyword in a new class.

### Negative

- A host cannot extend the package classes. Each class that existed in v0.2.5 was already `final`, so this is not a new break for these classes.
- Code that writes a property of a `readonly` class after the constructor now gets an `Error`. The package classes in v0.2.5 had no public mutable properties.
- `FeasibilityAgentFactory` is not `final`. A host can extend it in unsupported ways.
- A host subclass of `FeasibilityAgentFactory` must be a `readonly class`. A subclass that is not `readonly` causes a PHP fatal error.
- The DTO behaviour depends on the reflection behaviour of the Neuron `Deserializer`. A later Neuron version can change it. The schema and deserialization tests must find this change.
- The architecture test must list each exception. A new exception needs a change in the test and a reason in an ADR.

## References

- Ticket BAP-007.
- [ADR 0006](0006-inject-services-through-constructors-and-methods.md)
- [PHP: readonly classes](https://www.php.net/manual/en/language.oop5.basic.php#language.oop5.basic.class.readonly)
- `src/Ai/FeasibilityAgentFactory.php`
- `src/Ai/FeasibilityAgent.php`
- `src/BaPanelServiceProvider.php`
- `src/Exceptions/InvalidConfigurationException.php`
- `src/Exceptions/InvalidFeasibilityRequestException.php`
- `src/Graph/Graph.php`
- `src/Reports/FeasibilityReport.php`
- `src/Reports/Risk.php`
- `tests/Support/FakeFeasibilityAgentFactory.php`
- `tests/Unit/ArchitectureTest.php`
- `tests/Unit/Reports/ReportDtoReadonlyTest.php`
