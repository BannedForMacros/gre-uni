<?php
/**
 * Prueba de punta a punta de Guias Electronicas contra una instalacion real.
 *
 * Recorre el sistema como un usuario: login, pantallas, catalogos del DataMart
 * (Apache -> PHP -> ApiGRE -> SQL Server), agregar articulo, guardar una guia
 * de ingreso y una de salida, enviarlas al DataMart, PDF y listados.
 *
 * NUNCA emite a SUNAT: envio_sunat=0 siempre, no llama a facturacionElectronica
 * y aborta si cualquier ruta que va a pedir contiene "facturacion".
 *
 * CREA GUIAS DE VERDAD en la base local y en el DataMart (marcadas con
 * "PRUEBA E2E - NO VALIDA"). Solo para entornos de prueba: sin
 * --entorno-de-pruebas se niega a correr.
 *
 * Uso: php punta-a-punta.php --entorno-de-pruebas --base=http://127.0.0.1:8088 \
 *        --usuario=admin --clave-archivo=clave.txt [--resultado=r.json]
 */

$opt = getopt('', ['base:', 'usuario:', 'clave-archivo:', 'resultado:', 'entorno-de-pruebas']);
if (! array_key_exists('entorno-de-pruebas', $opt)) {
    fwrite(STDERR, "Esta prueba crea guias reales en la base y en el DataMart.\n"
        . "Solo se ejecuta en un entorno de pruebas: agregue --entorno-de-pruebas si lo es.\n");
    exit(2);
}
$base = rtrim($opt['base'] ?? 'http://127.0.0.1:8000', '/');
$usuario = $opt['usuario'] ?? 'admin';
$clave = trim((string) @file_get_contents($opt['clave-archivo'] ?? ''));
$jar = tempnam(sys_get_temp_dir(), 'e2e');
$csrf = null;
$fallos = 0;
$ctx = ['inicio' => date('c'), 'base' => $base, 'pasos' => []];
$hoy = date('Y-m-d');
const MARCA = 'PRUEBA E2E - NO VALIDA';

function http(string $metodo, string $ruta, ?array $datos = null, bool $ajax = false): array
{
    global $base, $jar, $csrf;
    if (stripos($ruta, 'facturacion') !== false) {
        throw new RuntimeException("BLOQUEADO: esta prueba nunca llama a {$ruta}");
    }
    $url = $base . $ruta;
    if ($metodo === 'GET' && $datos) {
        $url .= '?' . http_build_query($datos);
    }
    $h = ['Accept: ' . ($ajax ? 'application/json' : 'text/html')];
    if ($ajax) { $h[] = 'X-Requested-With: XMLHttpRequest'; }
    if ($csrf) { $h[] = 'X-CSRF-TOKEN: ' . $csrf; }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 180,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_HTTPHEADER => $h,
    ]);
    if ($metodo === 'POST') {
        if (getenv('E2E_DEBUG')) { fwrite(STDERR, "POST {$ruta} claves: " . implode(',', array_keys($datos ?? [])) . "\n"); }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($datos ?? []));
    }
    $cuerpo = (string) curl_exec($ch);
    $i = curl_getinfo($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) { throw new RuntimeException("sin respuesta de {$ruta}: {$err}"); }
    return ['status' => $i['http_code'], 'url' => $i['url'], 'cuerpo' => $cuerpo, 'ms' => (int) round($i['total_time'] * 1000)];
}

function paso(string $nombre, callable $fn): bool
{
    global $fallos, $ctx;
    try {
        $detalle = $fn();
        printf("OK     %-45s %s\n", $nombre, $detalle);
        $ctx['pasos'][] = ['paso' => $nombre, 'ok' => true, 'detalle' => $detalle];
        return true;
    } catch (Throwable $e) {
        $fallos++;
        printf("FALLO  %-45s %s\n", $nombre, $e->getMessage());
        $ctx['pasos'][] = ['paso' => $nombre, 'ok' => false, 'detalle' => $e->getMessage()];
        return false;
    }
}

function exigir($cond, string $msg): void { if (! $cond) { throw new RuntimeException($msg); } }

