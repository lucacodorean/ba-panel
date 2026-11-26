<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Symfony\Controller;

use Lucacodorean\BaPanel\Core\Exceptions\InvalidConfigurationException;
use Lucacodorean\BaPanel\Core\Exceptions\InvalidFeasibilityRequestException;
use Lucacodorean\BaPanel\Core\Feasibility\FeasibilityService;
use Lucacodorean\BaPanel\Symfony\BaPanelBundle;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Throwable;

/**
 * Assess a request with the feasibility agent. The request and the response are JSON.
 */
final readonly class FeasibilityController
{
    public const string CSRF_INVALID = 'The CSRF token is invalid.';

    public const string BODY_INVALID = 'The request body must be a JSON object.';

    private const string INPUT_MESSAGE = 'message';

    private const string INPUT_CONTEXT = 'context';

    private const string LOG_MESSAGE = 'The feasibility agent failed.';

    /**
     * @param  ?CsrfTokenManagerInterface  $csrf  Null when the host has no CSRF token manager. Then the CSRF check is off.
     */
    public function __construct(
        private FeasibilityService $feasibility,
        private LoggerInterface $logger,
        private ?CsrfTokenManagerInterface $csrf = null,
    ) {}

    /**
     * Return the report of the request as an array, as Markdown, and as safe HTML.
     *
     * Request: {"message": string, "context": list<string>}. Each context item is a node id.
     * Response 200: {"report": array, "markdown": string, "html": string}.
     * Errors: {"message": string} with 403 for an invalid CSRF token, 422 for an invalid request,
     * 503 when the AI is not configured or the prompt file is not valid, 502 when the agent fails.
     */
    public function assess(Request $request): JsonResponse
    {
        if (! $this->hasValidCsrfToken($request)) {
            return $this->error(self::CSRF_INVALID, JsonResponse::HTTP_FORBIDDEN);
        }

        try {
            $input = $request->toArray();
        } catch (JsonException) {
            return $this->error(self::BODY_INVALID, JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        // The response does not include the exception details. The details can contain provider data.
        try {
            return new JsonResponse($this->feasibility->assess(
                $input[self::INPUT_MESSAGE] ?? null,
                array_key_exists(self::INPUT_CONTEXT, $input) ? $input[self::INPUT_CONTEXT] : [],
            ));
        } catch (InvalidFeasibilityRequestException $exception) {
            return $this->error($exception->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (InvalidConfigurationException $exception) {
            $this->logger->error(FeasibilityService::AI_NOT_CONFIGURED, ['exception' => $exception]);

            return $this->error(FeasibilityService::AI_NOT_CONFIGURED, JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        } catch (Throwable $exception) {
            $this->logger->error(self::LOG_MESSAGE, ['exception' => $exception]);

            return $this->error(FeasibilityService::AGENT_FAILED, JsonResponse::HTTP_BAD_GATEWAY);
        }
    }

    /**
     * Return true when the host has no CSRF token manager, or when the header has a valid token.
     */
    private function hasValidCsrfToken(Request $request): bool
    {
        if ($this->csrf === null) {
            return true;
        }

        $token = $request->headers->get(PanelController::CSRF_HEADER);

        return is_string($token) && $token !== '' && $this->csrf->isTokenValid(new CsrfToken(BaPanelBundle::CSRF_TOKEN_ID, $token));
    }

    private function error(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status);
    }
}
