@extends('layouts.app')


@section('content')
  <div class="container-fluid">
    <div class="row justify-content-center">
      <div class="col-md-12">
        <h3>Registrar Empleado</h3>
        <form name="form_update" id="form_update"  onkeydown="return event.key != 'Enter';">
          @csrf
          <input type="text" id="id" name="id" value="{{ $empleado->id }}" hidden>

          <div class="row">
            <div class="col-md-6">
              <label class="form-label">Empleado</label>
              <select class="form-select" name="empleado_dmk_id" id="empleado_dmk_id" autofocus style="width: 100%">
                @foreach ($listTrabajadores as $item)
                  <option value="{{ $item->codTrabajador }}">{{ "{$item->apellidos} {$item->nombres}" }}</option>
                @endforeach
              </select>
            </div>

          </div>


          <div class="row">
            <div class="col-md-3">
              <label class="form-label">Nro Documento</label>
              <input type="text" class="form-control" name="nro_documento" id="nro_documento" value="{{ $empleado->nro_documento }}" placeholder="Nº Documento" required  readonly >
            </div>
            <div class="col-md-3">
              <label class="form-label">Apellido Paterno</label>
              <input type="text" class="form-control" name="ape_paterno" value="{{ $empleado->ape_paterno }}" placeholder="Apellido Paterno"  readonly>
            </div>
            <div class="col-md-3">
              <label class="form-label">Apellido Materno</label>
              <input type="text" class="form-control" name="ape_materno" value="{{ $empleado->ape_materno }}" placeholder="Apellido Materno"  readonly>
            </div>
            <div class="col-md-3">
              <label class="form-label">Nombre</label>
              <input type="text" class="form-control" name="nombres" value="{{ $empleado->nombres }}" placeholder="Nombres" required readonly>
            </div>
          </div>
          <div class="row mt-2">
            <div class="col-md-3">
              <label class="form-label">Fecha Nacimiento</label>
              <input type="date" class="form-control" name="fecha_nacimiento" value="{{ $empleado->fecha_nacimiento }}" placeholder="Nº Documento">
            </div>
            <div class="col-md-3">
              <label class="form-label">Sexo</label>
              <select class="form-select" name="sexo">
                <option value="1" {{ ($empleado->sexo == 1) ? 'selected' : '' ; }}>Masculino</option>
                <option value="2" {{ ($empleado->sexo == 2) ? 'selected' : '' ; }}>Femenino</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Usuario</label>
              <input type="text" class="form-control" name="usuario" id="usuario" value="{{ $user->username }}" placeholder="Usuario" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Password</label>
              <input type="text" class="form-control" name="password" id="password" value="{{ base64_decode($user->password_alt) }}" placeholder="Password" required>
            </div>
          </div>
          <div class="row mt-2">
            <div class="col-md-3">
              <label class="form-label">Perfil</label>
              <select class="form-select" name="perfil_id">
                @foreach ($listPerfiles as $item)
                  <option value="{{ $item->id }}" {{ ($item->id == $user->perfil_id) ? 'selected' : '' ; }}>{{ $item->nombre }}</option>
                @endforeach
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
  <script src="{{ asset('js/empleados/edit.js?v=') }}{{ rand() }}"></script>
  {{-- <script src="{{ asset('js/guias/ingreso/articulo.js?v=') }}{{ rand() }}"></script> --}}

@endpush
@endsection