# 0006. Inject services through constructors and methods

- **Status**: Accepted
- **Date**: 2026-11-23
- **Supersedes**: None.

## Context

The user set a new rule: "Use dependency injection in the constructors or whenever you call a service."

Before this decision, the package created its services in many places:

- Constructors had defaults such as `= new FrontMatterParser()`. Examples: `DocumentRenderer`, `GraphBuilder`, `NameSearch`, and `FeasibilityAssessor`.
- `ReportMetadata::create()` read the system time and the Composer version in a static method.
- `FeasibilityReport::metadata()` created new metadata when no metadata was attached.
- `FeasibilityReport::toMarkdown()` called the global `view()` helper.
- The controllers and the service provider called global helpers, for example `config()`, `view()`, `report()`, and `base_path()`.

These hidden dependencies have problems:

- A test cannot replace a service without a change in the class.
- A test cannot fix the time of a report.
- A host application cannot replace a service through the container.
- The signature of a class does not show what the class needs.

Some constraints apply:

- Neuron AI deserializes `FeasibilityReport` from the structured output of the model. Neuron creates the object. The container does not create it. The report cannot get services through its constructor.
- `SafeMarkdownConverter` must always escape raw HTML and remove unsafe links. A host must not make the panel unsafe through a container binding.
- The feasibility endpoint must build the agent after the request validation. Then the controller can catch a configuration error and return 503.

## Decision

We will inject each service through the constructor, or through the method parameters when the constructor is not available, and we will create the objects only in the service provider and in the container.

- **The rule**: A class gets each service that it calls as a parameter. A constructor has no `= new X()` default. Package code does not call global helpers such as `config()`, `view()`, `app()`, or `report()`.
- **Service**: A service is an object that does work or has a dependency on the environment. Examples: a parser, a converter, a builder, a factory, a repository, and a view factory. A class gets a service through injection.
- **Not a service**: These objects are data. Code can create them with `new`.
  - A value object, for example `Node`, `Relationship`, or `SearchResult`.
  - A DTO, for example `ReportMetadata` and the nested classes of `FeasibilityReport`.
  - The product of a factory, for example the `FeasibilityAgent` that `FeasibilityAgentFactory::make()` returns, or the `ReportMetadata` that `ReportMetadataFactory::create()` returns.
- **Composition root**: `BaPanelServiceProvider` is the composition root. Only its binding closures and `boot()` get services from the container. It uses `$app` for the config `Repository`, `basePath()`, `configPath()`, and `resourcePath()`. It binds `GraphBuilder`, `ConfigValidator`, `FeasibilityAgentFactory`, `ProviderFactory`, and `FeasibilityAgent`.
- **Controllers**: `PanelController` and `FeasibilityController` get their services as action parameters. Examples: `Illuminate\Contracts\View\Factory` and `Illuminate\Contracts\Debug\ExceptionHandler`.
- **Method injection for the report**: `FeasibilityReport::toMarkdown(Illuminate\Contracts\View\Factory $views)` gets the view factory as a parameter. Neuron creates the report, so this is the only place for the service.
- **No lazy metadata**: `FeasibilityReport::metadata()` throws `LogicException` when no metadata is attached. `FeasibilityAssessor` attaches the metadata after each assessment.
- **Metadata factory**: `ReportMetadataFactory(PackageVersion $version)` replaces `ReportMetadata::create()`. `PackageVersion` reads the installed version from Composer.
- **No clock service**: The package has no clock class and no clock binding. `ReportMetadataFactory::create(?DateTimeImmutable $now = null)` gets the time as a parameter.
  - When the caller gives no time, the factory uses `new DateTimeImmutable()`. This is the current system time.
  - `DateTimeImmutable` is a value object, not a service. The rule permits `new` for it.
  - A test gives a fixed time to `create()`. The test needs no fake clock.
  - The user rule is: "Don't add extra dependencies". This decision adds no package to `composer.json`.