function json(array $r): array
{
    $j = json_decode($r['cuerpo'], true);
    exigir(is_array($j), "no es JSON (HTTP {$r['status']}): " . substr(trim(preg_replace('/\s+/', ' ', strip_tags($r['cuerpo']))), 0, 240));
    $j['_http'] = $r['status'];
    return $j;
}

function procede(array $j, string $que): void
{
    if (($j['procede'] ?? null) === true) { return; }
    $detalle = strip_tags((string) ($j['msj'] ?? $j['message'] ?? ''));
    if (! empty($j['errors'])) {
        $detalle .= ' errores=' . json_encode(array_map(function ($e) { return $e[0] ?? $e; }, $j['errors']), JSON_UNESCAPED_UNICODE);
    }
    if (! empty($j['log'])) { $detalle .= ' log=' . substr((string) $j['log'], 0, 300); }
    throw new RuntimeException("{$que}: HTTP {$j['_http']} procede=" . var_export($j['procede'] ?? null, true) . " {$detalle}");
}

function sinErrorPhp(array $r): void
{
    foreach (['Whoops', 'ErrorException', 'Undefined variable', 'Undefined index', 'Undefined offset', 'Stack trace', 'SQLSTATE', 'Server Error', 'Deprecated:'] as $m) {
        exigir(stripos($r['cuerpo'], $m) === false, "la pagina contiene '{$m}'");
    }
}

function opciones(string $html, string $name): array
{
    exigir(preg_match('/<select[^>]*name="' . preg_quote($name, '/') . '"[^>]*>(.*?)<\/select>/s', $html, $m), "no hay select {$name}");
    preg_match_all('/<option([^>]*?)\svalue="([^"]*)"([^>]*)>(.*?)<\/option>/s', $m[1], $o, PREG_SET_ORDER);
    $r = [];
    foreach ($o as $x) {
        if ($x[2] === '' || $x[2] === '-1') { continue; }
        $r[] = ['valor' => html_entity_decode($x[2]), 'texto' => trim(html_entity_decode(strip_tags($x[4]))), 'attrs' => $x[1] . ' ' . $x[3]];
    }
    return $r;
}

function attr(string $attrs, string $n): ?string
{
    return preg_match('/' . preg_quote($n, '/') . '="([^"]*)"/', $attrs, $m) ? html_entity_decode($m[1]) : null;
}

function primero(string $html, string $name, bool $obligatorio = true): ?array
{
    try { $o = opciones($html, $name); } catch (Throwable $e) { if ($obligatorio) { throw $e; } return null; }
    if (! $o && $obligatorio) { throw new RuntimeException("el select {$name} vino vacio (catalogo del DataMart)"); }
    return $o[0] ?? null;
}

function buscar(string $ruta, array $base, array $terminos = ['a', 'e', 'o', 'i', '1'], string $campoTermino = 'term'): array
{
    foreach ($terminos as $t) {
        $j = json(http('GET', $ruta, $base + [$campoTermino => $t], true));
        if (! empty($j['items'])) { return $j['items'][0]; }
    }
    throw new RuntimeException("{$ruta} no devolvio ningun resultado");
}

// ======================================================================
echo "Prueba de punta a punta contra {$base}\n\n";

paso('Login', function () use ($usuario, $clave) {
    $r = http('GET', '/login');
    exigir($r['status'] === 200, "HTTP {$r['status']}");
    exigir(preg_match('/name="_token" value="([^"]+)"/', $r['cuerpo'], $m), 'sin _token en /login');
    $r = http('POST', '/login', ['_token' => $m[1], 'username' => $usuario, 'password' => $clave]);
    exigir(strpos(parse_url($r['url'], PHP_URL_PATH) ?? '', '/login') === false, 'sigue en /login (credenciales o sesion)');
    return 'entra a ' . parse_url($r['url'], PHP_URL_PATH);
}) or exit(1);

