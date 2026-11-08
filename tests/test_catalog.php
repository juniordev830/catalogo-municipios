<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Catalog.php';

use Municipios\Catalog;

function sampleCatalog(array $changes = []): array
{
    return array_replace([
        'fonte' => 'Conjunto demonstrativo autoral (não oficial)',
        'atualizado_em' => '2026-11-08',
        'municipios' => [
            [
                'id' => 1, 'nome' => 'Natal', 'uf' => 'RN',
                'populacao' => 800000, 'latitude' => -5.7945,
                'longitude' => -35.2110, 'codigo_ibge' => '2408102',
            ],
            [
                'id' => 2, 'nome' => 'São Gonçalo do Amarante', 'uf' => 'RN',
                'populacao' => 100000, 'latitude' => -5.7930,
                'longitude' => -35.3290, 'codigo_ibge' => '2412005',
            ],
            [
                'id' => 3, 'nome' => 'Recife', 'uf' => 'PE',
                'populacao' => 1500000, 'latitude' => -8.0476,
                'longitude' => -34.8770,
            ],
            ['id' => 4, 'nome' => 'Mossoró', 'uf' => 'RN'],
        ],
    ], $changes);
}

function equal(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' esperado=' . var_export($expected, true)
            . ' recebido=' . var_export($actual, true));
    }
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function invalid(callable $callback, string $message = ''): void
{
    try {
        $callback();
    } catch (InvalidArgumentException $error) {
        if ($message !== '' && !str_contains($error->getMessage(), $message)) {
            throw new RuntimeException('Mensagem inesperada: ' . $error->getMessage());
        }
        return;
    }
    throw new RuntimeException('Entrada inválida foi aceita');
}

function executeCli(array $args): array
{
    $process = proc_open(
        array_merge([PHP_BINARY, __DIR__ . '/../catalog.php'], $args),
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Falha ao iniciar CLI');
    }
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $out, $err];
}

