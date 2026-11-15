<?php
declare(strict_types=1);
function normalizeSearch(string $value): string {
    return strtolower(strtr($value, [
        'Á'=>'a','À'=>'a','Â'=>'a','Ã'=>'a','Ä'=>'a','á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a',
        'É'=>'e','È'=>'e','Ê'=>'e','Ë'=>'e','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'Í'=>'i','Ì'=>'i','Î'=>'i','Ï'=>'i','í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'Ó'=>'o','Ò'=>'o','Ô'=>'o','Õ'=>'o','Ö'=>'o','ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
        'Ú'=>'u','Ù'=>'u','Û'=>'u','Ü'=>'u','ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'Ç'=>'c','ç'=>'c'
    ]));
}
function searchCities(array $rows,string $term,string $uf): array {
    $uf=strtoupper($uf); if (!preg_match('/^[A-Z]{2}$/',$uf)) throw new InvalidArgumentException('UF deve ter duas letras');
    $ids=[]; $found=[];
    foreach($rows as $row) {
        if (!is_array($row) || !isset($row['id'],$row['nome'],$row['uf']) || !is_int($row['id']) || $row['id']<=0 || !is_string($row['nome']) || trim($row['nome'])==='' || !is_string($row['uf']) || !preg_match('/^[A-Z]{2}$/',$row['uf'])) throw new InvalidArgumentException('Registro inválido');
        if (isset($ids[$row['id']])) throw new InvalidArgumentException('ID duplicado'); $ids[$row['id']]=true;
        if ($row['uf']===$uf && ($term==='' || strpos(normalizeSearch($row['nome']),normalizeSearch($term))!==false)) $found[]=$row;
    }
    usort($found,fn($a,$b)=>strcmp($a['nome'],$b['nome'])); return $found;
}
try {
    if (($argv[1]??'')==='--self-test') {
        $x=[['id'=>1,'nome'=>'Natal','uf'=>'RN'],['id'=>2,'nome'=>'Recife','uf'=>'PE']];
        if (count(searchCities($x,'nat','rn'))!==1 || searchCities($x,'nat','PE')!==[]) throw new RuntimeException('Teste falhou');
        try { searchCities([$x[0],$x[0]],'','RN'); throw new RuntimeException('Falha'); } catch(InvalidArgumentException $e) {}
        $acentos=[['id'=>3,'nome'=>'São Gonçalo do Amarante','uf'=>'RN'],['id'=>4,'nome'=>'Açu','uf'=>'RN']];
        if (count(searchCities($acentos,'sao goncalo','RN'))!==1 || count(searchCities($acentos,'AÇU','rn'))!==1 || count(searchCities($acentos,'acu','RN'))!==1) throw new RuntimeException('Busca com acentos falhou');
        echo "6 cenários OK\n"; exit(0);
    }
    if (count($argv)!==4) throw new InvalidArgumentException('Uso: php main.php catalogo.json UF TERMO');
    $text=file_get_contents($argv[1]); if($text===false) throw new RuntimeException('Falha de leitura');
    $data=json_decode($text,true,128,JSON_THROW_ON_ERROR);
    if (!is_array($data) || !isset($data['municipios'],$data['fonte'],$data['atualizado_em'])) throw new InvalidArgumentException('Catálogo sem metadados');
    echo json_encode(['fonte'=>$data['fonte'],'atualizado_em'=>$data['atualizado_em'],'resultados'=>searchCities($data['municipios'],$argv[3],$argv[2])],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
} catch(Throwable $e) { fwrite(STDERR,"Erro: {$e->getMessage()}\n"); exit(2); }