foreach (['/guiaingreso', '/guiasalida', '/guiaingreso/create', '/guiasalida/create'] as $ruta) {
    paso("Pantalla {$ruta}", function () use ($ruta, &$ctx) {
        global $csrf;
        $r = http('GET', $ruta);
        exigir($r['status'] === 200, "HTTP {$r['status']}");
        sinErrorPhp($r);
        if (preg_match('/<meta name="csrf-token" content="([^"]+)"/', $r['cuerpo'], $m)) { $csrf = $m[1]; }
        $ctx['html'][$ruta] = $r['cuerpo'];
        return "{$r['ms']} ms";
    });
}

// ------------------------------------------------------------ INGRESO
$ok = paso('Ingreso: catalogos en la pantalla', function () use (&$ctx) {
    $h = $ctx['html']['/guiaingreso/create'] ?? '';
    exigir($h !== '', 'la pantalla no abrio');
    foreach (['serie', 'codalmacen', 'tipo_operacion_id', 'forma_pago_id', 'divisa_id'] as $n) { $ctx['ing'][$n] = primero($h, $n); }
    foreach (['vendedor_id', 'es_guia_interna', 'base_calculo'] as $n) { $ctx['ing'][$n] = primero($h, $n, false); }
    return "serie {$ctx['ing']['serie']['valor']}, almacen {$ctx['ing']['codalmacen']['texto']}, operacion {$ctx['ing']['tipo_operacion_id']['texto']}";
});

// La opcion por defecto del buscador es "Razon social": es la que usa el
// almacenero. Se verifica aparte y, pase lo que pase, el flujo sigue por RUC.
paso('Ingreso: buscar proveedor por razon social', function () {
    $it = buscar('/guiaingreso/listarProveedores', ['tipo' => 3], ['SAC', 'SA', 'EIRL', 'COMERCIAL']);
    return $it['text'];
});

$ok = $ok && paso('Ingreso: buscar proveedor por RUC (DataMart)', function () use (&$ctx) {
    $ctx['ing']['proveedor'] = buscar('/guiaingreso/listarProveedores', ['tipo' => 2], ['20', '10']);
    return $ctx['ing']['proveedor']['text'];
});

$ok = $ok && paso('Ingreso: buscar articulo (DataMart)', function () use (&$ctx) {
    $alm = $ctx['ing']['codalmacen'];
    $ctx['ing']['articulo'] = buscar('/guiaingreso/listarArticulos', [
        'tipo_busqueda_articulo' => 4, 'codalmacen' => $alm['valor'],
        'codestacion' => attr($alm['attrs'], 'data-codestacion') ?? '', 'codlistaprecio' => '',
    ]);
    return $ctx['ing']['articulo']['text'];
});

$ok = $ok && paso('Ingreso: agregar articulo al detalle', function () use (&$ctx) {
    $a = $ctx['ing']['articulo'];
    $j = json(http('POST', '/guiaingreso/agregarItem', [
        'producto_id' => $a['id'], 'descripcion' => $a['descripcion'], 'codigo_barra' => $a['codigo_barra'] ?? '',
        'precio_publico' => $a['precio_publico'], 'precio_sin_igv' => $a['precio_sin_igv'], 'costo_articulo' => $a['costo_articulo'],
        'peso' => $a['peso'], 'cod_unidad' => $a['cod_unidad'], 'desc_unidad_medida' => $a['desc_unidad_medida'],
        'sigla_umfe' => $a['sigla_umfe'], 'tipo_igv' => $a['tipo_igv'], 'items' => '[]',
    ], true));
    procede($j, 'agregarItem');
    $ctx['ing']['linea'] = $j['linea'];
    return "precio base {$j['linea']['precioSinIgv']}";
});

