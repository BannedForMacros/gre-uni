<html>
@inject('carbon', 'Carbon\Carbon')
<title>Guia Salida</title>

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


    .logo {
      /* width: 150px;
      height: 120px; */
      /* content: url(logo.jpg); */
    }

    .table_rounded {
      border-radius: 10px;
      border: 1px;
      border-color: rgb(88, 88, 88);
      border-style: solid
    }

    .table_no_rounded {
      border-radius: 1px;
      border: 1px;
      border-color: rgb(88, 88, 88);
      border-style: solid
    }

    .div_text {
      border-collapse: collapse;
      border-bottom: 1px solid;
      display: inline-block;
      font-size: 1.2rem;
    }

    .div_label {
      border-collapse: collapse;
      display: inline-block;
      font-size: 1.2rem;
    }

    .div_row {
      margin-top: 35px
    }

    .div_inline {
      display: inline;
    }

    .table_det td {
      border-top: 0.01em solid black;
      height: 1.8rem;
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

    .table_consulta_qr td {
      height: 4rem;
      padding-left: 2rem;
      border-style: solid;
      border-width: 0.01em
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
    {{-- tabla de cabecera --}}
    <table style="margin-top: -6.5rem; width: 100%">
      <tr>
        <td style="text-align: left; width: 32rem;">
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
            </tbody>
          </table>
        </td>
        <td></td>
        <td style="width: 32rem">
          <table class="table_no_rounded" style="width: 100%; height: 10rem">
            <tbody>
              <br>
              <tr>
                <td style="text-align: center;font-size: 13px">RUC: {{ $cabecera->ruc_entidad }}</td>
              </tr>
              <tr>
                <td style="text-align: center; font-size: 14px"><b>GUIA DE REMISION REMITENTE ELECTRONICA</b></td>
              </tr>

              <tr>
                <td style="text-align: center; font-size: 14px">
                  Nº T{{ Str::upper($documento->serie) }}-{{ $documento->numero }} </td>
              </tr>
              <tr>
                <td><br></td>
              </tr>
            </tbody>
          </table>
        </td>
      </tr>
    </table>

    {{-- tabla de datos de persona --}}
    <table style="width: 100%;" class="table_titulo_cabecera">
      <tr>
        <td><b>Datos de inicio de traslado</b></td>
      </tr>
    </table>
    <table class="" style="width: 100%; margin-top: 0.5rem">
      <tbody>
        <tr>
          <td style="width: 8rem"><b>Fecha Emision:</b></td>
          {{-- fecha_hora_emision NO existe: las columnas son fecha_emision y
               hora_emision, por separado. Eloquent devuelve null para un
               atributo que no existe y Carbon::parse(null) da la fecha de HOY,
               asi que el PDF de una guia del 3 de septiembre se imprimia con la
               fecha del dia en que se abria. En un documento con valor legal. --}}
          <td style="width: 8rem">{{ $carbon::parse($documento->fecha_emision)->format('Y-m-d') }}</td>
          <td style="width: 6rem"><b>Motivo de traslado: </b></td>
          <td style="width: 18rem">{{ $documento->descripcion_motivo_traslado }}</td>
          <td style="width: 5rem"> <b>Peso Bruto</b></td>
          <td style="width: 5rem">{{ $documento->peso_total }} KG</td>
        </tr>
        <tr>
          <td style="width: 8rem"><b>Fecha Inicio:</b></td>
          {{-- La fecha de inicio de traslado es la que declara la guia. Si no
               se indico, vale la de emision, como hacia el proyecto anterior. --}}
          <td>{{ $carbon::parse($documento->fecha_inicio_traslado ?? $documento->fecha_emision)->format('Y-m-d') }}</td>
          <td style="width: 6rem"><b>Modalidad transporte:</td>
          <td></b> {{ $documento->texto_modalidad_traslado }}</td>
          <td></td>
        </tr>
        <tr>
          <td style="width: 8rem"><b>Tipo Moneda:</b> {{ $guia->texto_moneda }}</td>
          <td><b>Doc. Relacionado:</b> </td>
          <td></td>
          <td></td>
        </tr>
        <tr></tr>
      </tbody>
    </table>

    <table style="width: 100%;" class="table_titulo_cabecera_top">
      <tr>
        <td><b>Datos destinatario</b></td>
      </tr>
    </table>

    <table class="" style="width: 100%; margin-top: -0.2rem">
      <tbody>
        @if ($documento->indicar_proveedor == 0)
          <tr>
            <td style="width: 8rem"><b>Ruc:</b></td>
            <td style="width: 8rem">{{ $documento->cliente_nro_documento }}</td>
            <td style="width: 6rem"><b>Razon social: </b></td>
            <td style="width: 18rem">{{ $documento->cliente_razon_social }}</td>
          </tr>
        @else
          <tr>
            <td style="width: 8rem"><b>Ruc:</b></td>
            <td style="width: 8rem">{{ $documento->proveedor_ruc }}</td>
            <td style="width: 6rem"><b>Razon social: </b></td>
            <td style="width: 18rem">{{ $documento->proveedor_nombre }}</td>
          </tr>
        @endif
      </tbody>
    </table>

    <table style="width: 100%;" class="table_titulo_cabecera_top">
      <tr>
        <td><b>Datos del punto de partida y punto de llegada</b></td>
      </tr>
    </table>

    <table class="" style="width: 100%; margin-top: -0.2rem">
      <tbody>
        <tr>
          <td style="width: 12rem"><b>Direccion del punto de partida:</b></td>
          <td style="width: 20rem">{{ $documento->direccion_partida }}</td>
          <td style="width: 8rem"><b>Ubigeo Partida:</b></td>
          <td style="width: 12rem">{{ $documento->ubigeo_partida }}</td>
        </tr>
        <tr>
          <td style="width: 12rem"><b>Direccion del punto de llegada:</b></td>
          <td style="width: 20rem">{{ $documento->direccion_llegada }}</td>
          <td style="width: 8rem"><b>Ubigeo Llegada:</b></td>
          <td style="width: 12rem">{{ $documento->ubigeo_llegada }}</td>
        </tr>
      </tbody>
    </table>

    <table style="width: 100%;" class="table_titulo_cabecera_top">
      <tr>
        <td><b>Datos del transportista</b></td>
      </tr>
    </table>

    <table class="" style="width: 100%; margin-top: -0.2rem">
      <tbody>
        <tr>
          <td style="width: 6rem"><b>DNI:</b></td>
          <td style="width: 12rem">{{ $documento->chofer_dni }}</td>
          <td style="width: 8rem"><b>Conductor:</b></td>
          <td style="width: 12rem">{{ $documento->chofer_nombre }}</td>
        </tr>
        <tr>
          <td style="width: 6rem"><b>Licencia:</b></td>
          <td style="width: 12rem">{{ $documento->chofer_brevete }}</td>
          <td style="width: 8rem"><b>placa Vehiculo:</b></td>
          <td style="width: 12rem">{{ $documento->vehiculo_placa }}</td>
        </tr>
      </tbody>
    </table>

    <table style="width: 100%;" class="table_titulo_cabecera_top">
      <tr>
        <td><b>Informacion de Bienes trasladados</b></td>
      </tr>
    </table>


    {{-- tabla de detalle items --}}
    <table class="" style="width: 100%; margin-top: 10px; border-spacing: 0; font-size: 10px">
      <thead>
        <th style="text-align: left; height: 0.8rem; width: 6rem;" class="th_items">Item</th>
        <th style="text-align: left; height: 0.8rem; width: 6rem" class="th_items">Codigo Bien</th>
        <th style="text-align: left; height: 0.8rem; width: 30rem" class="th_items">Descripcion</th>
        <th style="text-align: right; right: 0.8rem; width: 6rem" class="th_items">Unidad</th>
        <th style="text-align: right; right: 0.8rem; width: 6rem" class="th_items">Cantidad</th>
        @if ($valorada == 1)
          <th style="text-align: right; right: 0.8rem; width: 4rem" class="th_items">Costo</th>
          <th style="text-align: right; right: 0.8rem; width: 4rem" class="th_items">Total</th>
        @endif
      </thead>
      <tbody>
        @foreach ($detalle as $item)
          <tr style="text-align: left;" class="table_det_bottom">
            <td>{{ $nro++ }}</td>
            <td>{{ $item->codarticulo }}</td>
            <td>{{ $item->descripcion }} | {{ $item->codigo_barra }}</td>
            <td style="text-align: right">{{ Str::upper($item->desc_unidad_medida) ?? 'UNI' }}</td>
            <td style="text-align: right">{{ $item->cantidad }}</td>
            @if ($valorada == 1)
              {{-- <td style="text-align: right">{{ $item->precio_publico }}</td> --}}
              @if ($documento->base_calculo == 1)
                <td style="text-align: right">{{ $item->precio_sin_igv }}</td>
                <td style="text-align: right">{{ $item->importe }}</td>
              @else
                <td style="text-align: right">{{ $item->precio_publico }}</td>
                <td style="text-align: right">{{ $item->importe }}</td>
              @endif
            @endif
          </tr>
        @endforeach

      </tbody>
      @if ($valorada == 1)
        <tfoot>
          <tr>
            <td colspan="6" style="text-align: right"><b>SubTotal</b></td>
            <td style="text-align: right">{{ $guia->total_venta_gravada }}</td>
          </tr>
          {{-- <tr>
            <td colspan="6" style="text-align: right"><b>Exonerado</b></td>
            <td style="text-align: right">{{ $guia->monto_descuento }}</td>
          </tr> --}}
          <tr>
            <td colspan="6" style="text-align: right"><b>I.G.V</b></td>
            <td style="text-align: right">{{ $guia->total_igv }}</td>
          </tr>
          <tr>
            <td colspan="6" style="text-align: right"><b>Total</b></td>
            <td style="text-align: right">{{ $guia->total }}</td>
          </tr>
        </tfoot>
      @endif
    </table>

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
