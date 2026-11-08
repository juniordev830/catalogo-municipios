<?php
declare(strict_types=1);

namespace Municipios;

/**
 * Validates local catalog data and searches it without network requests.
 * Geographic calculations are approximate (great-circle distance).
 */
final class Catalog
{
    private const UFS = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO',
        'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI',
        'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ];

    /** @param array<string,mixed> $input */
    public static function validate(array $input): array
    {
        $source = self::requiredText($input['fonte'] ?? null, 'fonte', 240);
        $updated = $input['atualizado_em'] ?? null;
        if (!is_string($updated) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $updated, $parts)
            || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            throw new \InvalidArgumentException('atualizado_em deve ser uma data válida AAAA-MM-DD');
        }

        $cities = $input['municipios'] ?? null;
        if (!is_array($cities) || !array_is_list($cities) || count($cities) > 100000) {
            throw new \InvalidArgumentException('municipios deve ser uma lista de até 100000 registros');
        }
        $seen = [];
        $validated = [];
        foreach ($cities as $position => $city) {
            if (!is_array($city) || array_is_list($city)) {
                throw new \InvalidArgumentException("municipios[$position] deve ser um objeto");
            }
            $id = $city['id'] ?? null;
            if (!is_int($id) || $id <= 0) {
                throw new \InvalidArgumentException("municipios[$position].id inválido");
            }
            if (isset($seen[$id])) {
                throw new \InvalidArgumentException("ID duplicado: $id");
            }
            $seen[$id] = true;
            $name = self::requiredText($city['nome'] ?? null, "municipios[$position].nome", 150);
            $uf = self::state($city['uf'] ?? null);
            $population = self::optionalPopulation($city['populacao'] ?? null, $position);
            $latitude = self::coordinate($city['latitude'] ?? null, -90, 90, 'latitude');
            $longitude = self::coordinate($city['longitude'] ?? null, -180, 180, 'longitude');
            if (($latitude === null) !== ($longitude === null)) {
                throw new \InvalidArgumentException("municipios[$position] requer latitude e longitude juntas");
            }
            $ibge = $city['codigo_ibge'] ?? null;
            if ($ibge !== null && (!is_string($ibge) || !preg_match('/^\d{7}$/D', $ibge))) {
                throw new \InvalidArgumentException("municipios[$position].codigo_ibge deve ter 7 dígitos");
            }
            $validated[] = [
                'id' => $id,
                'nome' => $name,
                'uf' => $uf,
                'populacao' => $population,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'codigo_ibge' => $ibge,
            ];
        }
        return [
            'fonte' => $source,
            'atualizado_em' => $updated,
            'municipios' => $validated,
        ];
    }

    /**
     * Search with stable pagination, accent-insensitive text and optional radius.
     * The input must already be validated by validate().
     */
    public static function search(array $catalog, array $options = []): array
    {
        if (!isset($catalog['municipios'], $catalog['fonte'], $catalog['atualizado_em'])) {
            throw new \InvalidArgumentException('Catálogo não validado');
        }
        $uf = $options['uf'] ?? null;
        if ($uf !== null && $uf !== '') {
            $uf = self::state($uf);
        } else {
            $uf = null;
        }
        $term = $options['termo'] ?? '';
        if (!is_string($term) || strlen($term) > 200) {
            throw new \InvalidArgumentException('termo inválido');
        }
        $term = self::normalize(trim($term));
        $min = self::populationFilter($options['min_pop'] ?? null, 'min_pop');
        $max = self::populationFilter($options['max_pop'] ?? null, 'max_pop');
        if ($min !== null && $max !== null && $min > $max) {
            throw new \InvalidArgumentException('min_pop não pode exceder max_pop');
        }
        $sort = $options['sort'] ?? 'nome';
        if (!in_array($sort, ['nome', 'uf', 'id', 'populacao', 'distancia'], true)) {
            throw new \InvalidArgumentException('sort inválido');
        }
        $order = $options['order'] ?? 'asc';
        if (!in_array($order, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException('order inválido');
        }
        $limit = self::boundedInt($options['limit'] ?? 50, 'limit', 1, 1000);
        $offset = self::boundedInt($options['offset'] ?? 0, 'offset', 0, 1000000);
        $origin = self::origin($options);
        if ($sort === 'distancia' && $origin === null) {
            throw new \InvalidArgumentException('sort=distancia requer latitude, longitude e raio_km');
        }

        $filtered = [];
        foreach ($catalog['municipios'] as $city) {
            if ($uf !== null && $city['uf'] !== $uf) {
                continue;
            }
            if ($term !== '' && !str_contains(self::normalize($city['nome']), $term)) {
                continue;
            }
            if ($min !== null && ($city['populacao'] === null || $city['populacao'] < $min)) {
                continue;
            }
            if ($max !== null && ($city['populacao'] === null || $city['populacao'] > $max)) {
                continue;
            }
            if ($origin !== null) {
                if ($city['latitude'] === null || $city['longitude'] === null) {
                    continue;
                }
                $distance = self::distance(
                    $origin[0], $origin[1], $city['latitude'], $city['longitude']
                );
                if ($distance > $origin[2]) {
                    continue;
                }
                $city['distancia_km'] = round($distance, 2);
            }
            $filtered[] = $city;
        }

        usort($filtered, static function (array $left, array $right) use ($sort, $order): int {
            $key = $sort === 'distancia' ? 'distancia_km' : $sort;
            $a = $left[$key] ?? null;
            $b = $right[$key] ?? null;
            if ($a === null && $b !== null) {
                return 1;
            }
            if ($b === null && $a !== null) {
                return -1;
            }
            $comparison = is_string($a) && is_string($b)
                ? strcmp(Catalog::normalize($a), Catalog::normalize($b))
                : ($a <=> $b);
            if ($order === 'desc') {
                $comparison = -$comparison;
            }
            return $comparison ?: $left['id'] <=> $right['id'];
        });

        $counts = [];
        foreach ($filtered as $city) {
            $counts[$city['uf']] = ($counts[$city['uf']] ?? 0) + 1;
        }
        ksort($counts);
        $total = count($filtered);
        return [
            'fonte' => $catalog['fonte'],
            'atualizado_em' => $catalog['atualizado_em'],
            'total' => $total,
            'paginacao' => [
                'limit' => $limit,
                'offset' => $offset,
                'proximo_offset' => $offset + $limit < $total ? $offset + $limit : null,
            ],
            'quantidade_por_uf' => $counts,
            'resultados' => array_slice($filtered, $offset, $limit),
        ];
    }

    public static function statistics(array $catalog): array
    {
        $states = [];
        $withPopulation = 0;
        $withCoordinates = 0;
        $populationTotal = 0;
        foreach ($catalog['municipios'] as $city) {
            $states[$city['uf']] = ($states[$city['uf']] ?? 0) + 1;
            if ($city['populacao'] !== null) {
                $withPopulation++;
                $populationTotal += $city['populacao'];
            }
            if ($city['latitude'] !== null) {
                $withCoordinates++;
            }
        }
        ksort($states);
        return [
            'total_municipios' => count($catalog['municipios']),
            'municipios_por_uf' => $states,
            'com_populacao' => $withPopulation,
            'com_coordenadas' => $withCoordinates,
            'populacao_somada_dos_registros' => $populationTotal,
            'fonte' => $catalog['fonte'],
            'atualizado_em' => $catalog['atualizado_em'],
        ];
    }

    public static function csv(array $search): string
    {
        $handle = fopen('php://temp', 'w+');
        if ($handle === false) {
            throw new \RuntimeException('Falha ao preparar CSV');
        }
        try {
            fputcsv($handle, ['ID', 'Nome', 'UF', 'População', 'Latitude', 'Longitude', 'Código IBGE', 'Distância km'], ',', '"', '');
            foreach ($search['resultados'] as $city) {
                fputcsv($handle, [
                    $city['id'],
                    self::safeCsv($city['nome']),
                    $city['uf'],
                    $city['populacao'] ?? '',
                    $city['latitude'] ?? '',
                    $city['longitude'] ?? '',
                    $city['codigo_ibge'] ?? '',
                    $city['distancia_km'] ?? '',
                ], ',', '"', '');
            }
            rewind($handle);
            $result = stream_get_contents($handle);
            if ($result === false) {
                throw new \RuntimeException('Falha ao gerar CSV');
            }
            return $result;
        } finally {
            fclose($handle);
        }
    }

    public static function geojson(array $search): array
    {
        $features = [];
        foreach ($search['resultados'] as $city) {
            if ($city['latitude'] === null || $city['longitude'] === null) {
                continue;
            }
            $properties = [
                'id' => $city['id'],
                'nome' => $city['nome'],
                'uf' => $city['uf'],
                'populacao' => $city['populacao'],
                'codigo_ibge' => $city['codigo_ibge'],
            ];
            if (isset($city['distancia_km'])) {
                $properties['distancia_km'] = $city['distancia_km'];
            }
            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$city['longitude'], $city['latitude']],
                ],
                'properties' => $properties,
            ];
        }
        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }

    private static function safeCsv(string $value): string
    {
        return preg_match('/^[\s\x00-\x1F]*[=+@\-]/u', $value) === 1 ? "'" . $value : $value;
    }

    private static function origin(array $options): ?array
    {
        $keys = ['latitude', 'longitude', 'raio_km'];
        $present = array_filter($keys, static fn (string $key): bool => array_key_exists($key, $options));
        if (count($present) === 0) {
            return null;
        }
        if (count($present) !== 3) {
            throw new \InvalidArgumentException('Filtro geográfico requer latitude, longitude e raio_km');
        }
        $lat = self::coordinate($options['latitude'], -90, 90, 'latitude');
        $lon = self::coordinate($options['longitude'], -180, 180, 'longitude');
        $radius = $options['raio_km'];
        if ($lat === null || $lon === null || !is_numeric($radius)
            || is_bool($radius) || !is_finite((float) $radius)
            || (float) $radius <= 0 || (float) $radius > 20000) {
            throw new \InvalidArgumentException('Coordenadas ou raio_km inválidos');
        }
        return [$lat, $lon, (float) $radius];
    }

    private static function distance(float $aLat, float $aLon, float $bLat, float $bLon): float
    {
        $latDelta = deg2rad($bLat - $aLat);
        $lonDelta = deg2rad($bLon - $aLon);
        $h = sin($latDelta / 2) ** 2
            + cos(deg2rad($aLat)) * cos(deg2rad($bLat)) * sin($lonDelta / 2) ** 2;
        return 6371.0088 * 2 * asin(min(1.0, sqrt($h)));
    }

    private static function state(mixed $value): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException('UF inválida');
        }
        $uf = strtoupper(trim($value));
        if (!in_array($uf, self::UFS, true)) {
            throw new \InvalidArgumentException("UF inválida: $uf");
        }
        return $uf;
    }

    private static function optionalPopulation(mixed $value, int $position): ?int
    {
        if ($value === null) {
            return null;
        }
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("municipios[$position].populacao inválida");
        }
        return $value;
    }

    private static function populationFilter(mixed $value, string $name): ?int
    {
        if ($value === null) {
            return null;
        }
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("$name deve ser inteiro não negativo");
        }
        return $value;
    }

    private static function boundedInt(mixed $value, string $name, int $min, int $max): int
    {
        if (!is_int($value) || $value < $min || $value > $max) {
            throw new \InvalidArgumentException("$name deve ser inteiro entre $min e $max");
        }
        return $value;
    }

    private static function coordinate(mixed $value, int $min, int $max, string $name): ?float
    {
        if ($value === null) {
            return null;
        }
        if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)
            || $value < $min || $value > $max) {
            throw new \InvalidArgumentException("$name inválida");
        }
        return (float) $value;
    }

    private static function requiredText(mixed $value, string $name, int $max): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException("$name deve ser texto não vazio");
        }
        $text = trim($value);
        if (!preg_match('//u', $text)
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $text)
            || preg_match_all('/./us', $text) > $max) {
            throw new \InvalidArgumentException("$name inválido ou muito longo");
        }
        return $text;
    }

    private static function normalize(string $value): string
    {
        $value = strtr($value, [
            'Á' => 'a', 'À' => 'a', 'Â' => 'a', 'Ã' => 'a', 'Ä' => 'a',
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'É' => 'e', 'È' => 'e', 'Ê' => 'e', 'Ë' => 'e',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'Í' => 'i', 'Ì' => 'i', 'Î' => 'i', 'Ï' => 'i',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'Ó' => 'o', 'Ò' => 'o', 'Ô' => 'o', 'Õ' => 'o', 'Ö' => 'o',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'Ú' => 'u', 'Ù' => 'u', 'Û' => 'u', 'Ü' => 'u',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'Ç' => 'c', 'ç' => 'c',
        ]);
        return strtolower($value);
    }
}
