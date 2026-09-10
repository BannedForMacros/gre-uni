@extends('layouts.app')

@section('content')
{{--
  Listado de guías de salida.

  Mismo cambio que en Ingreso: el controller devolvía la tabla renderizada y el
  JavaScript la inyectaba con .html(). Ahora recibe datos.
--}}
<div class="gre"
     x-data="greListadoSalida({
        fechaInicio: '{{ date('Y-m-d') }}',
        fechaFin:    '{{ date('Y-m-d') }}',
        rutas: {
            listar:                 '{{ route('guiasalida.listar') }}',
            estadosSunat:           '{{ route('guiasalida.estadosSunat') }}',
            anular:                 '{{ route('guiasalida.anular') }}',
            storeDataMart:          '{{ route('guiasalida.storeDataMart') }}',
            facturacionElectronica: '{{ route('guiasalida.facturacionElectronica') }}'
        }
     })"
     x-cloak>

  <div class="container-fluid">
    <div class="row justify-content-center">
      <div class="col-md-12">

        <div class="gre-cabecera">
          <h1>Guías de Salida</h1>
          <span class="gre-cuenta" x-show="hayGuias" x-cloak>
            <span x-text="guiasFiltradas.length"></span>
          </span>
          <div class="gre-acciones">
            <a href="{{ route('guiasalida.create') }}" class="btn btn-primary btn-sm">
              <i class="fa fa-plus"></i> Nueva guía
            </a>
          </div>
        </div>

        <form @submit.prevent="cargar()" class="gre-filtros">
          <div class="row g-2 align-items-end">
            <div class="col-md-2">
              <label class="form-label" for="fecha_inicio">Fecha inicio</label>
              <input class="form-control" type="date" id="fecha_inicio"
                     x-model="filtros.fechaInicio" @change="alCambiarFecha('inicio')"
                     max="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-2">
              <label class="form-label" for="fecha_fin">Fecha fin</label>
              <input class="form-control" type="date" id="fecha_fin"
                     x-model="filtros.fechaFin" @change="alCambiarFecha('fin')">
            </div>
            <div class="col-md-1">
              <label class="form-label" for="serie">Serie</label>
              <input type="text" class="form-control" id="serie" placeholder="Serie"
                     x-model="filtros.serie">
            </div>
            <div class="col-md-2">
              <label class="form-label" for="numero">Número</label>
              <input type="text" class="form-control" id="numero" placeholder="N° guía"
                     x-model="filtros.numero">
            </div>
            <div class="col-md-2">
              <button type="submit" class="btn btn-secondary w-100" :disabled="cargando">
                <span x-show="!cargando"><i class="fa fa-search"></i> Buscar</span>
                <span x-show="cargando" x-cloak><i class="fa fa-circle-notch fa-spin"></i> Buscando…</span>
              </button>
            </div>
            <div class="col-md-3">
              <label class="form-label" for="filtro_rapido">Filtrar resultados</label>
              <div class="gre-busqueda">
                <i class="fa fa-search"></i>
                <input type="search" class="form-control" id="filtro_rapido"
                       placeholder="Filtrar por serie, cliente o estado"
                       x-model="filtroRapido" @input="pagina = 1">
                <button type="button" class="gre-busqueda-limpiar" x-show="filtroRapido" x-cloak
                        title="Limpiar" @click="filtroRapido = ''; pagina = 1">
                  <i class="fa fa-times"></i>
                </button>
              </div>
            </div>
          </div>
        </form>

        <div class="gre-error mt-3" x-show="error" x-cloak>
          <i class="fa fa-circle-exclamation"></i> <span x-text="error"></span>
        </div>

        {{-- El estado en SUNAT llega despues de la tabla; sin este aviso el
             usuario no sabe que una fila puede cambiar sola. --}}
        <div class="text-muted small mt-2" x-show="refrescandoEstados" x-cloak>
          <i class="fa fa-circle-notch fa-spin"></i> Consultando el estado en SUNAT…
        </div>

        <div class="row mt-3">
          <div class="col-md-12">
            <div class="gre-tabla">
              <table class="table table-hover table-sm gre-listado">
                <thead>
                  <tr>
                    <th class="gre-orden" @click="ordenarPor('numero')">Serie <span class="gre-orden-flecha" :class="{ activa: ordenActivo('numero') }" x-text="flecha('numero')"></span></th>
                    <th class="gre-orden" @click="ordenarPor('razonSocial')">Razón social <span class="gre-orden-flecha" :class="{ activa: ordenActivo('razonSocial') }" x-text="flecha('razonSocial')"></span></th>
                    <th class="gre-orden" @click="ordenarPor('fechaEmision')">F. Emisión <span class="gre-orden-flecha" :class="{ activa: ordenActivo('fechaEmision') }" x-text="flecha('fechaEmision')"></span></th>
                    <th class="gre-orden text-end" @click="ordenarPor('totalVenta')">Importe <span class="gre-orden-flecha" :class="{ activa: ordenActivo('totalVenta') }" x-text="flecha('totalVenta')"></span></th>
                    <th class="text-center">SUNAT</th>
                    <th class="gre-orden" @click="ordenarPor('estadoNombre')">Estado <span class="gre-orden-flecha" :class="{ activa: ordenActivo('estadoNombre') }" x-text="flecha('estadoNombre')"></span></th>
                    <th class="text-center">Acción</th>
                  </tr>
                </thead>
                <tbody>
                  <template x-for="g in guiasPagina" :key="g.id">
                    <tr>
                      <td class="gre-doc" x-text="g.documento"></td>
                      <td>
                        <span x-show="g.razonSocial" x-text="g.razonSocial"></span>
                        <span class="gre-sin-dato" x-show="!g.razonSocial" x-cloak>Sin registrar</span>
                      </td>
                      <td x-text="fecha(g.fechaEmision)"></td>
                      <td class="gre-importe" x-text="money(g.totalVenta)"></td>
                      <td class="align-middle text-center" x-text="g.envioSunat ? 'Sí' : 'No'"></td>
                      <td>
                        <span :class="claseEstado(g)" x-text="g.estadoNombre"></span>
                      </td>
                      <td>
                        <div class="gre-fila-acciones">
                          <a :href="g.urlPdf" target="_blank" class="gre-icono" title="Ver la guia en PDF">
                            <i class="fa fa-file-lines"></i>
                          </a>

                          <a :href="g.urlPdfValorada" target="_blank" class="gre-icono" title="Ver la guia valorada">
                            <i class="fa fa-file-invoice-dollar"></i>
                          </a>

                          <a :href="g.urlContinuar" class="gre-icono" title="Continuar esta guia"
                             x-show="g.mostrarContinuar" x-cloak>
                            <i class="fa fa-pen"></i>
                          </a>

                          <button type="button" class="gre-icono gre-icono-accion" title="Reenviar al DataMart"
                                  x-show="g.mostrarGuardarDatamarket" x-cloak @click="reenviarDataMart(g)">
                            <i class="fa fa-paper-plane"></i>
                          </button>

                          <button type="button" class="gre-icono gre-icono-accion" title="Reenviar al facturador"
                                  x-show="g.verReintentoFacturador" x-cloak @click="reenviarFacturador(g)">
                            <i class="fa fa-rotate-right"></i>
                          </button>

                          <button type="button" class="gre-icono gre-icono-peligro" title="Anular esta guia"
                                  x-show="g.mostrarAnular" x-cloak @click="anular(g)">
                            <i class="fa fa-ban"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                  </template>

                  {{-- Mientras llegan los datos la tabla se quedaba en blanco y
                       parecia colgada. El esqueleto ocupa el sitio de las filas. --}}
                  <template x-if="cargando && !hayGuias">
                    <template x-for="n in 5" :key="n">
                      <tr class="gre-esqueleto">
                        <td><span></span></td>
                        <td><span></span></td>
                        <td><span></span></td>
                        <td><span></span></td>
                        <td><span></span></td>
                        <td><span></span></td>
                        <td><span></span></td>
                      </tr>
                    </template>
                  </template>

                  <tr x-show="!hayGuias && !cargando">
                    <td colspan="7" class="gre-vacio">
                      <i class="fa fa-inbox"></i>
                      <strong>No hay guías en este rango</strong>
                      <span>Ajuste las fechas o cree una guía nueva.</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div class="gre-paginacion" x-show="hayGuias">
              <span class="gre-paginacion-info">
                <span x-text="guiasFiltradas.length"></span>
                <span x-text="guiasFiltradas.length === 1 ? 'guía' : 'guías'"></span>
                · total <strong x-text="money(totalImporte)"></strong>
              </span>

              <div class="btn-group btn-group-sm" x-show="totalPaginas > 1">
                <button type="button" class="btn btn-outline-secondary"
                        :disabled="pagina === 1" @click="irA(pagina - 1)">Anterior</button>
                <span class="btn btn-outline-secondary disabled">
                  <span x-text="pagina"></span> / <span x-text="totalPaginas"></span>
                </span>
                <button type="button" class="btn btn-outline-secondary"
                        :disabled="pagina === totalPaginas" @click="irA(pagina + 1)">Siguiente</button>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
@endsection

@push('js-scripts')
  <script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
  <script src="{{ asset('js/gre/http.js?v=') }}{{ rand() }}"></script>
  <script src="{{ asset('js/gre/guia-listado.js?v=') }}{{ rand() }}"></script>
@endpush
