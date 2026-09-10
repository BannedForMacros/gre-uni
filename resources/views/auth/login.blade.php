@extends('layouts.app')

@section('content')
{{--
  Pantalla de acceso.

  La anterior era el andamio por defecto de Laravel, sin estilo y con un error
  de accesibilidad: <label for="email"> apuntaba a un campo con id="username",
  asi que hacer clic en la etiqueta no enfocaba nada.
--}}
<div class="gre gre-login">
  <div class="gre-login-caja">

    @if ($logo = \App\Support\Empresa::logoUrl())
      <img src="{{ $logo }}" alt="{{ \App\Models\Parametro::find(3)->valor ?? '' }}" class="gre-login-logo">
    @else
      <div class="gre-login-marca">
        <i class="fa fa-truck-fast"></i>
        <span>Guías de Remisión</span>
      </div>
    @endif

    <h1 class="gre-login-titulo">Iniciar sesión</h1>

    @if ($errors->any())
      <div class="gre-login-error" role="alert">
        <i class="fa fa-circle-exclamation"></i>
        <span>{{ $errors->first() }}</span>
      </div>
    @endif

    <form method="POST" action="{{ route('login') }}" x-data="{ enviando: false }" @submit="enviando = true">
      @csrf

      <div class="mb-3">
        <label for="username" class="form-label">Usuario</label>
        <input id="username" name="username" type="text"
               class="form-control @error('username') is-invalid @enderror"
               value="{{ old('username') }}"
               required autocomplete="username" autofocus>
      </div>

      <div class="mb-3">
        <label for="password" class="form-label">Contraseña</label>
        <input id="password" name="password" type="password"
               class="form-control @error('password') is-invalid @enderror"
               required autocomplete="current-password">
      </div>

      <div class="form-check mb-4">
        <input class="form-check-input" type="checkbox" name="remember" id="remember"
               {{ old('remember') ? 'checked' : '' }}>
        <label class="form-check-label" for="remember">Mantener la sesión abierta</label>
      </div>

      <button type="submit" class="btn btn-primary w-100" :disabled="enviando">
        <span x-show="!enviando">Ingresar</span>
        <span x-show="enviando" x-cloak>
          <i class="fa fa-circle-notch fa-spin"></i> Ingresando…
        </span>
      </button>
    </form>

  </div>

  <p class="gre-login-pie">
    {{ \App\Models\Parametro::find(3)->valor ?? '' }}
  </p>
</div>
@endsection

@push('js-scripts')
  <script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
@endpush
