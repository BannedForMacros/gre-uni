<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- CSRF Token -->
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <title>{{ config('app.name', 'Laravel') }}</title>

  <!-- Scripts -->
  <script src="{{ asset('js/app.js') }}" defer></script>
  <script src="{{ asset('assets/jquery/jquery-3.7.0.min.js') }}"></script>
  <!-- Fonts -->
  <link rel="dns-prefetch" href="//fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">

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
    <link rel="stylesheet" href="{{ asset('css/guia.css') }}">

  @routes
  <script type="text/javascript">
    var APP_URL = {!! json_encode(url('/')) !!}
    const _token = $('meta[name="csrf-token"]').attr('content');
  </script>
    <style>[x-cloak]{display:none!important}</style>
</head>

<body>
  <div id="app">
    {{-- <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm"> --}}
    <nav class="navbar navbar-expand-md navbar-dark bg-dark shadow-sm">
      <div class="container-fluid">
        <a class="navbar-brand" href="{{ url('/') }}">
          {{ config('app.name', 'Laravel') }}
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
          aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent">
          <!-- Left Side Of Navbar -->
          <ul class="navbar-nav me-auto">
            <li class="nav-item dropdown">
              <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button"
                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                Comprobantes
              </a>

              <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                <a class="dropdown-item" href="{{ route('guiaingreso.index') }}">Guia de Ingreso</a>
                <a class="dropdown-item" href="{{ route('guiasalida.index') }}">Guia de Salida</a>
              </div>
            </li>
            {{-- @dd(Auth::user()) --}}
            @if ((Auth::user()->perfil_id ?? null) == 1)
            <li class="nav-item dropdown">
              <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button"
                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                Configuraciones
              </a>
              <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                <a class="dropdown-item" href="{{ route('empleados.index') }}">Empleados</a>
                <a class="dropdown-item" href="{{ route('configuracion.empresa') }}">Configuración de Empresa</a>
              </div>
                  
            </li>
              @endif
          </ul>

          <!-- Right Side Of Navbar -->
          <ul class="navbar-nav ms-auto">
            <!-- Authentication Links -->
            @guest
              @if (Route::has('login'))
                <li class="nav-item">
                  <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                </li>
              @endif

              @if (Route::has('register'))
                {{-- <li class="nav-item">
                  <a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a>
                </li> --}}
              @endif
            @else
              <li class="nav-item dropdown">
                <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button"
                  data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                  {{ Auth::user()->name }}
                </a>

                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                  <a class="dropdown-item" href="{{ route('logout') }}"
                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    {{ __('Logout') }}
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
