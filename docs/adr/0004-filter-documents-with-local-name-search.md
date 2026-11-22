# 0004. Filter documents with a local name search

- **Status**: Accepted
- **Date**: 2026-11-22
- **Supersedes**: None.

## Context

The panel has a filter field. The business analyst types a query to find a document by its name.
The ticket requires a search over the file name, with a threshold of 80%.
The filter sends a request while the business analyst types. One query can cause many requests.
The client selects the AI provider with `BAP_AI_PROVIDER` (ADR 0002). The filter must work with each supported provider.
Anthropic has no embedding API.
The filter must not change the graph.
The test suite must be able to check the results without a network.

## Decision

We will match the query against the file names with a local character-trigram search, and not with embeddings from an AI provider.

- `Tokenizer` changes the text to lowercase. It splits on characters that are not letters or digits, and on camelCase boundaries.
- `Vectorizer` changes each token into a character-trigram frequency vector. It adds one space on each side of the token. It compares two vectors with the cosine similarity.
- `NameSearch` gives a token similarity of 1.0 when a name token starts with the query token. This supports type-ahead. Otherwise it uses the cosine similarity.
- The score of a node is the average, over the query tokens, of the best similarity against the name tokens.
- `NameSearch::THRESHOLD` is `0.8`. The search keeps a node with a score of 0.8 or more.
- The results are sorted by score, highest first, then by path.
- The filter shows the results in a list. It does not hide, move, or change a node in the graph.

## Alternatives

- **Embeddings from the configured AI provider**: The team rejected this option.
  - Each keystroke causes a network call and a cost.
  - Anthropic has no embedding API. The filter then does not work with `BAP_AI_PROVIDER=anthropic`.
  - The results depend on the model version. The tests cannot check exact scores.
- **Exact substring match**: The team rejected this option. It gives no score, so the 80% threshold has no meaning. It does not find a name with small spelling differences.
- **Hide the nodes that do not match**: The team rejected this option. The ticket requires that the filter does not change the graph.

## Consequences

### Positive

- The search makes no network call. It has no cost per keystroke.
- The search works with each supported provider.
- The same query and the same files always give the same results.
- The tests can check exact scores.
- A prefix of a name token always matches, for example `end` finds `endpoints`.
- The graph stays the same while the business analyst filters.

### Negative

- The search compares names only. It does not read the title or the body of the document.
- The search does not find words with the same meaning. For example, `routes` does not find `endpoints`.
- The typo tolerance is limited. For example, `endpionts` against `endpoints` scores approximately 0.56. This is below the threshold, so there is no match.
- A later change to embeddings can replace `Vectorizer`. That change needs a new ADR.

## References

- Ticket BAP-005.
- [ADR 0002](0002-adopt-neuron-ai-as-agent-development-kit.md)
- [ADR 0003](0003-build-documentation-graph-from-front-matter-relations.md)
- `src/Search/Tokenizer.php`
- `src/Search/Vectorizer.php`
- `src/Search/NameSearch.php`
- `src/Search/SearchResult.php`
- `src/Http/Controllers/PanelController.php`