$ok = $ok && paso('Ingreso: guardar guia', function () use (&$ctx, $hoy) {
    $i = $ctx['ing']; $l = $i['linea']; $a = $i['articulo'];
    $precio = (float) $l['precioSinIgv'] ?: 1.0; $cant = 2;
    $base = round($precio * $cant, 2); $igv = round($base * 0.18, 2);
    $r = http('POST', '/guiaingreso', [
        // Guia interna: usa el correlativo de la serie, que es el caso normal.
        'es_guia_interna' => 1, 'serie' => $i['serie']['valor'], 'fecha_emision' => $hoy,
        'vendedor_id' => $i['vendedor_id']['valor'] ?? '', 'vendedor_nombre' => $i['vendedor_id']['texto'] ?? '',
        'proveedor_id' => $i['proveedor']['id'], 'proveedor_nombre' => $i['proveedor']['proveedor_nombre'], 'proveedor_ruc' => $i['proveedor']['proveedor_ruc'],
        'divisa_id' => $i['divisa_id']['valor'], 'divisa_nombre' => $i['divisa_id']['texto'],
        'forma_pago_id' => $i['forma_pago_id']['valor'], 'forma_pago_nombre' => $i['forma_pago_id']['texto'],
        'tipo_operacion_id' => $i['tipo_operacion_id']['valor'], 'tipo_operacion_nombre' => $i['tipo_operacion_id']['texto'],
        'codalmacen' => $i['codalmacen']['valor'], 'almacen_nombre' => $i['codalmacen']['texto'],
        'codestacion' => attr($i['codalmacen']['attrs'], 'data-codestacion') ?? '',
        'base_calculo' => 1, 'monto_descuento' => 0, 'importe_sin_igv' => $base, 'monto_igv' => $igv, 'total_venta' => $base + $igv,
        'comentario' => MARCA, 'es_consignado' => 0, 'guardar_avance' => 'false',
        'detalle' => json_encode([[
            'item' => 1, 'codarticulo' => $a['id'], 'codigo_barra' => $a['codigo_barra'] ?? '', 'descripcion' => $a['descripcion'],
            'cantidad' => $cant, 'precio' => $precio, 'importe' => $base, 'porcentaje_descuento' => 0, 'monto_descuento' => 0,
            'cod_unidad' => $a['cod_unidad'], 'desc_unidad_medida' => $a['desc_unidad_medida'], 'sigla_umfe' => $a['sigla_umfe'],
            'costo_articulo' => $a['costo_articulo'], 'precio_publico' => $a['precio_publico'], 'precio_sin_igv' => $a['precio_sin_igv'],
            'peso_unitario' => $a['peso'], 'tipo_igv' => $a['tipo_igv'], 'bonificacion' => 0, 'es_consignado' => 0,
        ]]),
    ], true);
    $j = json($r);
    procede($j, 'store');
    $ctx['ing']['id'] = $j['id'];
    return "id {$j['id']} ({$r['ms']} ms)";
});

$ok = $ok && paso('Ingreso: enviar al DataMart (SQL Server)', function () use (&$ctx) {
    $r = http('POST', '/guiaingreso/storeDataMart', ['id' => $ctx['ing']['id'], 'panel_origen' => 'create'], true);
    procede(json($r), 'storeDataMart');
    return "{$r['ms']} ms";
});

$ok && paso('Ingreso: PDF', function () use (&$ctx) {
    foreach ([0, 1] as $valorada) {
        $r = http('GET', "/guiaingreso/pdf/{$ctx['ing']['id']}/{$valorada}");
        exigir($r['status'] === 200 && strncmp($r['cuerpo'], '%PDF', 4) === 0, "valorada={$valorada}: no es un PDF (HTTP {$r['status']})");
    }
    return 'normal y valorada';
});

$ok && paso('Ingreso: aparece en el listado', function () use (&$ctx, $hoy) {
    $j = json(http('POST', '/guiaingreso/listar', ['fecha_inicio' => $hoy, 'fecha_fin' => $hoy], true));
    exigir(preg_match('/"id":\s*"?' . $ctx['ing']['id'] . '"?[,}]/', json_encode($j)), 'la guia no esta en el listado de hoy');
    return 'ok';
});

// ------------------------------------------------------------- SALIDA
$ok = paso('Salida: catalogos en la pantalla', function () use (&$ctx) {
    $h = $ctx['html']['/guiasalida/create'] ?? '';
    exigir($h !== '', 'la pantalla no abrio');
    foreach (['serie', 'codalmacen', 'tipo_operacion_id', 'forma_pago_id', 'divisa_id'] as $n) { $ctx['sal'][$n] = primero($h, $n); }
    foreach (['codlistaprecio', 'vendedor_id', 'base_calculo', 'motivo_traslado_id'] as $n) { $ctx['sal'][$n] = primero($h, $n, false); }
    return "serie {$ctx['sal']['serie']['valor']}, operacion {$ctx['sal']['tipo_operacion_id']['texto']}";
});

