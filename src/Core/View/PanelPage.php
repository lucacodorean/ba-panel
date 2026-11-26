<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Core\View;

use Lucacodorean\BaPanel\Core\Feasibility\FeasibilityRequest;

/**
 * The data of the panel page. The shells build it and render the "panel" template with toArray().
 */
final readonly class PanelPage
{
    public const string TEMPLATE = 'panel';

    /**
     * The node id in the document URL template. The panel script replaces it with the real node id.
     */
    public const string DOCUMENT_ID_PLACEHOLDER = '000000000000';

    public const string DEFAULT_LOCALE = 'en';

    /**
     * @param  array{nodes: list<array<string, mixed>>, relationships: list<array<string, mixed>>}  $graph
     * @param  string  $documentUrlTemplate  The document URL with DOCUMENT_ID_PLACEHOLDER as the node id.
     * @param  ?string  $csrfToken  The CSRF token. Null when the host has no CSRF protection.
     * @param  string  $csrfHeader  The request header that sends the CSRF token.
     */
    public function __construct(
        public array $graph,
        public string $documentUrlTemplate,
        public string $searchUrl,
        public string $feasibilityUrl,
        public ?string $csrfToken,
        public string $csrfHeader,
        public string $locale = self::DEFAULT_LOCALE,
        public int $maxMessageLength = FeasibilityRequest::MAX_MESSAGE_LENGTH,
        public int $maxContextFiles = FeasibilityRequest::MAX_CONTEXT_FILES,
    ) {}

    /**
     * Return the template variables.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'graph' => $this->graph,
            'documentUrlTemplate' => $this->documentUrlTemplate,
            'searchUrl' => $this->searchUrl,
            'feasibilityUrl' => $this->feasibilityUrl,
            'csrfToken' => $this->csrfToken,
            'csrfHeader' => $this->csrfHeader,
            'locale' => $this->locale,
            'maxMessageLength' => $this->maxMessageLength,
            'maxContextFiles' => $this->maxContextFiles,
        ];
    }
}