$tests = [
    'validates and normalizes states' => function (): void {
        $input = sampleCatalog();
        $input['municipios'][0]['uf'] = 'rn';
        $validated = Catalog::validate($input);
        equal('RN', $validated['municipios'][0]['uf']);
        equal(4, count($validated['municipios']));
    },
    'requires metadata and real dates' => function (): void {
        invalid(fn () => Catalog::validate(sampleCatalog(['fonte' => ''])), 'fonte');
        invalid(fn () => Catalog::validate(sampleCatalog(['atualizado_em' => '2026-02-29'])), 'atualizado_em');
        equal('2028-02-29', Catalog::validate(sampleCatalog(['atualizado_em' => '2028-02-29']))['atualizado_em']);
    },
    'rejects duplicate ids' => function (): void {
        $input = sampleCatalog();
        $input['municipios'][1]['id'] = 1;
        invalid(fn () => Catalog::validate($input), 'duplicado');
    },
    'rejects missing or malformed ids' => function (): void {
        foreach ([0, '1', null, -5] as $id) {
            $input = sampleCatalog();
            $input['municipios'][0]['id'] = $id;
            invalid(fn () => Catalog::validate($input), 'id');
        }
    },
    'rejects unsupported state' => function (): void {
        $input = sampleCatalog();
        $input['municipios'][0]['uf'] = 'XX';
        invalid(fn () => Catalog::validate($input), 'UF');
    },
    'rejects invalid municipality names' => function (): void {
        foreach (['', 4, "\x00", "\xFF"] as $name) {
            $input = sampleCatalog();
            $input['municipios'][0]['nome'] = $name;
            invalid(fn () => Catalog::validate($input), 'nome');
        }
    },
    'rejects malformed coordinates' => function (): void {
        $input = sampleCatalog();
        unset($input['municipios'][0]['longitude']);
        invalid(fn () => Catalog::validate($input), 'latitude e longitude');
        $input = sampleCatalog();
        $input['municipios'][0]['latitude'] = -100;
        invalid(fn () => Catalog::validate($input), 'latitude');
    },
    'rejects invalid population and ibge codes' => function (): void {
        $input = sampleCatalog();
        $input['municipios'][0]['populacao'] = -1;
        invalid(fn () => Catalog::validate($input), 'populacao');
        $input = sampleCatalog();
        $input['municipios'][0]['codigo_ibge'] = '123';
        invalid(fn () => Catalog::validate($input), 'codigo_ibge');
    },
    'searches with accent and case folding' => function (): void {
        $result = Catalog::search(Catalog::validate(sampleCatalog()), ['termo' => 'sao gonc']);
        equal(1, $result['total']);
        equal('São Gonçalo do Amarante', $result['resultados'][0]['nome']);
    },
    'filters by uf' => function (): void {
        $result = Catalog::search(Catalog::validate(sampleCatalog()), ['uf' => 'rn']);
        equal(3, $result['total']);
        equal(['RN' => 3], $result['quantidade_por_uf']);
    },
    'filters by population and omits unknown values' => function (): void {
        $result = Catalog::search(Catalog::validate(sampleCatalog()), [
            'min_pop' => 100000, 'max_pop' => 800000,
        ]);
        equal(2, $result['total']);
        equal(['Natal', 'São Gonçalo do Amarante'], array_column($result['resultados'], 'nome'));
    },
    'paginates deterministically' => function (): void {
        $catalog = Catalog::validate(sampleCatalog());
        $first = Catalog::search($catalog, ['limit' => 2]);
        equal(4, $first['total']);
        equal(2, $first['paginacao']['proximo_offset']);
        $second = Catalog::search($catalog, ['limit' => 2, 'offset' => 2]);
        equal(null, $second['paginacao']['proximo_offset']);
        equal(2, count($second['resultados']));
    },
    'sorts descending by population' => function (): void {
        $result = Catalog::search(Catalog::validate(sampleCatalog()), [
            'sort' => 'populacao', 'order' => 'desc',
        ]);
        equal('Recife', $result['resultados'][0]['nome']);
        equal('Mossoró', $result['resultados'][3]['nome']);
    },
    'rejects invalid filters' => function (): void {
        $catalog = Catalog::validate(sampleCatalog());
        invalid(fn () => Catalog::search($catalog, ['uf' => 'XX']), 'UF');
        invalid(fn () => Catalog::search($catalog, ['limit' => 0]), 'limit');
        invalid(fn () => Catalog::search($catalog, ['offset' => -1]), 'offset');
        invalid(fn () => Catalog::search($catalog, ['sort' => 'sql']), 'sort');
        invalid(fn () => Catalog::search($catalog, ['order' => 'random']), 'order');
        invalid(fn () => Catalog::search($catalog, ['min_pop' => 10, 'max_pop' => 5]), 'min_pop');
    },
    'geofilter selects nearby cities only' => function (): void {
        $result = Catalog::search(Catalog::validate(sampleCatalog()), [
            'latitude' => -5.7945, 'longitude' => -35.211, 'raio_km' => 20,
            'sort' => 'distancia',
        ]);
        equal(2, $result['total']);
        equal('Natal', $result['resultados'][0]['nome']);
        equal(0.0, $result['resultados'][0]['distancia_km']);
        check($result['resultados'][1]['distancia_km'] > 0, 'Distância ausente');
    },
    'geofilter rejects incomplete or invalid options' => function (): void {
        $catalog = Catalog::validate(sampleCatalog());
        invalid(fn () => Catalog::search($catalog, ['latitude' => 0]), 'geográfico');
        invalid(fn () => Catalog::search($catalog, [
            'latitude' => 0, 'longitude' => 0, 'raio_km' => 0,
        ]), 'raio_km');
        invalid(fn () => Catalog::search($catalog, ['sort' => 'distancia']), 'distancia');
    },
    'statistics reports only known population values' => function (): void {
        $stats = Catalog::statistics(Catalog::validate(sampleCatalog()));
        equal(4, $stats['total_municipios']);
        equal(3, $stats['com_populacao']);
        equal(3, $stats['com_coordenadas']);
        equal(2400000, $stats['populacao_somada_dos_registros']);
        equal(['PE' => 1, 'RN' => 3], $stats['municipios_por_uf']);
    },
    'geojson excludes missing coordinates and uses lon lat order' => function (): void {
        $result = Catalog::search(Catalog::validate(sampleCatalog()));
        $geo = Catalog::geojson($result);
        equal('FeatureCollection', $geo['type']);
        equal(3, count($geo['features']));
        equal([-35.211, -5.7945], $geo['features'][0]['geometry']['coordinates']);
    },
    'csv protects spreadsheet formula values' => function (): void {
        $input = sampleCatalog();
        $input['municipios'][0]['nome'] = '=2+3';
        $csv = Catalog::csv(Catalog::search(Catalog::validate($input)));
        check(str_contains($csv, "'=2+3"), 'Fórmula não neutralizada');
        $rows = array_map('str_getcsv', array_filter(explode("\n", trim($csv))));
        equal(5, count($rows));
        equal(8, count($rows[1]));
    },
    'empty catalog is valid and searchable' => function (): void {
        $catalog = Catalog::validate(sampleCatalog(['municipios' => []]));
        equal(0, Catalog::search($catalog)['total']);
        equal(0, Catalog::statistics($catalog)['total_municipios']);
    },
    'cli produces json and stats' => function (): void {
        $file = tempnam(sys_get_temp_dir(), 'catalog');
        try {
            file_put_contents($file, json_encode(sampleCatalog(), JSON_THROW_ON_ERROR));
            [$status, $out] = executeCli([$file, '--uf=RN', '--termo=natal']);
            equal(0, $status);
            equal(1, json_decode($out, true, 32, JSON_THROW_ON_ERROR)['total']);
            [$status, $out] = executeCli([$file, '--format=stats']);
            equal(0, $status);
            equal(4, json_decode($out, true, 32, JSON_THROW_ON_ERROR)['total_municipios']);
        } finally {
            unlink($file);
        }
    },
    'cli produces csv and geojson' => function (): void {
        $file = tempnam(sys_get_temp_dir(), 'catalog');
        try {
            file_put_contents($file, json_encode(sampleCatalog(), JSON_THROW_ON_ERROR));
            [$status, $out] = executeCli([$file, '--format=csv']);
            equal(0, $status);
            check(str_contains($out, 'ID,Nome,UF'), 'CSV ausente');
            [$status, $out] = executeCli([$file, '--format=geojson']);
            equal(0, $status);
            equal(3, count(json_decode($out, true, 32, JSON_THROW_ON_ERROR)['features']));
        } finally {
            unlink($file);
        }
    },
    'cli reports errors without tracebacks' => function (): void {
        $file = tempnam(sys_get_temp_dir(), 'catalog');
        try {
            file_put_contents($file, '{');
            [$status, , $error] = executeCli([$file]);
            equal(2, $status);
            check(str_contains($error, 'Erro:'), 'Mensagem amigável ausente');
            [$status] = executeCli([$file, '--limit=no']);
            equal(2, $status);
            [$status] = executeCli([$file, '--format=wrong']);
            equal(2, $status);
        } finally {
            unlink($file);
        }
    },
];

$failures = 0;
foreach ($tests as $name => $test) {
    try {
        $test();
        echo "[OK] $name\n";
    } catch (Throwable $error) {
        $failures++;
        fwrite(STDERR, "[FAIL] $name: " . $error->getMessage() . "\n");
    }
}
echo sprintf("%d testes; %d falhas\n", count($tests), $failures);
exit($failures === 0 ? 0 : 1);
