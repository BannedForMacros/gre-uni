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
        <div class="row">
          <div class="col-md-12">
            <h5><i class="fa fa-list"></i> Guia de Ingreso Generadas
            <a href="{{ route('guiaingreso.create') }}" class="btn btn-primary btn-sm float-end"><i class="fa fa-plus"></i> Nueva</a></h5>

          </div>
        </div>

        <form name="form_busqueda" id="form_busqueda">
        <div class="row">
            @csrf
            <div class="col-md-3">
              <label class="form-label">Fecha Inicio</label>
              <input class="form-control fecha" data-tipo='inicio' type="date" name="fecha_inicio" id="fecha_inicio" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-3">
              <label class="form-label">Fecha Fin</label>
              <input class="form-control fecha" data-tipo='fin' type="date" name="fecha_fin" id="fecha_fin" value="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-1">
              <label class="form-label">Serie</label>
              <input type="text" class="form-control" name="serie" placeholder="Nro Serie">
            </div>
            <div class="col-md-2">
              <label class="form-label">Numero</label>
              <input type="text" class="form-control" name="numero" placeholder="Nro guia">
            </div>

            <div class="col-md-2">
              <button class="btn btn-success mt-3"><i class="fa fa-search"></i> Buscar</button>
            </div>

          </div>
        </form>

        <div class="row mt-4">
          <div class="col-md-12" id="resultados">

          </div>
        </div>
      </div>

    </div>
  </div>
  @push('js-scripts')
    <script src="{{ asset('js/guias/ingreso/index.js?v=') }}{{ rand() }}"></script>
  @endpush
@endsection