- **Test seam for the agent**: `FeasibilityAgentFactory(ProviderFactory, Repository)` builds the agent in `make()`. The class is not final. A test or a host can extend it and give another AI provider, for example the Neuron `FakeAIProvider`. `FeasibilityController` gets the factory and calls `make()` after the validation. The `FeasibilityAgent` container binding stays. It uses the factory.
- **Self-contained CommonMark adapters**: `FrontMatterParser` and `SafeMarkdownConverter` create their CommonMark objects in their constructors. They are adapters. The CommonMark objects are internal parts of the adapters. `SafeMarkdownConverter` owns the safe settings `html_input=escape` and `allow_unsafe_links=false`. Other classes get the adapters through injection.

## Alternatives

- **Keep the `= new X()` constructor defaults**: The team rejected this option. A default hides the dependency. The default does not follow the rule of the user.
- **Use the global helpers in package code**: The team rejected this option. A helper gets a service from the global container. The signature does not show the dependency. A unit test needs a full application.
- **Inject the view factory into the report constructor or through a setter**: The team rejected this option. Neuron creates the report with the deserializer, so the constructor does not get services. A setter adds state that the caller can forget.
- **Keep the lazy metadata in `FeasibilityReport::metadata()`**: The team rejected this option. The report must then create a `ReportMetadataFactory` or call the container. An exception shows a missing `attachMetadata()` call immediately.
- **Inject the CommonMark environment or the converter into the adapters**: The team rejected this option. A host could then bind an unsafe converter. The panel could then show raw HTML or `javascript:` links. The CommonMark objects need no config and no I/O, so a test needs no replacement.
- **Make `FeasibilityAgentFactory` final and add an interface**: The team rejected this option for now. One class without an interface is sufficient for the tests. A later ADR can add an interface.
- **Inject a PSR-20 clock (`Psr\Clock\ClockInterface`)**: The team rejected this option. It adds `psr/clock` as a direct dependency. The user does not permit extra dependencies. It also adds a clock class and a container binding. One parameter on `create()` is sufficient for the tests.
- **Use the Laravel `DateFactory` (the `Date` facade)**: The team rejected this option. It is a framework service, not a value. It returns a Carbon object, not a `DateTimeImmutable`. A test must then set a global test time, for example with `Carbon::setTestNow()`. Global state can leak between tests.
- **Read the time only inside the factory, with no parameter**: The team rejected this option. A test cannot then fix the time of a report.

## Consequences

### Positive

- The signature of each class shows its services.
- A test can give a fake service without a change in the class. Examples: a fixed time for `ReportMetadataFactory::create()` and a factory that returns an agent with `FakeAIProvider`.
- The package has no extra dependency and no clock class.
- The report Markdown and the report HTML always use the safe converter settings.
- The service provider is the one place that creates the services.

### Negative

- Code that creates `DocumentRenderer`, `GraphBuilder`, or `NameSearch` with `new` must now give all services. This is a breaking change for v0.2.5 users who create these classes directly.
- The order of the `NameSearch` parameters changes. `$threshold` is now the last parameter.
- Each caller of `FeasibilityReport::toMarkdown()` must give a view factory.
- Code that reads `metadata()` before `attachMetadata()` now gets a `LogicException`.
- A host application cannot replace the time source through a container binding. The `FeasibilityAssessor` always uses the current system time.
- `FeasibilityAgentFactory` is not final. A host can extend it in unsupported ways.
- `FrontMatterParser` and `SafeMarkdownConverter` are an exception to the rule. Maintainers must know the reason for the exception.

## References

- Ticket BAP-007.
- [ADR 0005](0005-integrate-feasibility-agent-through-json-endpoint-with-structured-report.md)
- [PSR-20: Clock](https://www.php-fig.org/psr/psr-20/) (rejected alternative)
- `src/BaPanelServiceProvider.php`
- `src/Ai/FeasibilityAgentFactory.php`
- `src/Ai/FeasibilityAssessor.php`
- `src/Reports/FeasibilityReport.php`
- `src/Reports/ReportMetadataFactory.php`
- `src/Reports/PackageVersion.php`
- `src/Graph/DocumentRenderer.php`
- `src/Graph/GraphBuilder.php`
- `src/Graph/FrontMatterParser.php`
- `src/Markdown/SafeMarkdownConverter.php`
- `src/Search/NameSearch.php`
- `src/Http/Controllers/FeasibilityController.php`
- `src/Http/Controllers/PanelController.php`
