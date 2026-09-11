<html>
@inject('carbon', 'Carbon\Carbon')
<title>Guia Ingreso</title>

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

    /* .tabla_comprobante {
      border-left: 0.01em solid black;
      border-right: 0;
      border-top: 0.01em solid black;
      border-bottom: 0;
      border-collapse: collapse;
      border-radius: 0px 0px 100px 100px;

    } */

    /* .tabla_comprobante td,
    .tabla_comprobante th {
      border-left: 0;
      border-right: 0.01em solid black;
      border-top: 0;
      border-bottom: 0.01em solid black;
    } */

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

    .table_leyenda td {
      height: 2rem;
      border-bottom-style: solid;
      border-bottom-width: 0.01em
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
  </style>
</head>

<body>

  <header>
    <div>
      {{-- <img src={{ url('img/cu/logoweb.png') }} class="logo floatLeft" width="220"> --}}

    </div>
  </header>
  <main style='font-size:10px;'>
    {{-- tabla de cabecera --}}
    <table style="margin-top: -6.5rem; width: 100%">
      <tr>
        <td style="text-align: center; width: 32rem;">
          {{-- El logo sale de Configuracion de Empresa, de cada cliente.

               Estaba fijo a img/logo.png, que en el proyecto anterior era
               correcto porque era de UN cliente. Aqui se instala en un centenar:
               con la ruta fija, cualquiera que no suba el suyo imprimiria sus
               guias con la marca de otra empresa. Si no hay logo configurado no
               se pinta nada, que es mejor que pintar el de un tercero. --}}
          @php($empresaLogo = \App\Support\Empresa::logoPath())
          @if($empresaLogo)
            <img src="{{ $empresaLogo }}" class="logo floatLeft" width="230">
          @endif
          <table class="table_rounded" style="width: 100%; height: 5rem; font-size: 10px">
            <tbody>
              <tr>
                <td style="text-align: center; font-size: 11px"><b>{{ Str::upper($cabecera->nombre_entidad) }}</b></td>
              </tr>
              <tr>
                <td><b>Direccion: </b>{{ $cabecera->direccion_entidad }}</td>
              </tr>
              <tr>
                <td><b>Telf: </b>{{ $cabecera->telefono_entidad }}</td>
              </tr>
            </tbody>
          </table>
        </td>
        <td></td>
        <td style="width: 30rem">
          <table class="table_rounded" style="width: 100%; height: 15rem">
            <tbody>
              <tr>
                <td></td>
              </tr>
              <tr>
                <td style="text-align: center; font-size: 22px">GUIA DE INGRESO</td>
              </tr>
              {{-- <tr>
                <td style="text-align: center; font-size: 14px; ">E L E C T R O N I C A</td>
              </tr> --}}
              <tr>
                <td><br></td>
              </tr>
              <tr>
                <td style="text-align: center;font-size: 12px">RUC: {{ $cabecera->ruc_entidad }}</td>
              </tr>
              <tr>
                <td><br></td>
              </tr>
              <tr>
                <td style="text-align: center; font-size: 22px">
                  {{ Str::upper($documento->serie) }}-{{ $documento->numero }} </td>
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
    <table class="table_rounded" style="width: 100%">
      <tbody>
        <tr>
          <td style="width: 36rem"><b>Razon Social:</b> {{ $documento->proveedor_nombre }}</td>
          <td><b>RUC:</b>{{ $documento->proveedor_ruc }}</td>
        </tr>
        <tr>
          <td style="width: 36rem"><b>Fecha Emision:</b>
            {{-- fecha_hora_emision NO existe: las columnas son fecha_emision y
                 hora_emision, por separado. Eloquent devuelve null para un
                 atributo desconocido y Carbon::parse(null) da la fecha de HOY,
                 asi que una guia de septiembre reimpresa en diciembre salia
                 fechada en diciembre. En un documento con valor legal. --}}
            {{ $carbon::parse(trim($documento->fecha_emision . ' ' . $documento->hora_emision))->format('d/m/Y H:i:s') }}</td>
          {{-- Apuntaba a $documento->cliente_direccion, que NO es una columna de
               guia_ingresos: Eloquent devolvia null y este hueco salia vacio en
               todas las guias de todos los clientes, desde siempre.

               La direccion del proveedor no se guarda en una guia de ingreso
               -el formulario no la pide-, asi que aqui no hay nada que pintar.
               Se deja el rotulo, que es parte del formato, y se documenta:
               para llenarlo hay que capturarla al registrar la guia, como ya
               se hace en guia de salida con proveedor_direccion. --}}
          <td><b>Direccion:</b> </td>
        </tr>
        <tr>
          <td style="width: 36rem"><b>Tipo Moneda:</b> {{ $guia->texto_moneda }}</td>
          <td><b>Tipo Operacion:</b> {{ $documento->tipo_operacion_nombre }}</td>
        </tr>
        <tr></tr>
      </tbody>
    </table>

    {{-- tabla de detalle items --}}
    <table class="table_rounded" style="width: 100%; margin-top: 10px; border-spacing: 0; font-size: 10px">
      <thead>
        <th style="height: 1.8rem; width: 6rem;">Cantidad</th>
        <th style="height: 1.8rem; width: 6rem">Codigo</th>
        <th style="height: 1.8rem; width: 25rem">Descripcion</th>
        <th style="height: 1.8rem; width: 6rem">Monto</th>
        @if ($valorada == 1)
          <th style="text-align: right; right: 0.8rem; width: 4rem" class="th_items">Costo</th>
          <th style="text-align: right; right: 0.8rem; width: 4rem" class="th_items">Total</th>
        @endif
      </thead>
      <tbody>
        @foreach ($detalle as $item)
          <tr style="text-align: center;" class="table_det">
            <td>{{ $item->cantidad }} {{ Str::upper($item->desc_unidad_medida) ?? 'UNI' }}</td>
            <td>{{ $item->codarticulo }}</td>
            <td>{{ $item->descripcion }}</td>
            <td>{{ $item->importe }}</td>
            @if ($valorada == 1)
              <td style="text-align: right">{{ $item->costo_articulo }}</td>
              <td style="text-align: right">{{ $item->costo_total }}</td>
            @endif
          </tr>
        @endforeach

      </tbody>

      @if ($valorada == 1)
        <tfoot >
          <tr class="table_det">
            <td colspan="5" style="text-align: right"><b>Valor Neto</b></td>
            <td style="text-align: right">{{ $guia->total_venta_gravada }}</td>
          </tr>
          <tr class="">
            <td colspan="5" style="text-align: right"><b>Exonerado</b></td>
            <td style="text-align: right">{{ $guia->monto_descuento }}</td>
          </tr>
          <tr class="">
            <td colspan="5" style="text-align: right"><b>I.G.V</b></td>
            <td style="text-align: right">{{ $guia->total_igv }}</td>
          </tr>
          <tr class="">
            <td colspan="5" style="text-align: right"><b>Total</b></td>
            <td style="text-align: right">{{ $guia->total }}</td>
          </tr>
        </tfoot>
      @endif

    </table>

    {{-- tabla de leyenda y subtotal --}}
    <table style="margin-top: 20px; font-size: 10px">
      <tbody>
        <tr>
          <td style="width: 33rem">
            <table style="border-spacing: 0;width: 100%; font-size: 10px">
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
                  {{-- <td>{{ $guia->texto_forma_pago }}</td> --}}
                  <td>Contado</td>
                </tr>
                <tr class="table_leyenda">
                  <td>VENDEDOR: </td>
                  <td>{{ $guia->nombre_cajero }}</td>
                </tr>
              </tbody>
            </table>

          </td>
          <td style="width: 14rem">

          </td>
          <td style="width: 16rem">
            <table style="border-spacing: 0;width: 100%; font-size: 10px">
              <tbody style="text-align: right">
                <tr>
                  <td><b>Op. Gravadas: </b></td>
                  <td class="td_subtotal" style="width: 6rem; padding-right: 1rem">{{ $guia->total_venta_gravada }}
                  </td>
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

    {{-- linea de separacion --}}
    <table style="width: 100%">
      <tbody>
        <tr>
          <td class="td_subtotal"></td>
        </tr>
      </tbody>
    </table>

    {{-- Aqui iba una tabla de consulta y un QR, dentro de un display:none.

         No se veia -ni se ve- pero se generaba igual: 221 divs y 23 de los
         33 KB del html que dompdf tiene que analizar, un tercio del tiempo de
         render, para no pintar nada. Si algun dia se necesita el QR, se
         recupera del historial: el codigo iba fijo a mano y la url apuntaba a
         un dominio de ejemplo. --}}
  </main>
</body>

</html>
