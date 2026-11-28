<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use CatalogReports;
use Illuminate\Http\Request;

final class ReportController
{
    public function search(Request $request)
    {
        return $this->withCatalog($request, function (array $payload) {
            $result = CatalogReports::search($payload['catalogo'], $payload['filtros'] ?? []);
            $result['itens'] = array_map(static fn (array $item) => array_intersect_key($item,
                array_flip(['id', 'objeto', 'categoria', 'encontrado_em', 'status', 'devolvido_em'])), $result['itens']);
            return response()->json($result);
        });
    }

    public function summary(Request $request)
    {
        return $this->withCatalog($request, function (array $payload) {
            if (!is_string($payload['data_referencia'] ?? null)) throw new \InvalidArgumentException('Informe data_referencia.');
            return response()->json(CatalogReports::summary($payload['catalogo'], $payload['data_referencia']));
        });
    }

    private function withCatalog(Request $request, callable $action)
    {
        if (!$request->isJson()) return response()->json(['erro' => 'Envie application/json.'], 415);
        if (strlen($request->getContent()) > 2000000) return response()->json(['erro' => 'Corpo acima de 2 MB.'], 413);
        try {
            $document = json_decode($request->getContent(), false, 64, JSON_THROW_ON_ERROR);
            if (!$document instanceof \stdClass || !($document->catalogo ?? null) instanceof \stdClass
                || (isset($document->filtros) && !$document->filtros instanceof \stdClass)) {
                throw new \InvalidArgumentException('Envie catalogo e filtros como objetos.');
            }
            $payload = json_decode($request->getContent(), true, 64, JSON_THROW_ON_ERROR);
            return $action($payload);
        } catch (\InvalidArgumentException | \JsonException $error) {
            return response()->json(['erro' => $error->getMessage()], 422);
        }
    }
}
