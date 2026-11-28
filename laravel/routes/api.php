<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Municipios\Catalog;

Route::get('/municipios', function (Request $request) {
    $filters = $request->only(['uf', 'termo', 'min_pop', 'max_pop', 'sort', 'order', 'limit', 'offset', 'latitude', 'longitude', 'raio_km']);
    foreach ($filters as $value) {
        if (!is_string($value)) {
            return response()->json(['erro' => 'Filtros devem ser valores simples.'], 422);
        }
    }
    $catalog = Catalog::validate(json_decode(file_get_contents(base_path('../examples/catalogo.json')), true, 64, JSON_THROW_ON_ERROR));
    try {
        return response()->json(Catalog::search($catalog, $filters));
    } catch (InvalidArgumentException $error) {
        return response()->json(['erro' => $error->getMessage()], 422);
    }
});
Route::get('/municipios/estatisticas', function () {
    $catalog = Catalog::validate(json_decode(file_get_contents(base_path('../examples/catalogo.json')), true, 64, JSON_THROW_ON_ERROR));
    return response()->json(Catalog::statistics($catalog));
});
