@extends('layouts.app')

<style>
  body {
    background-color: #f5f7fa !important;
  }
</style>


@section('content')
  <div class="container-fluid">
    <div class="row justify-content-center">
      <div class="col-md-12">
        <h5>Guia de Remision Electronica</h5>

        <div class="row">
          <div class="col-md-6">
            <div class="row">
              <div class="col-md-3 mb-2">
                <label class="form-label">Fecha Emision</label>
                <input type="date" class="form-control" value="{{ date('Y-m-d') }}" readonly>
              </div>
              <div class="col-md-3 mb-2">
                <label class="form-label">Fecha Traslado</label>
                <input type="date" class="form-control" required>
              </div>
            </div>
            <div class="row">
              <div class="col-md-12 mb-2">
                <label class="form-label">Motivo Traslado</label>
                <select class="form-control form-select" name="motivo_traslado_id" id="motivo_traslado_id">
                  <option value="1">motivo 1</option>
                </select>
              </div>
              <div class="col-md-12 mb-2">
                <label class="form-label">Otro Motivo</label>
                <textarea class="form-control" name="motivo_traslado_otro" id="motivo_traslado_otro" rows="2"></textarea>
              </div>
            </div>

          </div>
          <div class="col-md-6">
            <div class="row">
              <div class="col-md-12 mb-2">
                <label class="form-label">Destinatario</label>
                <select class="form-control form-select" name="motivo_traslado_id" id="motivo_traslado_id">
                  <option value="1">motivo 1</option>
                </select>
              </div>
            </div>
            <div class="row">
              <div class="col-md-3 mb-2">
                <label class="form-label">Peso Total (KG)</label>
                <input type="text" class="form-control" name="peso_total" id="peso_total">
              </div>
              <div class="col-md-5 mb-2">
                <label class="form-label">Nro de Comprobante</label>
                <input type="text" class="form-control" name="nro_comprobante" id="nro_comprobante">
              </div>
              <div class="col-md-4 mb-2">
                <label class="form-label">Nº Orden de Compra</label>
                <input type="text" class="form-control" name="nro_comprobante" id="nro_comprobante">
              </div>
            </div>

            <div class="row">
              <div class="col-md-12 mb-2">
                <label class="form-label">Observaciones</label>
                <textarea class="form-control" name="observaciones" id="observaciones" rows="2"></textarea>
              </div>
            </div>

          </div>

        </div>

        <div class="row">
          <div class="col-md-6">
            <h5>Punto partida</h5>
            <div class="row">
              <div class="col-md-12">
                <label class="form-label">Ubigeo</label>
                <select class="form-select" name="ubigeo_partida" id="ubigeo_partida">
                  <option value="1">Ubigeo partida 1</option>
                </select>
              </div>
            </div>
            <div class="row">
              <div class="col-md-12">
                <label class="form-label">Direccion</label>
                <input type="text" class="form-control" name="direccion_partida" id="direccion_partida" required
                  placeholder="Indica la direccion de partida">
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <h5>Punto llegada</h5>
            <div class="row">
              <div class="col-md-12">
                <label class="form-label">Ubigeo</label>
                <select class="form-select" name="ubigeo_llegada" id="ubigeo_llegada">
                  <option value="1">Ubigeo llegada 1</option>
                </select>
              </div>
            </div>
            <div class="row">
              <div class="col-md-12">
                <label class="form-label">Direccion</label>
                <input type="text" class="form-control" name="direccion_llegada" id="direccion_llegada" required
                  placeholder="Indica la direccion de llegada">
              </div>
            </div>
          </div>
        </div>
        <div class="row">
          <h5>Transporte</h5>
          <div class="col-md-6">
            <label class="form-label">Modalidad</label>
            <select class="form-select" name="modalidad_id" id="modalidad_id">
              <option value="1">Transporte Publico</option>
              <option value="2">Transporte Privado</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Empresa Transporte</label>
            <select class="form-select" name="empresa_tramsporte_id" id="empresa_tramsporte_id">
              <option value="1">Empresa 1</option>
              <option value="2">Empresa 2</option>
            </select>
          </div>
        </div>
        <div class="row mt-2">
          <div class="col-md-6">
            <label class="form-label">Vehiculos</label>
            <div class="row">
              <div class="col-md-10">
                <select class="form-select form-select-sm" name="vehiculo_id" id="vehiculo_id">
                  <option value="1">Transporte Publico</option>
                  <option value="2">Transporte Privado</option>
                </select>
              </div>
              <div class="col-md-2">
                <button class="btn btn-success btn-sm"><i class="fa fa-plus"></i></button>
              </div>
            </div>
            <div class="row">
              <div class="col-md-12">
                <table class="table table-borderless table-striped table-sm fs-6">
                  <thead>
                    <th>Marca</th>
                    <th>Modelo</th>
                    <th>Placa</th>
                    <th>Accion</th>
                  </thead>
                  <tbody>
                    <tr>
                      <td>111</td>
                      <td>111</td>
                      <td>111</td>
                      <td>111</td>
                    </tr>
                    <tr>
                      <td>111</td>
                      <td>111</td>
                      <td>111</td>
                      <td>111</td>
                    </tr>
                  </tbody>
                </table>

              </div>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Conductores</label>
            <div class="row">
              <div class="col-md-10">
                <select class="form-select form-select-sm" name="vehiculo_id" id="vehiculo_id">
                  <option value="1">Conductor 1</option>
                  <option value="2">Conductor 2</option>
                </select>
              </div>
              <div class="col-md-2">
                <button class="btn btn-success btn-sm"><i class="fa fa-plus"></i></button>
              </div>
            </div>
            <div class="row">
              <div class="col-md-12">
                <table class="table table-borderless table-striped table-sm fs-6">
                  <thead>
                    <th>Doc Identidad</th>
                    <th>Conductor</th>
                    <th>Accion</th>
                  </thead>
                  <tbody>
                    <tr>
                      <td>111</td>
                      <td>111</td>
                      <td>111</td>
                    </tr>
                    <tr>
                      <td>111</td>
                      <td>111</td>
                      <td>111</td>
                    </tr>
                  </tbody>
                </table>

              </div>
            </div>
          </div>

        </div>
        <hr>
        <div class="row">
          <h5>Detalle</h5>
          <div class="row">
            <div class="col-md-12">
              <label class="form-label">Producto</label>
              <select class="form-select" name="producto_id" id="producto_id">
                <option value="1">Producto 1</option>
              </select>
            </div>
          </div>
          <div class="row">
            <div class="col-md-2">
              <label class="form-label">Cantidad</label>
              <input class="form-control form-control-sm" type="number" name="cantidad" id="cantidad">
            </div>
            <div class="col-md-2 mt-4">
              <button class="btn btn-success btn-sm"><i class="fa fa-plus"></i> Agregar</button>
            </div>
          </div>

          <div class="row mt-3">
            <div class="col-md-12">
              <table class="table table-borderless table-striped table-sm fs-6">
                <thead>
                  <th>Codigo</th>
                  <th>Descripcion</th>
                  <th>Cantidad</th>
                  <th>Accion</th>
                </thead>
                <tbody>
                  <tr>
                    <td>111</td>
                    <td>111</td>
                    <td>111</td>
                    <td>111</td>
                  </tr>
                  <tr>
                    <td>111</td>
                    <td>111</td>
                    <td>111</td>
                    <td>111</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="col-md-12">
            <br>
            <a type="button" href="#" class="btn btn-danger btn-sm float-start"><i class="fa fa-arrow-left"
                aria-hidden="true"></i>
              Cancelar</a>
            <button class="btn btn-primary btn-sm float-end" id="btn_guardar"><i class="fa fa-save"
                aria-hidden="true"></i>
              Guardar</button>
          </div>

        </div>
      </div>
    </div>
  </div>
@endsection
