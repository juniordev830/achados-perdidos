<?php
declare(strict_types=1);
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
function check(Kernel $kernel, string $method, string $uri, ?string $body, int $status, ?callable $verify = null): void {
    $request = Request::create($uri, $method, [], [], [], ['HTTP_ACCEPT'=>'application/json', 'CONTENT_TYPE'=>'application/json'], $body);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    if ($response->getStatusCode() !== $status || ($verify && !$verify(json_decode($response->getContent(), true, 64, JSON_THROW_ON_ERROR)))) {
        throw new RuntimeException("$method $uri: expected $status, got ".$response->getStatusCode());
    }
}
check($kernel, 'POST', '/api/relatorios/consulta', '{"catalogo":{"itens":[]},"filtros":{"status":"pendente"}}', 200, fn ($data) => $data['total'] === 0);
check($kernel, 'POST', '/api/relatorios/consulta', '{"catalogo":{"itens":[]},"filtros":{"status":"invalido"}}', 422);
check($kernel, 'POST', '/api/relatorios/consulta', '{}', 422);
check($kernel, 'POST', '/api/relatorios/consulta', str_repeat(' ', 2000001), 413);
echo "API smoke tests passed\n";
