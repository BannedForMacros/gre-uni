@inject('carbon', 'Carbon\Carbon')

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
            <h5><i class="fa fa-users"></i> Empleados Registrados
              <a href="{{ route('empleados.create') }}" class="btn btn-primary btn-sm float-end"><i class="fa fa-plus"></i>
                Nuevo</a>
            </h5>
          </div>
        </div>
        <div class="row mt-4">
          <div class="col-md-12">
            <table class="table table-hover table-striped table-sm table-bordered" id="tabla_empleados" style="width: 100%">
              <thead>
                <th>Nro Documento</th>
                <th>Apellido Paterno</th>
                <th>Apellido Materno</th>
                <th>Nombres</th>
                <th>Sexo</th>
                <th>Accion</th>
              </thead>
              @foreach ($list ?? [] as $item)
                <tr>
                  <td class="align-middle">{{ $item->nro_documento }}</td>
                  <td class="align-middle">{{ $item->ape_paterno }}</td>
                  <td class="align-middle">{{ $item->ape_materno }}</td>
                  <td class="align-middle">{{ $item->nombres }}</td>
                  <td class="align-middle">{{ ($item->sexo == '1') ? 'Masculino' : 'Femenino' ; }}</td>
                  <td class="align-middle">
                    <a type="button" class="btn btn-success btn-sm" href="{{ route('empleados.edit', ['empleado'=>$item->id]) }}"><i class="fa fa-edit"></i> Editar</a>
                  </td>
                </tr>
              @endforeach
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>
@endsection
