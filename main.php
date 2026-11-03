<?php
declare(strict_types=1);

/**
 * Portuguese accent folding without optional mbstring/intl extensions.
 * This is matching normalization, not a canonical municipality identifier.
 */
function normalizedName(string $value): string {
    return strtolower(strtr($value, [
        'Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A',
        'É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
        'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I',
        'Ó'=>'O','Ò'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O',
        'Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U',
        'Ç'=>'C','Ñ'=>'N',
        'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'ç'=>'c','ñ'=>'n'
    ]));
}

function searchCities(array $rows,string $term,string $uf): array {
    $uf=strtoupper(trim($uf));
    if (!preg_match('/^[A-Z]{2}$/D',$uf))
        throw new InvalidArgumentException('UF deve ter duas letras');
    if (strlen($term)>200)
        throw new InvalidArgumentException('Termo de busca muito longo');
    $target=normalizedName(trim($term));
    $ids=[]; $found=[];
    foreach ($rows as $row) {
        if (!is_array($row) || !isset($row['id'],$row['nome'],$row['uf']) ||
            !is_int($row['id']) || $row['id']<=0 ||
            !is_string($row['nome']) || trim($row['nome'])==='' ||
            !is_string($row['uf']) || !preg_match('/^[A-Z]{2}$/D',$row['uf']))
            throw new InvalidArgumentException('Registro inválido');
        if (isset($ids[$row['id']]))
            throw new InvalidArgumentException('ID duplicado');
        $ids[$row['id']]=true;
        if ($row['uf']===$uf &&
            ($target==='' || str_contains(normalizedName($row['nome']),$target))) {
            $found[]=$row;
        }
    }
    usort($found,fn($a,$b) =>
        strcmp(normalizedName($a['nome']),normalizedName($b['nome'])) ?: ($a['id'] <=> $b['id']));
    return $found;
}

/** Paginate only after validating the full catalog and sorting matches. */
function searchCitiesPage(array $rows,string $term,string $uf,int $limit=20,int $offset=0): array {
    if ($limit<1 || $limit>100 || $offset<0 || $offset>1000000)
        throw new InvalidArgumentException('Paginação fora dos limites');
    $all=searchCities($rows,$term,$uf);
    return ['total'=>count($all),'limite'=>$limit,'deslocamento'=>$offset,
            'resultados'=>array_slice($all,$offset,$limit)];
}

function nonNegativeInt(string $input,string $name): int {
    if (!preg_match('/^(0|[1-9][0-9]{0,6})$/D',$input))
        throw new InvalidArgumentException("$name deve ser inteiro não negativo");
    return (int)$input;
}

function expect(bool $ok): void {
    if (!$ok) throw new RuntimeException('Teste falhou');
}

try {
    if (($argv[1]??'')==='--self-test') {
        $x=[
            ['id'=>1,'nome'=>'Natal','uf'=>'RN'],
            ['id'=>2,'nome'=>'Recife','uf'=>'PE'],
            ['id'=>3,'nome'=>'São Gonçalo do Amarante','uf'=>'RN'],
            ['id'=>4,'nome'=>'São Miguel','uf'=>'RN'],
            ['id'=>5,'nome'=>'Sao Bento','uf'=>'RN']
        ];
        expect(count(searchCities($x,'nat','rn'))===1);
        expect(searchCities($x,'nat','PE')===[]);
        expect(count(searchCities($x,'sao','RN'))===3);
        expect(searchCities($x,'goncalo','RN')[0]['id']===3);
        expect(searchCities($x,'SÃO','RN')[0]['id']===5);
        $page=searchCitiesPage($x,'sao','RN',2,1);
        expect($page['total']===3 && count($page['resultados'])===2 &&
               $page['resultados'][0]['id']===3);
        expect(searchCitiesPage($x,'','RN',10,99)['resultados']===[]);
        try { searchCities([$x[0],$x[0]],'','RN'); throw new RuntimeException('Falha'); }
        catch(InvalidArgumentException $e) {}
        try { searchCitiesPage($x,'','RN',0,0); throw new RuntimeException('Falha'); }
        catch(InvalidArgumentException $e) {}
        try { searchCitiesPage($x,'','RN',20,-1); throw new RuntimeException('Falha'); }
        catch(InvalidArgumentException $e) {}
        echo "10 cenários OK\n"; exit(0);
    }
    if (count($argv)!==4 && count($argv)!==7)
        throw new InvalidArgumentException('Uso: php main.php catalogo.json UF TERMO [--pagina LIMITE DESLOCAMENTO]');
    $pageRequested=count($argv)===7;
    if ($pageRequested && $argv[4]!=='--pagina')
        throw new InvalidArgumentException('Esperada opção --pagina');
    if (!is_file($argv[1]) || filesize($argv[1])>2000000)
        throw new InvalidArgumentException('Catálogo ausente ou maior que 2 MB');
    $text=file_get_contents($argv[1]);
    if($text===false) throw new RuntimeException('Falha de leitura');
    $data=json_decode($text,true,128,JSON_THROW_ON_ERROR);
    if (!is_array($data) || !isset($data['municipios'],$data['fonte'],$data['atualizado_em']) ||
        !is_array($data['municipios']) || !is_string($data['fonte']) ||
        !is_string($data['atualizado_em']))
        throw new InvalidArgumentException('Catálogo sem metadados');
    $results=$pageRequested
        ? searchCitiesPage($data['municipios'],$argv[3],$argv[2],
            nonNegativeInt($argv[5],'LIMITE'),nonNegativeInt($argv[6],'DESLOCAMENTO'))
        : ['resultados'=>searchCities($data['municipios'],$argv[3],$argv[2])];
    echo json_encode(['fonte'=>$data['fonte'],'atualizado_em'=>$data['atualizado_em']]+$results,
        JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
} catch(Throwable $e) { fwrite(STDERR,"Erro: {$e->getMessage()}\n"); exit(2); }