$ok = $ok && paso('Salida: buscar cliente (DataMart)', function () use (&$ctx) {
    $ctx['sal']['cliente'] = buscar('/guiasalida/listarClientes', ['tipo_busqueda_cliente' => 4], ['SAC', 'EIRL', 'SA']);
    return $ctx['sal']['cliente']['text'];
});

$ok = $ok && paso('Salida: buscar articulo (DataMart)', function () use (&$ctx) {
    $s = $ctx['sal'];
    $lp = $s['codlistaprecio'];
    $ctx['sal']['articulo'] = buscar('/guiasalida/listarArticulos', [
        'tipo_busqueda_articulo' => 4, 'codalmacen' => $s['codalmacen']['valor'],
        'codlistaprecio' => $lp['valor'] ?? '', 'codestacion' => $lp ? (attr($lp['attrs'], 'data-codestacion') ?? '') : '',
        'indicar_proveedor' => 0,
    ]);
    return $ctx['sal']['articulo']['text'];
});

$ok = $ok && paso('Salida: agregar articulo al detalle', function () use (&$ctx) {
    $a = $ctx['sal']['articulo'];
    $j = json(http('POST', '/guiasalida/agregarItem', [
        'producto_id' => $a['id'], 'descripcion' => $a['descripcion'], 'codigo_barra' => $a['codigo_barra'] ?? '',
        'precio_publico' => $a['precio_publico'], 'precio_sin_igv' => $a['precio_sin_igv'], 'costo_articulo' => $a['costo_articulo'],
        'peso' => $a['peso'], 'cod_unidad' => $a['cod_unidad'], 'desc_unidad_medida' => $a['desc_unidad_medida'],
        'sigla_umfe' => $a['sigla_umfe'], 'tipo_igv' => $a['tipo_igv'] ?? (($a['afecto'] ?? 1) ? 1 : 2), 'afecto' => $a['afecto'] ?? 1,
        'stock' => $a['stock'] ?? 0, 'items' => '[]',
    ], true));
    procede($j, 'agregarItem');
    $ctx['sal']['linea'] = $j['linea'];
    return "precio {$j['linea']['precioSinIgv']}";
});

