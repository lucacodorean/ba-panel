<?php

declare(strict_types=1);

namespace Lucacodorean\BaPanel\Tests\Support\Symfony;

use Lucacodorean\BaPanel\Core\Ai\FeasibilityAgentFactory;
use Lucacodorean\BaPanel\Core\Ai\ProviderFactory;
use Lucacodorean\BaPanel\Core\Config\AiSettings;
use Lucacodorean\BaPanel\Tests\Support\FakeFeasibilityAgentFactory;
use Lucacodorean\BaPanel\Tests\Support\RecordingLogger;
use Lucacodorean\BaPanel\Tests\Support\TemporaryFilesystem;
use NeuronAI\Testing\FakeAIProvider;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Boot the test kernel and send requests to it.
 *
 * All panels of one test process share one project directory and one cache root. The cache root is removed at shutdown.
 * Call reset() after each test. It removes the docs and the templates of the project directory.
 */
final class SymfonyPanel
{
    public const string CSRF_HEADER = 'X-CSRF-Token';

    private static ?TemporaryFilesystem $filesystem = null;

    private readonly TestKernel $kernel;

    public readonly RecordingLogger $logger;

    /**
     * @var array<string, string>
     */
    private array $cookies = [];

    /**
     * @param  array<string, mixed>|null  $config  The "ba_panel" config. Null to use the env defaults.
     * @param  array<string, string|null>  $env  The BAP_* environment values that the test has set.
     */
    private function __construct(?array $config, bool $csrf, array $env, private readonly bool $fakeAgents)
    {
        $this->kernel = new TestKernel(self::projectDir(), self::filesystem()->path('cache'), $config, $csrf, $env, $fakeAgents);
        $this->logger = new RecordingLogger;
    }

    /**
     * Return a panel with the given config. The kernel is not booted yet.
     *
     * @param  array<string, mixed>|null  $config
     * @param  array<string, string|null>  $env
     * @param  bool  $fakeAgents  False to keep the real agent factory of the bundle. Never send a feasibility request then.
     */
    public static function create(?array $config, bool $csrf = false, array $env = [], bool $fakeAgents = true): self
    {
        return new self($config, $csrf, $env, $fakeAgents);
    }

    /**
     * Return a valid enabled config. The docs path is relative to the project directory.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function enabledConfig(array $overrides = []): array
    {
        return array_replace([
            'enabled' => true,
            'ai' => ['provider' => 'openai', 'model' => 'gpt-test', 'key' => 'secret-key'],
            'docs' => ['docs'],
        ], $overrides);
    }

    /**
     * Return the project directory of all test panels.
     */
    public static function projectDir(): string
    {
        return self::filesystem()->directory('project');
    }

    /**
     * Write a file under the project directory and return its path.
     */
    public static function file(string $relative, string $contents): string
    {
        return self::filesystem()->file('project/'.$relative, $contents);
    }

    /**
     * Return the node id of the file under the project directory.
     */
    public static function nodeId(string $relative): string
    {
        $path = realpath(self::projectDir().'/'.$relative);

        if ($path === false) {
            throw new RuntimeException('Missing fixture: '.$relative);
        }

        return substr(sha1($path), 0, 12);
    }

    /**
     * Remove the docs and the templates of the project directory. The caches stay for the next test.
     */
    public static function reset(): void
    {
        if (self::$filesystem === null) {
            return;
        }

        foreach (['docs', 'templates'] as $directory) {
            self::remove(self::projectDir().'/'.$directory);
        }

        self::projectDir();
    }

    /**
     * Boot the kernel. Set the fake agent factory and the recording logger.
     * The default provider has no responses. Thus a test that does not give a provider gets a 502, never a real request.
     */
    public function boot(?FakeAIProvider $provider = null): self
    {
        $this->kernel->boot();
        $this->container()->set('logger', $this->logger);

        if ($this->fakeAgents) {
            $this->useProvider($provider ?? new FakeAIProvider);
        }

        return $this;
    }

    /**
     * Replace the agent factory with a factory that gives each agent the provider.
     */
    public function useProvider(FakeAIProvider $provider): FakeAIProvider
    {
        $this->useAgentFactory(new FakeFeasibilityAgentFactory(new ProviderFactory, new AiSettings, $provider));

        return $provider;
    }

    /**
     * Replace the agent factory, for example with the real factory and other settings. Only with fake agents.
     */
    public function useAgentFactory(FeasibilityAgentFactory $factory): void
    {
        $this->container()->set(FeasibilityAgentFactory::class, $factory);
    }

    public function container(): ContainerInterface
    {
        return $this->kernel->getContainer();
    }

    public function kernel(): TestKernel
    {
        return $this->kernel;
    }

    /**
     * Send the request. Keep the response cookies for the next request, as a browser does.
     */
    public function handle(Request $request): Response
    {
        foreach ($this->cookies as $name => $value) {
            $request->cookies->set($name, $value);
        }

        $response = $this->kernel->handle($request);
        $this->kernel->terminate($request, $response);

        foreach ($response->headers->getCookies() as $cookie) {
            $this->cookies[$cookie->getName()] = (string) $cookie->getValue();
        }

        return $response;
    }

    /**
     * Send a GET request.
     *
     * @param  array<string, mixed>  $query
     */
    public function get(string $uri, array $query = []): Response
    {
        return $this->handle(Request::create($uri, 'GET', $query));
    }

    /**
     * Send a POST request with the raw body as JSON.
     *
     * @param  array<string, string>  $headers  The request headers, for example ["X-CSRF-Token" => "..."].
     */
    public function postJson(string $uri, string $body, array $headers = []): Response
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->handle(Request::create($uri, 'POST', [], [], [], $server, $body));
    }

    /**
     * Send the feasibility request with the CSRF token of the panel page.
     *
     * @param  array<string, mixed>|string  $payload  An array is encoded as JSON. A string is sent as is.
     */
    public function postFeasibility(array|string $payload, ?string $token = null): Response
    {
        $body = is_string($payload) ? $payload : json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->postJson('/ba-panel/feasibility', $body, $token === null ? [] : [self::CSRF_HEADER => $token]);
    }

    /**
     * Open the panel page and return the CSRF token of the meta tag. Return null when the page has no token.
     */
    public function csrfToken(): ?string
    {
        $content = (string) $this->get('/ba-panel')->getContent();

        return preg_match('/<meta name="csrf-token" content="([^"]+)">/', $content, $matches) === 1 ? $matches[1] : null;
    }

    public function shutdown(): void
    {
        $this->kernel->shutdown();
    }

    /**
     * Return the JSON body of the response as an array.
     *
     * @return array<mixed>
     */
    public static function json(Response $response): array
    {
        $data = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new RuntimeException('The response is not a JSON object.');
        }

        return $data;
    }

    private static function filesystem(): TemporaryFilesystem
    {
        if (self::$filesystem === null) {
            $filesystem = new TemporaryFilesystem;
            self::$filesystem = $filesystem;

            register_shutdown_function(static function () use ($filesystem): void {
                $filesystem->cleanup();
            });
        }

        return self::$filesystem;
    }

    private static function remove(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            unlink($path);

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::remove($path.'/'.$entry);
            }
        }

        rmdir($path);
    }
}
