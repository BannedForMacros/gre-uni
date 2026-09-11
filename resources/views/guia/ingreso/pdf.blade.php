<html>
@inject('carbon', 'Carbon\Carbon')
<title>Guia Ingreso</title>

{{--
  Guia de Ingreso.

  Misma maquetacion que la guia de salida, para que los dos documentos de la
  empresa se lean igual: cabecera con el recuadro del RUC y el numero, secciones
  con titulo y linea, y la tabla de bienes.

  El titulo NO dice "Remision Remitente Electronica": una guia de ingreso es un
  documento interno de recepcion, no se envia a SUNAT, y ponerle ese nombre
  seria declarar algo que no es.

  Tampoco lleva los bloques de traslado -punto de partida y llegada,
  transportista- porque en un ingreso la mercaderia llega, no sale: esos datos
  no se capturan en esta pantalla.
--}}

<head style='font-size:12px;'>
  <style>
    @page {
      margin: 80px 25px;
      font-size: 11px;
    }

    header {
      position: fixed;
      top: -60px;
      left: 0px;
      right: 0px;
      height: 50px;
    }

    footer {
      position: fixed;
      bottom: -60px;
      left: 0px;
      right: 0px;
      height: 50px;
    }

    p:last-child {
      page-break-after: never;
    }

    body {
      margin-left: 10px;
      margin-top: 20px;
      margin-right: 20px;
      font-family: sans-serif;
    }

    .table_no_rounded {
      border-radius: 1px;
      border: 1px;
      border-color: rgb(88, 88, 88);
      border-style: solid
    }

    .table_det_bottom td {
      border-bottom: 0.01em solid black;
      height: 1.8rem;
    }

    .table_leyenda td {
      height: 2rem;
      border-bottom-style: solid;
      border-bottom-width: 0.01em
    }

    .table_titulo_cabecera {
      border-bottom: 0.12em solid black;
      height: 1.8rem;
    }

    .table_titulo_cabecera_top {
      border-top: 0.12em solid black;
      height: 1.8rem;
    }

    .td_subtotal {
      height: 2rem;
      border-bottom-style: solid;
      border-bottom-width: 0.01em
    }

    .th_items {
      background-color: rgba(211, 205, 205, 0.664)
    }
  </style>
</head>