$ok = $ok && paso('Salida: guardar guia (sin enviar a SUNAT)', function () use (&$ctx, $hoy) {
    $s = $ctx['sal']; $a = $s['articulo']; $c = $s['cliente'];
    $precioSinIgv = (float) ($a['precio_sin_igv'] ?: $a['precio_publico'] ?: 1); $precio = round($precioSinIgv * 1.18, 2);
    $cant = 1; $base = round($precioSinIgv * $cant, 2); $igv = round($base * 0.18, 2);
    $lp = $s['codlistaprecio'];
    $r = http('POST', '/guiasalida/store', [
        'serie' => $s['serie']['valor'], 'fecha_emision' => $hoy, 'fecha_inicio_traslado' => $hoy,
        'guardar_avance' => 'false', 'id_continua' => '', 'envio_sunat' => 0,
        'tipo_operacion_id' => $s['tipo_operacion_id']['valor'], 'tipo_operacion_nombre' => $s['tipo_operacion_id']['texto'],
        'motivo_traslado_id' => $s['motivo_traslado_id']['valor'] ?? (attr($s['tipo_operacion_id']['attrs'], 'data-codigo_motivo_traslado') ?? '01'),
        'descripcion_motivo_traslado' => $s['motivo_traslado_id']['texto'] ?? 'VENTA',
        'modalidad_traslado' => '02', 'vehiculo_placa' => 'E2E-001', 'vehiculo_marca' => 'PRUEBA',
        'chofer_dni' => '00000000', 'chofer_nombre' => 'CHOFER PRUEBA E2E', 'chofer_brevete' => 'Q00000000',
        'cliente_id' => $c['id'], 'cliente_razon_social' => $c['razon_social'], 'cliente_nro_documento' => $c['nro_documento'],
        'cliente_documento_tipo_nombre' => $c['documento_tipo_nombre'], 'cliente_direccion' => $c['direccion'],
        'codalmacen' => $s['codalmacen']['valor'], 'almacen_nombre' => $s['codalmacen']['texto'],
        'codlistaprecio' => $lp['valor'] ?? '', 'codestacion' => $lp ? (attr($lp['attrs'], 'data-codestacion') ?? '') : '',
        'forma_pago_id' => $s['forma_pago_id']['valor'], 'forma_pago_nombre' => $s['forma_pago_id']['texto'],
        'divisa_id' => $s['divisa_id']['valor'], 'divisa_nombre' => $s['divisa_id']['texto'],
        'vendedor_id' => $s['vendedor_id']['valor'] ?? '', 'vendedor_nombre' => $s['vendedor_id']['texto'] ?? '',
        'ubigeo_partida' => '150101', 'direccion_partida' => 'ALMACEN ' . MARCA,
        'ubigeo_llegada' => '150101', 'direccion_llegada' => $c['direccion'] ?: 'DESTINO ' . MARCA,
        'indicar_proveedor' => 0, 'peso_bruto_total' => (float) ($a['peso'] ?? 0) ?: 1,
        'monto_descuento' => 0, 'importe_sin_igv' => $base, 'monto_igv' => $igv, 'total_venta' => $base + $igv,
        'base_calculo' => $s['base_calculo']['valor'] ?? 0, 'comentario' => MARCA,
        'detalle' => json_encode([[
            'codarticulo' => $a['id'], 'descripcion' => $a['descripcion'], 'precio' => $precio, 'precio_publico' => $a['precio_publico'],
            'precio_sin_igv' => $precioSinIgv, 'cantidad' => $cant, 'importe' => round($precio * $cant, 2),
            'porcentaje_descuento' => 0, 'monto_descuento' => 0, 'peso' => $a['peso'], 'codigo_barra' => $a['codigo_barra'] ?? '',
            'cod_unidad' => $a['cod_unidad'], 'desc_unidad_medida' => $a['desc_unidad_medida'], 'sigla_umfe' => $a['sigla_umfe'],
            'costo_articulo' => $a['costo_articulo'],
        ]]),
    ], true);
    $j = json($r);
    procede($j, 'store');
    $ctx['sal']['id'] = $j['id'];
    return "id {$j['id']} ({$r['ms']} ms)";
});

$ok = $ok && paso('Salida: enviar al DataMart (SQL Server)', function () use (&$ctx) {
    $r = http('POST', '/guiasalida/storeDataMart', ['id' => $ctx['sal']['id'], 'panel_origen' => 'create'], true);
    procede(json($r), 'storeDataMart');
    return "{$r['ms']} ms";
});

$ok && paso('Salida: PDF', function () use (&$ctx) {
    foreach ([0, 1] as $valorada) {
        $r = http('GET', "/guiasalida/pdf/{$ctx['sal']['id']}/{$valorada}");
        exigir($r['status'] === 200 && strncmp($r['cuerpo'], '%PDF', 4) === 0, "valorada={$valorada}: no es un PDF (HTTP {$r['status']})");
    }
    return 'normal y valorada';
});

$ok && paso('Salida: aparece en el listado', function () use (&$ctx, $hoy) {
    $j = json(http('POST', '/guiasalida/listar', ['fecha_inicio' => $hoy, 'fecha_fin' => $hoy], true));
    exigir(preg_match('/"id":\s*"?' . $ctx['sal']['id'] . '"?[,}]/', json_encode($j)), 'la guia no esta en el listado de hoy');
    return 'ok';
});

// ------------------------------------------------------------ RESUMEN
unset($ctx['html']);
$ctx['fin'] = date('c');
$ctx['fallos'] = $fallos;
if (! empty($opt['resultado'])) { file_put_contents($opt['resultado'], json_encode($ctx, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); }
@unlink($jar);
echo $fallos ? "\nRESULTADO: {$fallos} paso(s) fallaron\n" : "\nRESULTADO: TODO OK\n";
exit($fallos ? 1 : 0);
