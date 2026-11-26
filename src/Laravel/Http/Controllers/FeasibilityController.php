<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Laravel\Http\Controllers;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lucacodorean\BaPanel\Core\Exceptions\InvalidConfigurationException;
use Lucacodorean\BaPanel\Core\Exceptions\InvalidFeasibilityRequestException;
use Lucacodorean\BaPanel\Core\Feasibility\FeasibilityService;
use Throwable;

/**
 * Assess a request with the feasibility agent. The request and the response are JSON.
 */
final readonly class FeasibilityController
{
    private const string INPUT_MESSAGE = 'message';

    private const string INPUT_CONTEXT = 'context';

    /**
     * Return the report of the request as an array, as Markdown, and as safe HTML.
     *
     * Request: {"message": string, "context": list<string>}. Each context item is a node id.
     * Response 200: {"report": array, "markdown": string, "html": string}.
     * Errors: {"message": string} with 422 for an invalid request,
     * 503 when the AI is not configured or the prompt file is not valid, 502 when the agent fails.
     */
    public function assess(Request $request, FeasibilityService $feasibility, ExceptionHandler $exceptions): JsonResponse
    {
        // The response does not include the exception details. The details can contain provider data.
        try {
            return new JsonResponse($feasibility->assess(
                $request->input(self::INPUT_MESSAGE),
                $request->input(self::INPUT_CONTEXT, []),
            ));
        } catch (InvalidFeasibilityRequestException $exception) {
            return $this->error($exception->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (InvalidConfigurationException $exception) {
            $exceptions->report($exception);

            return $this->error(FeasibilityService::AI_NOT_CONFIGURED, JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        } catch (Throwable $exception) {
            $exceptions->report($exception);

            return $this->error(FeasibilityService::AGENT_FAILED, JsonResponse::HTTP_BAD_GATEWAY);
        }
    }

    private function error(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status);
    }
}
