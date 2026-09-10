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
            anular:                 '{{ route('guiasalida.anular') }}',
            storeDataMart:          '{{ route('guiasalida.storeDataMart') }}',
            facturacionElectronica: '{{ route('guiasalida.facturacionElectronica') }}'
        }
     })"
     x-cloak>

  <div class="container-fluid">
    <div class="row justify-content-center">
      <div class="col-md-12">

        <div class="row">
          <div class="col-md-12">
            <h5>
              <i class="fa fa-list"></i> Guías de Salida
              <a href="{{ route('guiasalida.create') }}" class="btn btn-primary btn-sm float-end">
                <i class="fa fa-plus"></i> Nueva
              </a>
            </h5>
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
              <button type="submit" class="btn btn-success w-100" :disabled="cargando">
                <span x-show="!cargando"><i class="fa fa-search"></i> Buscar</span>
                <span x-show="cargando" x-cloak><i class="fa fa-circle-notch fa-spin"></i> Buscando…</span>
              </button>
            </div>
            <div class="col-md-3">
              <label class="form-label" for="filtro_rapido">Filtrar resultados</label>
              <input type="search" class="form-control" id="filtro_rapido"
                     placeholder="Serie, razón social o estado"
                     x-model="filtroRapido" @input="pagina = 1">
            </div>
          </div>
        </form>

        <div class="gre-error mt-3" x-show="error" x-cloak>
          <i class="fa fa-circle-exclamation"></i> <span x-text="error"></span>
        </div>

        <div class="row mt-3">
          <div class="col-md-12">
            <div class="gre-scroll">
              <table class="table table-hover table-sm table-bordered gre-detalle">
                <thead>
                  <tr>
                    <th class="gre-orden" @click="ordenarPor('numero')">Serie <span x-text="flecha('numero')"></span></th>
                    <th class="gre-orden" @click="ordenarPor('razonSocial')">Razón social <span x-text="flecha('razonSocial')"></span></th>
                    <th class="gre-orden" @click="ordenarPor('fechaEmision')">F. Emisión <span x-text="flecha('fechaEmision')"></span></th>
                    <th class="gre-orden text-end" @click="ordenarPor('totalVenta')">Importe <span x-text="flecha('totalVenta')"></span></th>
                    <th class="text-center">SUNAT</th>
                    <th class="gre-orden" @click="ordenarPor('estadoNombre')">Estado <span x-text="flecha('estadoNombre')"></span></th>
                    <th class="text-center">Acción</th>
                  </tr>
                </thead>
                <tbody>
                  <template x-for="g in guiasPagina" :key="g.id">
                    <tr>
                      <td class="align-middle" x-text="g.documento"></td>
                      <td class="align-middle" x-text="g.razonSocial"></td>
                      <td class="align-middle" x-text="fecha(g.fechaEmision)"></td>
                      <td class="align-middle gre-num" x-text="money(g.totalVenta)"></td>
                      <td class="align-middle text-center" x-text="g.envioSunat ? 'Sí' : 'No'"></td>
                      <td class="align-middle" x-text="g.estadoNombre"></td>
                      <td class="align-middle text-center">
                        <div class="btn-group btn-group-sm">
                          <a :href="g.urlPdf" target="_blank" class="btn btn-sm btn-primary">
                            <i class="fa fa-external-link"></i> Ver
                          </a>
                          <button type="button" class="btn btn-dark dropdown-toggle dropdown-toggle-split"
                                  data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="visually-hidden">Más acciones</span>
                          </button>
                          <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                              <a class="dropdown-item text-success" :href="g.urlPdfValorada" target="_blank">
                                <i class="fa fa-file"></i> Guía valorada
                              </a>
                            </li>
                            <li x-show="g.mostrarContinuar">
                              <a class="dropdown-item" :href="g.urlContinuar"><i class="fa fa-edit"></i> Continuar</a>
                            </li>
                            <li x-show="g.mostrarGuardarDatamarket">
                              <button type="button" class="dropdown-item text-success" @click="reenviarDataMart(g)">
                                <i class="fa fa-paper-plane"></i> Reenviar a DataMart
                              </button>
                            </li>
                            <li x-show="g.verReintentoFacturador">
                              <button type="button" class="dropdown-item text-primary" @click="reenviarFacturador(g)">
                                <i class="fa fa-paper-plane"></i> Reenviar al facturador
                              </button>
                            </li>
                            <li x-show="g.mostrarAnular"><hr class="dropdown-divider"></li>
                            <li x-show="g.mostrarAnular">
                              <button type="button" class="dropdown-item text-danger" @click="anular(g)">
                                <i class="fa fa-times"></i> Anular
                              </button>
                            </li>
                          </ul>
                        </div>
                      </td>
                    </tr>
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