<body>

  <header>
    <div>
    </div>
  </header>

  <main style='font-size:10px;'>

    {{-- Cabecera --}}
    <table style="margin-top: -6.5rem; width: 100%">
      <tr>
        <td style="text-align: left; width: 32rem;">
          {{-- El logo sale de Configuracion de Empresa, de cada cliente. Con una
               ruta fija, una instalacion que no suba el suyo imprimiria sus
               guias con la marca de otra empresa. --}}
          @php($empresaLogo = \App\Support\Empresa::logoPath())
          @if($empresaLogo)
            <img src="{{ $empresaLogo }}" class="logo floatLeft" width="230">
          @endif
          <table class="" style="width: 100%; height: 2rem; font-size: 10px; margin-top: -12px">
            <tbody>
              <tr>
                <td style="text-align: left; font-size: 11px"><b>{{ Str::upper($cabecera->nombre_entidad) }}</b></td>
              </tr>
              <tr>
                <td>{{ $cabecera->direccion_entidad }}</td>
              </tr>
              <tr>
                <td>{{ $cabecera->telefono_entidad }}</td>
              </tr>
            </tbody>
          </table>
        </td>
        <td></td>
        <td style="width: 32rem">
          <table class="table_no_rounded" style="width: 100%; height: 10rem">
            <tbody>
              <tr>
                <td><br></td>
              </tr>
              <tr>
                <td style="text-align: center;font-size: 13px">RUC: {{ $cabecera->ruc_entidad }}</td>
              </tr>
              <tr>
                <td style="text-align: center; font-size: 14px"><b>GUIA DE INGRESO</b></td>
              </tr>
              <tr>
                <td style="text-align: center; font-size: 14px">
                  Nº {{ Str::upper($documento->serie) }}-{{ $documento->numero }}</td>
              </tr>
              <tr>
                <td><br></td>
              </tr>
            </tbody>
          </table>
        </td>
      </tr>
    </table>

    {{-- Datos del documento --}}
    <table style="width: 100%;" class="table_titulo_cabecera">
      <tr>
        <td><b>Datos del documento</b></td>
      </tr>
    </table>

    <table class="" style="width: 100%; margin-top: 0.5rem">
      <tbody>
        <tr>
          <td style="width: 8rem"><b>Fecha Emision:</b></td>
          {{-- Fecha y hora van en columnas separadas. Antes se leia
               fecha_hora_emision, que no existe, y Carbon::parse(null)
               imprimia la fecha de HOY. --}}
          <td style="width: 10rem">{{ $carbon::parse(trim($documento->fecha_emision . ' ' . $documento->hora_emision))->format('d/m/Y H:i:s') }}</td>
          <td style="width: 7rem"><b>Tipo Operacion:</b></td>
          <td style="width: 16rem">{{ $documento->tipo_operacion_nombre }}</td>
        </tr>
        <tr>
          <td style="width: 8rem"><b>Almacen:</b></td>
          <td style="width: 10rem">{{ $documento->almacen_nombre }}</td>
          <td style="width: 7rem"><b>Forma de Pago:</b></td>
          <td style="width: 16rem">{{ $documento->forma_pago_nombre }}</td>
        </tr>
        <tr>
          <td style="width: 8rem"><b>Tipo Moneda:</b></td>
          <td style="width: 10rem">{{ $guia->texto_moneda }}</td>
          <td style="width: 7rem"><b>Doc. Relacionado:</b></td>
          <td style="width: 16rem">
            @if ($documento->pedido_serie || $documento->pedido_numero)
              {{ $documento->pedido_serie }}-{{ $documento->pedido_numero }}
            @endif
          </td>
        </tr>
      </tbody>
    </table>

    {{-- Proveedor --}}
    <table style="width: 100%;" class="table_titulo_cabecera_top">
      <tr>
        <td><b>Datos del proveedor</b></td>
      </tr>
    </table>

    <table class="" style="width: 100%; margin-top: -0.2rem">
      <tbody>
        <tr>
          <td style="width: 8rem"><b>Ruc:</b></td>
          <td style="width: 10rem">{{ $documento->proveedor_ruc }}</td>
          <td style="width: 7rem"><b>Razon social:</b></td>
          <td style="width: 16rem">{{ $documento->proveedor_nombre }}</td>
        </tr>
      </tbody>
    </table>

    {{-- Bienes --}}
    <table style="width: 100%;" class="table_titulo_cabecera_top">
      <tr>
        <td><b>Informacion de Bienes recibidos</b></td>
      </tr>
    </table>

    <table class="" style="width: 100%; margin-top: 10px; border-spacing: 0; font-size: 10px">
      <thead>
        <th style="text-align: left; height: 0.8rem; width: 3rem;" class="th_items">Item</th>
        <th style="text-align: left; height: 0.8rem; width: 6rem" class="th_items">Codigo Bien</th>
        <th style="text-align: left; height: 0.8rem; width: 28rem" class="th_items">Descripcion</th>
        <th style="text-align: right; height: 0.8rem; width: 5rem" class="th_items">Unidad</th>
        <th style="text-align: right; height: 0.8rem; width: 5rem" class="th_items">Cantidad</th>
        <th style="text-align: right; height: 0.8rem; width: 6rem" class="th_items">Monto</th>
        @if ($valorada == 1)
          <th style="text-align: right; height: 0.8rem; width: 5rem" class="th_items">Costo</th>
          <th style="text-align: right; height: 0.8rem; width: 5rem" class="th_items">Total</th>
        @endif
      </thead>
      <tbody>
        @foreach ($detalle as $item)
          <tr style="text-align: left;" class="table_det_bottom">
            <td>{{ $nro++ }}</td>
            <td>{{ $item->codarticulo }}</td>
            <td>{{ $item->descripcion }}@if ($item->codigo_barra) | {{ $item->codigo_barra }}@endif</td>
            <td style="text-align: right">{{ Str::upper($item->desc_unidad_medida) ?? 'UNI' }}</td>
            <td style="text-align: right">{{ $item->cantidad }}</td>
            <td style="text-align: right">{{ $item->importe }}</td>
            @if ($valorada == 1)
              <td style="text-align: right">{{ $item->costo_articulo }}</td>
              <td style="text-align: right">{{ $item->costo_total }}</td>
            @endif
          </tr>
        @endforeach
      </tbody>
    </table>

    {{-- Importe en letras, datos adicionales y totales --}}
    <table style="margin-top: 20px; width: 100%; font-size: 10px">
      <tbody>
        <tr>
          <td style="width: 33rem">
            <table style="border-spacing: 0; width: 100%; font-size: 10px">
              <tbody>
                <tr>
                  <td colspan="2"><b>SON {{ $guia->total_letras }}</b></td>
                </tr>
                <tr class="table_leyenda">
                  <td colspan="2"><b>Informacion Adicional</b></td>
                </tr>
                <tr class="table_leyenda">
                  <td style="width: 10rem">LEYENDA: </td>
                  <td></td>
                </tr>
                <tr class="table_leyenda">
                  <td>FORMA DE PAGO: </td>
                  <td>{{ $documento->forma_pago_nombre ?: 'Contado' }}</td>
                </tr>
                <tr class="table_leyenda">
                  <td>VENDEDOR: </td>
                  <td>{{ $guia->nombre_cajero }}</td>
                </tr>
              </tbody>
            </table>
          </td>
          <td style="width: 3rem"></td>
          <td style="width: 16rem">
            <table style="border-spacing: 0; width: 100%; font-size: 10px">
              <tbody style="text-align: right">
                <tr>
                  <td><b>Op. Gravadas: </b></td>
                  <td class="td_subtotal" style="width: 6rem; padding-right: 1rem">{{ $guia->total_venta_gravada }}</td>
                </tr>
                <tr>
                  <td><b>IGV: </b></td>
                  <td class="td_subtotal" style="width: 6rem; padding-right: 1rem">{{ $guia->total_igv }}</td>
                </tr>
                <tr>
                  <td><b>Precio Venta: </b></td>
                  <td class="td_subtotal" style="width: 6rem; padding-right: 1rem">{{ $guia->total }}</td>
                </tr>
              </tbody>
            </table>
          </td>
        </tr>
      </tbody>
    </table>

    {{-- Observaciones --}}
    <table style="width: 100%;" class="table_titulo_cabecera">
      <tr>
        <td><b>Observaciones</b></td>
      </tr>
    </table>

    <table class="" style="width: 100%; margin-top: -0.2rem">
      <tbody>
        <tr>
          <td style="width: 100%">{{ $documento->comentario }}</td>
        </tr>
      </tbody>
    </table>

  </main>
</body>

</html>
