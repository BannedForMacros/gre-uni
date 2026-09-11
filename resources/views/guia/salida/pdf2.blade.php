<html>
@inject('carbon', 'Carbon\Carbon')

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
                <td style="text-align: center; font-size: 22px">GUIA DE SALIDA</td>
              </tr>
              <tr>
                {{-- <td style="text-align: center; font-size: 14px; ">E L E C T R O N I C A</td> --}}
              </tr>
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
          @if ($documento->indicar_proveedor == 1)
            <td style="width: 36rem"><b>Razon Social:</b> {{ $documento->proveedor_nombre }}</td>
            <td><b>RUC:</b>{{ $documento->proveedor_ruc }}</td>
          @endif
          @if ($documento->indicar_proveedor == 0)
            <td style="width: 36rem"><b>Razon Social:</b> {{ $documento->cliente_razon_social }}</td>
            <td><b>{{ $documento->cliente_documento_tipo_nombre }}:</b>{{ $documento->cliente_nro_documento }}</td>
          @endif
        </tr>
        <tr>
          <td style="width: 36rem"><b>Fecha Emision:</b>
            {{-- Fecha y hora van en columnas separadas: fecha_emision y
                 hora_emision. Antes se leia fecha_hora_emision, que no existe,
                 y Carbon::parse(null) imprimia la fecha de HOY. --}}
            {{ $carbon::parse(trim($documento->fecha_emision . ' ' . $documento->hora_emision))->format('d/m/Y H:i:s') }}</td>
          <td><b>Direccion:</b> {{ $documento->cliente_direccion }}</td>
        </tr>
        {{-- <tr>
          <td style="width: 36rem"><b>Tipo Doc. Ref:</b> {{ $documentoReferencia->documento_tipo_nombre }}</td>
          <td ><b>Documento Ref:</b> {{ $documentoReferencia->serie }}-{{ $documentoReferencia->numero }}</td>
        </tr> --}}
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
        <th style="height: 1.8rem; width: 6rem">Barra</th>
        <th style="height: 1.8rem; width: 25rem">Descripcion</th>
        <th style="height: 1.8rem; width: 6rem">Monto</th>
      </thead>
      <tbody>
        @foreach ($detalle as $item)
          <tr style="text-align: center;" class="table_det">
            <td>{{ $item->cantidad }} UNI</td>
            <td>{{ $item->codarticulo }}</td>
            <td>{{ $item->codigo_barra }}</td>
            <td>{{ $item->descripcion }}</td>
            <td>{{ $item->importe }}</td>
          </tr>
        @endforeach

      </tbody>
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

    {{-- tabla de consulta y qr --}}

    <table style="width: 100%; font-size: 10px; margin-top: 10px; display: none">
      <tbody>
        <tr>
          <td style="width: 50rem;">

            <table style="width: 100%; border-spacing: 0" class="table_consulta_qr">
              <tbody>
                <tr>
                  <td>
                    <span>Consulte comprobante en (https://supermercadosmila/comprobante/32212)</span><br>
                    <span>Resumen: 1OzT6N9sCcEhSxmBxPt/KEeqRSI=</span><br>
                    <span>Representación Impresa de la BOLETA DE VENTA ELECTRÓNICA.</span><br>
                  </td>
                </tr>
              </tbody>
            </table>
          </td>
          <td style="width: 6rem"></td>
          <td style="20rem">
            <table>
              <tbody>
                <tr>
                  <td style="height: 10rem;">
                    {!! DNS2D::getBarcodeHTML('44456456564454555656', 'QRCODE', 4, 4) !!}
                  </td>
                </tr>
              </tbody>
            </table>
          </td>
        </tr>
      </tbody>
    </table>

  </main>
</body>

</html>
