# 0003. Build the documentation graph from front matter relations

- **Status**: Accepted
- **Date**: 2026-11-22
- **Supersedes**: None.

## Context

The panel shows the documentation as a graph. Each node is one Markdown document. Each edge connects two documents.
A relation has a direction. "A declares B" means that A leads to B. The user asked for arrows on the edges.
Two documents can declare each other. The graph must show this pair as one edge, not as two edges.
A large graph is difficult to read. The user asked to see only the selected document and the documents with a direct relation to it.
The documents come from the paths in `BAP_DOCS_ARRAY` (`config('ba-panel.docs')`).
The package needs a source for the edges. The source must be in the documents. The business analyst must not keep a separate file.
The source must not change the rendered text of the document.
A document can move to a different directory. The link format must stay readable for the author.
An error in one document must not stop the panel.
The browser must not get a file system path that it can send back to read a file.

## Decision

We will read the edges from the `relations` list in the YAML front matter of each document, and resolve each path relative to the directory of the declaring document. The relations are directed. The graph keeps one edge for each pair of documents. The edge is bidirectional when the two documents declare each other.

- The front matter can also contain `title`. The fallback is the first `# ` heading, then the file name without extension.
- `FrontMatterParser` uses the `league/commonmark` front matter parser with `symfony/yaml`. Invalid YAML gives empty metadata. It does not throw an exception.
- `GraphBuilder` scans each configured directory recursively for `*.md` files (case-insensitive). It skips hidden files and hidden directories inside a scanned directory.
- `GraphBuilder` skips a relation without an exception when the target does not exist, is not a scanned file, or is the same document. It adds a duplicate relation once.
- If A declares B, B declares A, or both, the graph has exactly one `Relationship` between A and B. The key of the pair is the two node ids, sorted.
- A `Relationship` is a directed link. The source declares the target.
- The stored source and target come from the first declaration. `GraphBuilder` reads the files in sorted path order and the relations in declaration order. For a one-way link, this is the real direction.
- `Relationship::bidirectional()` is true when the target also declares the source. `Relationship::toArray()` returns `{source, target, bidirectional}`.
- `Relationship::leadsFrom()` is true when the link can be followed from a node. This is the source, or the target of a bidirectional link.
- `Graph::relationsOf()` returns the nodes that the links lead to from a node. A one-way link leads only from its source. A bidirectional link leads from both ends. Each node is listed once, sorted by path. The reader and the `relations` field of the documents endpoint use this list.
- `Graph::neighbourhoodOf()` returns the node and its direct neighbours. A neighbour has a `Relationship` with the node in either direction: outgoing, incoming, or bidirectional. The method does not follow links further. Each node is listed once, sorted by path. The `subgraph` field of the documents endpoint contains the ids of this list.
- `Node::id` is the first 12 hexadecimal characters of `sha1(realpath)`. The HTTP routes accept only this id. `Node::toArray()` does not contain the absolute path.
- The panel draws an arrow at the target end of a one-way edge. It draws arrows at the two ends of a bidirectional edge.
- When the user opens a document, the panel shows only the subgraph of that document. The subgraph contains the document and its direct neighbours. The panel shows an edge only when the two ends are visible. The "Show all files" button and the reader close button show the full graph again. The filter alone does not change the graph.
- The links count in the panel is the number of `Relationship` objects. A bidirectional pair counts one time. The counts in the header are always the counts of the full graph.

## Alternatives

- **Undirected relations, one edge for each pair**: The team rejected this option. The direction of a relation carries meaning, for example "depends on" or "follows". The user asked for arrows that show the direction.
- **Directed relations, one edge for each declaration**: The team rejected this option. A pair that both documents declare then gives two edges for one link. The graph shows two lines between the same two nodes.
- **Inline Markdown links in the body**: The team rejected this option. A body link is part of the text for the reader. Not every link is a relation that the author wants to show in the graph.
- **A separate index file (for example `relations.yaml`)**: The team rejected this option. The author must update two files. The index can become different from the documents.
- **Paths relative to the application base path**: The team rejected this option. A relation then breaks when the package user changes the base path or moves a directory. A path relative to the declaring document is the same rule as a Markdown link.
- **Throw an exception for a broken relation**: The team rejected this option. One broken document then stops the full panel.
- **Use the file path as the node id in URLs**: The team rejected this option. A path from the request can read files outside the documentation.
- **Follow the arrows to all reachable nodes in the subgraph view**: The team rejected this option. The user wants to hide the nodes without a direct relation to the selected document. A multi-hop search can show most of a connected graph.

## Consequences

### Positive

- The author keeps the relations in the same file as the text.
- The rendered document does not show the front matter.
- A relative path works when the author moves a group of related documents together.
- Invalid YAML, a missing target, and a self link do not stop the panel.
- The graph keeps the direction of a relation. An arrow shows the direction to the reader.
- A pair that both documents declare gives one edge with two arrows. The links count does not count it two times.
- The subgraph view shows only the selected document and its direct neighbours. A large graph is easier to read.
- The subgraph view shows the documents that declare the selected document through a one-way link. The user does not need the full graph to see them.
- The controller reads a file only through a node from the graph. A request value never becomes a path.

### Negative

- A two-way link needs a declaration in each of the two files.
- The reader of a document does not show the documents that declare it through a one-way link. The full graph shows these links.
- The subgraph view does not show the documents two or more links away. The user must open a neighbour or click "Show all files" to see them.
- The reader shows fewer files than the subgraph view. The linked files in the reader do not contain the incoming-only neighbours.
- A broken relation is skipped without a message. The author does not see the error in the panel.
- The package builds the graph again on each request. A large documentation set makes each request slower.
- The node id changes when a file moves. A saved link to a document then gets a 404 response.

## References

- Ticket BAP-005.
- [ADR 0001](0001-validate-env-contract-at-boot.md)
- `src/Graph/GraphBuilder.php`
- `src/Graph/FrontMatterParser.php`
- `src/Graph/Graph.php`
- `src/Graph/Node.php`
- `src/Graph/Relationship.php`
- `src/Http/Controllers/PanelController.php`
- `resources/views/panel.blade.php`
- `routes/web.php`
