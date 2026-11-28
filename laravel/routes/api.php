<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/relatorios/consulta', function (Request $request) {
    if (!$request->isJson()) {
        return response()->json(['erro' => 'Envie application/json.'], 415);
    }
    if (strlen($request->getContent()) > 2000000) {
        return response()->json(['erro' => 'Corpo acima de 2 MB.'], 413);
    }
    try {
        $payload = json_decode($request->getContent(), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($payload) || !is_array($payload['catalogo'] ?? null) || !is_array($payload['filtros'] ?? [])) {
            throw new InvalidArgumentException('Envie catalogo e filtros como objetos.');
        }
        $result = CatalogReports::search($payload['catalogo'], $payload['filtros'] ?? []);
        $result['itens'] = array_map(static fn (array $item) => array_intersect_key($item, array_flip(['id', 'objeto', 'categoria', 'encontrado_em', 'status', 'devolvido_em'])), $result['itens']);
        return response()->json($result);
    } catch (InvalidArgumentException | JsonException $error) {
        return response()->json(['erro' => $error->getMessage()], 422);
    }
});
