<?php
declare(strict_types=1);

require_once __DIR__ . '/src/Catalog.php';

use Municipios\Catalog;

/**
 * Read-only catalog CLI. Source files are never modified.
 *
 * php catalog.php examples/catalogo.json --uf=RN --termo=natal
 * php catalog.php examples/catalogo.json --latitude=-5.79 --longitude=-35.21 --raio-km=50
 */
function catalogCli(array $arguments): int
{
    $usage = "Uso: php catalog.php catalogo.json [--uf=RN] [--termo=Natal] [--sort=nome] [--order=asc] [--limit=50] [--offset=0] [--format=json|csv|geojson|stats]\n";
    if (count($arguments) < 2 || in_array($arguments[1], ['-h', '--help'], true)) {
        fwrite(STDERR, $usage);
        return count($arguments) > 1 ? 0 : 2;
    }
    $options = [];
    $format = 'json';
    $names = [
        'uf' => 'uf', 'termo' => 'termo', 'sort' => 'sort', 'order' => 'order',
        'limit' => 'limit', 'offset' => 'offset', 'min-pop' => 'min_pop',
        'max-pop' => 'max_pop', 'latitude' => 'latitude',
        'longitude' => 'longitude', 'raio-km' => 'raio_km',
    ];
    foreach (array_slice($arguments, 2) as $argument) {
        if (!str_starts_with($argument, '--') || !str_contains($argument, '=')) {
            fwrite(STDERR, "Opção inválida: $argument\n");
            return 2;
        }
        [$name, $value] = explode('=', substr($argument, 2), 2);
        if ($name === 'format') {
            $format = $value;
        } elseif (isset($names[$name])) {
            $key = $names[$name];
            if (in_array($key, ['limit', 'offset', 'min_pop', 'max_pop'], true)) {
                if (!preg_match('/^\d+$/D', $value)) {
                    fwrite(STDERR, "Valor inteiro inválido: $argument\n");
                    return 2;
                }
                $options[$key] = (int) $value;
            } elseif (in_array($key, ['latitude', 'longitude', 'raio_km'], true)) {
                if (!is_numeric($value)) {
                    fwrite(STDERR, "Número inválido: $argument\n");
                    return 2;
                }
                $options[$key] = (float) $value;
            } else {
                $options[$key] = $value;
            }
        } else {
            fwrite(STDERR, "Opção desconhecida: $argument\n");
            return 2;
        }
    }
    if (!in_array($format, ['json', 'csv', 'geojson', 'stats'], true)) {
        fwrite(STDERR, "Formato inválido: $format\n");
        return 2;
    }
    try {
        $file = $arguments[1];
        if (!is_file($file) || !is_readable($file)) {
            throw new RuntimeException('Arquivo não encontrado ou ilegível');
        }
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException('Falha ao ler catálogo');
        }
        $input = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($input)) {
            throw new InvalidArgumentException('JSON deve conter um objeto');
        }
        $catalog = Catalog::validate($input);
        if ($format === 'stats') {
            $output = Catalog::statistics($catalog);
        } else {
            $search = Catalog::search($catalog, $options);
            $output = match ($format) {
                'csv' => Catalog::csv($search),
                'geojson' => Catalog::geojson($search),
                default => $search,
            };
        }
        fwrite(STDOUT, is_string($output) ? $output :
            json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
        return 0;
    } catch (JsonException | InvalidArgumentException | RuntimeException $error) {
        fwrite(STDERR, 'Erro: ' . $error->getMessage() . "\n");
        return 2;
    }
}

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    exit(catalogCli($argv));
}
