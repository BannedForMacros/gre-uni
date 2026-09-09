@extends('layouts.app')

@section('content')
  <div class="container-fluid">
    <div class="row justify-content-center">
      <div class="col-md-9">

        <h3 class="mb-3">Configuración de Empresa</h3>

        @if (session('ok'))
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa fa-check-circle"></i> {{ session('ok') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        @endif

        @if ($errors->any())
          <div class="alert alert-danger">
            <ul class="mb-0">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <form action="{{ route('configuracion.empresa.update') }}" method="POST" enctype="multipart/form-data">
          @csrf

          <div class="card mb-3">
            <div class="card-header"><b>Logo</b> <small class="text-muted">— aparece en los PDF de guías</small></div>
            <div class="card-body">
              <div class="row align-items-center">
                <div class="col-md-4 text-center">
                  <div style="border:1px dashed #ccc;border-radius:8px;padding:10px;background:#fafafa;min-height:120px;display:flex;align-items:center;justify-content:center;">
                    @if ($logoUrl)
                      <img id="logo_preview" src="{{ $logoUrl }}" alt="Logo" style="max-width:100%;max-height:120px;">
                    @else
                      <img id="logo_preview" src="" alt="" style="max-width:100%;max-height:120px;display:none;">
                      <span id="logo_placeholder" class="text-muted">Sin logo</span>
                    @endif
                  </div>
                </div>
                <div class="col-md-8">
                  <label class="form-label">Subir nuevo logo</label>
                  <input type="file" class="form-control" name="logo" id="logo_input" accept="image/png,image/jpeg,image/svg+xml,image/webp">
                  <small class="text-muted">PNG, JPG, SVG o WEBP. Máx 2MB. Recomendado fondo transparente (PNG).</small>

                  @if ($logoUrl)
                    <div class="mt-2">
                      <button type="button" class="btn btn-outline-danger btn-sm"
                              onclick="document.getElementById('form_eliminar_logo').submit();">
                        <i class="fa fa-trash"></i> Quitar logo actual
                      </button>
                    </div>
                  @endif
                </div>
              </div>
            </div>
          </div>

          <div class="card mb-3">
            <div class="card-header"><b>Datos de la empresa</b></div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-8 mb-3">
                  <label class="form-label">Razón social</label>
                  <input type="text" class="form-control" name="razon" value="{{ old('razon', $empresa->razon) }}">
                </div>
                <div class="col-md-4 mb-3">
                  <label class="form-label">RUC</label>
                  <input type="text" class="form-control" name="ruc" value="{{ old('ruc', $empresa->ruc) }}">
                </div>
                <div class="col-md-8 mb-3">
                  <label class="form-label">Dirección</label>
                  <input type="text" class="form-control" name="direccion" value="{{ old('direccion', $empresa->direccion) }}">
                </div>
                <div class="col-md-4 mb-3">
                  <label class="form-label">Teléfonos</label>
                  <input type="text" class="form-control" name="telefonos" value="{{ old('telefonos', $empresa->telefonos) }}">
                </div>
              </div>
            </div>
          </div>
                    <hr class="my-4">

                    <h6 class="mb-3">Funcionalidades</h6>

                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="usa_consignados" value="0">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="usa_consignados" name="usa_consignados" value="1"
                               {{ ($usaConsignados ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label" for="usa_consignados">
                            <strong>Productos consignados</strong>
                        </label>
                    </div>
                    <div class="form-text mb-4">
                        Actívelo solo si esta empresa trabaja con mercadería en consignación.
                        Si está apagado, la opción no aparece al registrar guías de ingreso ni de
                        salida, y no se envía nada al DataMart. Las empresas que no lo usan no
                        requieren ningún cambio en su base de datos.
                    </div>


          <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Guardar cambios</button>
        </form>

        {{-- form separado para eliminar el logo --}}
        <form id="form_eliminar_logo" action="{{ route('configuracion.empresa.logo.eliminar') }}" method="POST" class="d-none">
          @csrf
        </form>

      </div>
    </div>
  </div>

  @push('js-scripts')
    <script>
      // Preview en vivo al elegir un archivo
      document.getElementById('logo_input').addEventListener('change', function (e) {
        var file = e.target.files[0];
        if (!file) return;
        var img = document.getElementById('logo_preview');
        var ph  = document.getElementById('logo_placeholder');
        img.src = URL.createObjectURL(file);
        img.style.display = 'inline-block';
        if (ph) ph.style.display = 'none';
      });
    </script>
  @endpush
@endsection
