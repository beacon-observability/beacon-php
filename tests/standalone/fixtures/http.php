<?php

declare(strict_types=1);

if (class_exists('OpenTelemetry\\API\\Globals', false) || interface_exists('Psr\\Log\\LoggerInterface', false)) { throw new RuntimeException('Private runtime polluted application dependencies'); }
require getenv('BEACON_TEST_APP_AUTOLOAD');
$handler = static function (): string {
    $pdo = new PDO('sqlite::memory:');
    if ($pdo->query('SELECT 42')->fetchColumn() !== 42) { throw new RuntimeException('PDO result changed'); }
    try { (new GuzzleHttp\Client())->get('http://127.0.0.1:1', ['connect_timeout' => 0.2]); }
    catch (GuzzleHttp\Exception\ConnectException) { return 'HTTP_APPLICATION_OK'; }
    throw new RuntimeException('Expected Guzzle exception was suppressed');
};
if (isset($_GET['symfony'])) {
    $request = Symfony\Component\HttpFoundation\Request::createFromGlobals();
    $request->attributes->set('_controller', static fn () => new Symfony\Component\HttpFoundation\Response($handler()));
    $request->attributes->set('_route', 'beacon_symfony_probe');
    $kernel = new Symfony\Component\HttpKernel\HttpKernel(new Symfony\Component\EventDispatcher\EventDispatcher(), new Symfony\Component\HttpKernel\Controller\ControllerResolver(), null, new Symfony\Component\HttpKernel\Controller\ArgumentResolver());
    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);
} elseif (isset($_GET['laravel'])) {
    $app = Illuminate\Foundation\Application::configure(basePath: dirname(__DIR__))
        ->withRouting(using: static function () use ($handler): void { Illuminate\Support\Facades\Route::get('/', $handler); })
        ->withExceptions()
        ->create();
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $request = Illuminate\Http\Request::capture();
    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);
} else { echo $handler(); }
