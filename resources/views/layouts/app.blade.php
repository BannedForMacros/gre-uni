<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- CSRF Token -->
  <meta name="csrf-token" content="{{ csrf_token() }}">

  {{-- El titulo de la pestana tambien decia "Laravel". Lleva la empresa
       porque un usuario suele tener abiertas varias instalaciones a la vez. --}}
  <title>{{ config('app.name', 'Guías Electrónicas') }}@if($greTituloEmpresa = optional(\App\Models\Parametro::find(3))->valor) · {{ $greTituloEmpresa }}@endif</title>

  <!-- Scripts -->
  <script src="{{ asset('js/app.js') }}" defer></script>
  <script src="{{ asset('assets/jquery/jquery-3.7.0.min.js') }}"></script>
  <!-- Fonts -->
  {{-- Sin fuentes remotas.

       La aplicacion se instala en el servidor del cliente y muchos no tienen
       salida a internet: esta hoja de estilo de Google bloqueaba el render de
       TODAS las pantallas hasta que la peticion expiraba, y despues caia igual
       a la fuente del sistema. Ahora se usa directamente la del sistema, que
       en Windows es la que el usuario ya lee todo el dia. --}}

  <!-- Styles -->
  <link href="{{ asset('css/app.css') }}" rel="stylesheet">
  <link rel="stylesheet" href="{{ asset('assets/fontawesome/css/all.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/select2/dist/css/select2.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/select2-bootstrap-5/select2-bootstrap-5-theme.min.css') }}">
  
  <link rel="stylesheet" href="{{ asset('assets/DataTables/datatables.min.css') }}">
  <link rel="stylesheet"
    href="{{ asset('assets/DataTables/DataTables-1.13.1/css/dataTables.bootstrap5.min.css') }}">
  <link rel="stylesheet"
    href="{{ asset('assets/DataTables/Responsive-2.4.0/css/responsive.bootstrap5.min.css') }}">

  
  <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    {{-- filemtime como version: el navegador del cliente cachea el css y tras
       una actualizacion se quedaba con el viejo hasta un Ctrl+F5 manual. --}}
    <link rel="stylesheet" href="{{ asset('css/guia.css') }}?v={{ @filemtime(public_path('css/guia.css')) }}">

  @routes
  <script type="text/javascript">
    var APP_URL = {!! json_encode(url('/')) !!}
    const _token = $('meta[name="csrf-token"]').attr('content');
  </script>
    <style>[x-cloak]{display:none!important}</style>
</head>

