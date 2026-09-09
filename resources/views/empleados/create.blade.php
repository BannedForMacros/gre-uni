@extends('layouts.app')


@section('content')
  <div class="container-fluid">
    <div class="row justify-content-center">
      <div class="col-md-12">
        <h3>Registrar Empleado</h3>
        <form name="form_store" id="form_store" action="{{ route('empleados.store')}}" method="POST"  onkeydown="return event.key != 'Enter';">
          @csrf
          
          <div class="row">
            <div class="col-md-6">
              <label class="form-label">Empleado</label>
              <select class="form-select select_2" name="empleado_dmk_id" id="empleado_dmk_id" autofocus style="width: 100%">
                <option value="-1">Seleccionar un trabajador</option>
                @foreach ($listTrabajadores as $item)
                  <option value="{{ $item->codTrabajador }}">{{ "{$item->apellidos} {$item->nombres}" }}</option>
                @endforeach
              </select>
            </div>

          </div>

          <div class="row mt-2">
            <div class="col-md-3">
              <label class="form-label">Nro Documento</label>
              <input type="text" class="form-control" name="nro_documento" id="nro_documento" placeholder="Nº Documento" required readonly>
            </div>
            <div class="col-md-3">
              <label class="form-label">Apellido Paterno</label>
              <input type="text" class="form-control" name="ape_paterno" id="ape_paterno" placeholder="Apellido Paterno" required readonly>
            </div>
            <div class="col-md-3">
              <label class="form-label">Apellido Materno</label>
              <input type="text" class="form-control" name="ape_materno" id="ape_materno" placeholder="Apellido Materno" required readonly>
            </div>
            <div class="col-md-3">
              <label class="form-label">Nombre</label>
              <input type="text" class="form-control" name="nombres" id="nombres" placeholder="Nombres" required readonly>
            </div>
          </div>
          <div class="row mt-2">
            <div class="col-md-3" hidden>
              <label class="form-label">Fecha Nacimiento</label>
              <input type="date" class="form-control" name="fecha_nacimiento" placeholder="Nº Documento" required value="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-3" hidden>
              <label class="form-label">Sexo</label>
              <select class="form-select" name="sexo">
                <option value="1">Masculino</option>
                <option value="2">Femenino</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Usuario</label>
              <input type="text" class="form-control" name="usuario" id="usuario" placeholder="Usuario" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Password</label>
              <input type="text" class="form-control" name="password" id="password" placeholder="Password" required>
            </div>
          </div>
          <div class="row mt-2">
            <div class="col-md-3">
              <label class="form-label">Perfil</label>
              <select class="form-select" name="perfil_id">
                <option value="1">Administrador</option>
                <option value="2">Operario</option>
              </select>
            </div>

          </div>
          <div class="row mt-3">
            <div class="col-md-12">
              <a class="btn btn-danger float-start" href="{{ route('empleados.index') }}"><i class="fa fa-arrow-left"></i> Volver</a>
              <button type="submit" class="btn btn-primary btn-primary float-end"><i class="fa fa-save"></i> Guardar</button>

            </div>
          </div>


        </form>
      </div>
    </div>
  </div>
  @push('js-scripts')
  <script src="{{ asset('js/empleados/create.js?v=') }}{{ rand() }}"></script>
  {{-- <script src="{{ asset('js/guias/ingreso/articulo.js?v=') }}{{ rand() }}"></script> --}}

@endpush
@endsection