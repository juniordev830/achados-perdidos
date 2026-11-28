<?php
declare(strict_types=1);
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

putenv('CACHE_STORE=array');
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
set_exception_handler(function (Throwable $error): void { fwrite(STDERR, (string) $error); exit(1); });
$checks = 0;
function http(string $method, string $uri, int $status, ?string $body = null, ?callable $verify = null, array $server = []) {
    global $kernel, $checks;
    $request = Request::create($uri, $method, [], [], [], $server + ['HTTP_ACCEPT'=>'application/json', 'CONTENT_TYPE'=>'application/json'], $body);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    if ($response->getStatusCode() !== $status || ($verify && !$verify($response))) {
        throw new RuntimeException("$method $uri: expected $status, got ".$response->getStatusCode().' '.$response->getContent());
    }
    $checks++;
    return $response;
}
function payload($response): array { return json_decode($response->getContent(), true, 64, JSON_THROW_ON_ERROR); }

$catalog = ['itens'=>[
    ['id'=>'chave-1','objeto'=>'Chave','categoria'=>'chaves','encontrado_em'=>'2026-11-01','status'=>'pendente',
     'historico'=>[['evento'=>'encontrado','data'=>'2026-11-01']], 'return_reference'=>'segredo', 'contato'=>'privado', 'cpf'=>'privado'],
]];
$body = json_encode(['catalogo'=>$catalog,'filtros'=>(object)[]], JSON_THROW_ON_ERROR);
http('POST','/api/relatorios/consulta',200,$body,fn ($r) => payload($r)['total'] === 1 && !str_contains($r->getContent(),'privado') && !str_contains($r->getContent(),'segredo') && !isset(payload($r)['itens'][0]['historico']));
http('POST','/api/relatorios/resumo',200,json_encode(['catalogo'=>$catalog,'data_referencia'=>'2026-11-28'], JSON_THROW_ON_ERROR),
    fn ($r) => payload($r) == CatalogReports::summary($catalog,'2026-11-28'));
foreach (['[]','{"catalogo":[]}','{"catalogo":{"itens":[]},"filtros":[]}','{','null'] as $invalid) {
    http('POST','/api/relatorios/consulta',422,$invalid);
}
http('POST','/api/relatorios/resumo',422,'{"catalogo":{"itens":[]},"data_referencia":[]}');
http('POST','/api/relatorios/resumo',422,'{"catalogo":{"itens":[]},"data_referencia":"2026-02-30"}');
http('POST','/api/relatorios/consulta',415,$body,null,['CONTENT_TYPE'=>'text/plain']);
http('POST','/api/relatorios/consulta',413,str_repeat(' ',2000001));
Cache::store('array')->flush();
for ($i=0;$i<60;$i++) http('POST','/api/relatorios/consulta',200,$body);
http('POST','/api/relatorios/consulta',429,$body,fn ($r) => $r->headers->has('Retry-After'));
http('GET','/up',200);
http('PUT','/storage/probe',404);
echo "API regression checks passed: $checks\n";