<body>
  <div id="app">
    {{-- Cabecera de la aplicacion.

         Decia "Laravel", que es el nombre del framework: lo que sale por
         defecto de APP_NAME y que nadie llego a cambiar. En el servidor de un
         cliente eso es lo primero que ve el usuario cada manana.

         Ahora dice que ES -Guias Electronicas- y DE QUIEN es, leyendo la razon
         social que el propio cliente configura en Configuracion de Empresa. Si
         hay logo cargado se usa; si no, una marca con la inicial. --}}
    @php
      $greEmpresa = optional(\App\Models\Parametro::find(3))->valor;
      $greLogo    = \App\Support\ConfiguracionEmpresa::get('logo_empresa');
    @endphp

    <nav class="navbar navbar-expand-md navbar-dark gre-nav">
      <div class="container-fluid">

        <a class="navbar-brand gre-marca" href="{{ route('guiaingreso.index') }}">
          @if ($greLogo)
            <img src="{{ asset('storage/' . $greLogo) }}" alt="{{ $greEmpresa }}" class="gre-marca-logo">
          @else
            <span class="gre-marca-inicial">{{ mb_strtoupper(mb_substr($greEmpresa ?: 'G', 0, 1)) }}</span>
          @endif

          <span class="gre-marca-texto">
            <strong>Guías Electrónicas</strong>
            @if ($greEmpresa)
              <small>{{ $greEmpresa }}</small>
            @endif
          </span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
          aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
          <ul class="navbar-nav me-auto">
            {{-- Se marca donde esta el usuario. Antes las dos entradas se veian
                 igual estuvieras donde estuvieras. --}}
            <li class="nav-item dropdown">
              <a id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown"
                 aria-haspopup="true" aria-expanded="false"
                 class="nav-link dropdown-toggle {{ request()->routeIs('guiaingreso.*', 'guiasalida.*') ? 'activo' : '' }}">
                <i class="fa fa-file-lines"></i> Comprobantes
              </a>

              <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                <a class="dropdown-item {{ request()->routeIs('guiaingreso.*') ? 'activo' : '' }}"
                   href="{{ route('guiaingreso.index') }}">Guía de Ingreso</a>
                <a class="dropdown-item {{ request()->routeIs('guiasalida.*') ? 'activo' : '' }}"
                   href="{{ route('guiasalida.index') }}">Guía de Salida</a>
              </div>
            </li>

            @if ((Auth::user()->perfil_id ?? null) == 1)
              <li class="nav-item dropdown">
                <a id="navbarDropdownConfig" href="#" role="button" data-bs-toggle="dropdown"
                   aria-haspopup="true" aria-expanded="false"
                   class="nav-link dropdown-toggle {{ request()->routeIs('empleados.*', 'configuracion.*') ? 'activo' : '' }}">
                  <i class="fa fa-gear"></i> Configuraciones
                </a>
                <div class="dropdown-menu" aria-labelledby="navbarDropdownConfig">
                  <a class="dropdown-item {{ request()->routeIs('empleados.*') ? 'activo' : '' }}"
                     href="{{ route('empleados.index') }}">Empleados</a>
                  <a class="dropdown-item {{ request()->routeIs('configuracion.*') ? 'activo' : '' }}"
                     href="{{ route('configuracion.empresa') }}">Configuración de Empresa</a>
                </div>
              </li>
            @endif
          </ul>

          <ul class="navbar-nav ms-auto">
            @guest
              @if (Route::has('login'))
                <li class="nav-item">
                  <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                </li>
              @endif
            @else
              <li class="nav-item dropdown">
                <a id="navbarDropdownUsuario" class="nav-link gre-usuario" href="#" role="button"
                   data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                  {{-- Iniciales en vez de un icono generico: identifican de un
                       vistazo con que cuenta se esta trabajando. --}}
                  <span class="gre-avatar">{{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}</span>
                  <span class="gre-usuario-nombre">{{ Auth::user()->name }}</span>
                  <i class="fa fa-chevron-down gre-usuario-flecha"></i>
                </a>

                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdownUsuario">
                  <span class="dropdown-item-text gre-usuario-ficha">
                    <strong>{{ Auth::user()->name }}</strong>
                    <small>{{ Auth::user()->email }}</small>
                  </span>
                  <hr class="dropdown-divider">
                  <a class="dropdown-item" href="{{ route('logout') }}"
                     onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <i class="fa fa-arrow-right-from-bracket"></i> {{ __('Logout') }}
                  </a>

                  <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                    @csrf
                  </form>
                </div>
              </li>
            @endguest
          </ul>
        </div>
      </div>
    </nav>

    <main class="py-4">
      @yield('content')
    </main>
  </div>

  <script src="{{ asset('assets/select2/dist/js/select2.full.js') }}"></script>
  <script src="{{ asset('assets/sweetalert2/sweetalert.min.js') }}"></script>

  <script src="{{ asset('assets/DataTables/datatables.min.js') }}"></script>
  <script src="{{ asset('assets/DataTables/DataTables-1.13.1/js/dataTables.bootstrap5.min.js') }}"></script>
  <script src="{{ asset('assets/DataTables/Responsive-2.4.0/js/dataTables.responsive.min.js') }}"></script>
  <script src="{{ asset('assets/DataTables/Responsive-2.4.0/js/responsive.bootstrap5.min.js') }}"></script>
  
  <script src="{{ asset('assets/momentjs/momentjs.js') }}"></script>
  <script src="{{ asset('assets/momentjs/moment-with-locales.js') }}"></script>
  
  <script src="{{ asset('assets/js/data_table_es.js') }}"></script>

  <script src="{{ asset('js/round.js') }}"></script>
  @stack('js-scripts')

</body>

</html>
