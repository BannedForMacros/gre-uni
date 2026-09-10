<!doctype html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- CSRF Token -->
  <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

  <title><?php echo e(config('app.name', 'Laravel')); ?></title>

  <!-- Scripts -->
  <script src="<?php echo e(asset('js/app.js')); ?>" defer></script>
  <script src="<?php echo e(asset('assets/jquery/jquery-3.7.0.min.js')); ?>"></script>
  <!-- Fonts -->
  <link rel="dns-prefetch" href="//fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">

  <!-- Styles -->
  <link href="<?php echo e(asset('css/app.css')); ?>" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo e(asset('assets/fontawesome/css/all.min.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/select2/dist/css/select2.min.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('assets/select2-bootstrap-5/select2-bootstrap-5-theme.min.css')); ?>">
  
  <link rel="stylesheet" href="<?php echo e(asset('assets/DataTables/datatables.min.css')); ?>">
  <link rel="stylesheet"
    href="<?php echo e(asset('assets/DataTables/DataTables-1.13.1/css/dataTables.bootstrap5.min.css')); ?>">
  <link rel="stylesheet"
    href="<?php echo e(asset('assets/DataTables/Responsive-2.4.0/css/responsive.bootstrap5.min.css')); ?>">

  
  <link rel="stylesheet" href="<?php echo e(asset('css/custom.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset('css/guia.css')); ?>">

  <?php echo app('Tightenco\Ziggy\BladeRouteGenerator')->generate(); ?>
  <script type="text/javascript">
    var APP_URL = <?php echo json_encode(url('/')); ?>

    const _token = $('meta[name="csrf-token"]').attr('content');
  </script>
    <style>[x-cloak]{display:none!important}</style>
</head>

<body>
  <div id="app">
    
    <nav class="navbar navbar-expand-md navbar-dark bg-dark shadow-sm">
      <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo e(url('/')); ?>">
          <?php echo e(config('app.name', 'Laravel')); ?>

        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
          aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="<?php echo e(__('Toggle navigation')); ?>">
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
                <a class="dropdown-item" href="<?php echo e(route('guiaingreso.index')); ?>">Guia de Ingreso</a>
                <a class="dropdown-item" href="<?php echo e(route('guiasalida.index')); ?>">Guia de Salida</a>
              </div>
            </li>
            
            <?php if((Auth::user()->perfil_id ?? null) == 1): ?>
            <li class="nav-item dropdown">
              <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button"
                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                Configuraciones
              </a>
              <div class="dropdown-menu" aria-labelledby="navbarDropdown">
                <a class="dropdown-item" href="<?php echo e(route('empleados.index')); ?>">Empleados</a>
                <a class="dropdown-item" href="<?php echo e(route('configuracion.empresa')); ?>">Configuración de Empresa</a>
              </div>
                  
            </li>
              <?php endif; ?>
          </ul>

          <!-- Right Side Of Navbar -->
          <ul class="navbar-nav ms-auto">
            <!-- Authentication Links -->
            <?php if(auth()->guard()->guest()): ?>
              <?php if(Route::has('login')): ?>
                <li class="nav-item">
                  <a class="nav-link" href="<?php echo e(route('login')); ?>"><?php echo e(__('Login')); ?></a>
                </li>
              <?php endif; ?>

              <?php if(Route::has('register')): ?>
                
              <?php endif; ?>
            <?php else: ?>
              <li class="nav-item dropdown">
                <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button"
                  data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                  <?php echo e(Auth::user()->name); ?>

                </a>

                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                  <a class="dropdown-item" href="<?php echo e(route('logout')); ?>"
                    onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <?php echo e(__('Logout')); ?>

                  </a>

                  <form id="logout-form" action="<?php echo e(route('logout')); ?>" method="POST" class="d-none">
                    <?php echo csrf_field(); ?>
                  </form>
                </div>
              </li>
            <?php endif; ?>
          </ul>




        </div>
      </div>
    </nav>

    <main class="py-4">
      <?php echo $__env->yieldContent('content'); ?>
    </main>
  </div>

  <script src="<?php echo e(asset('assets/select2/dist/js/select2.full.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/sweetalert2/sweetalert.min.js')); ?>"></script>

  <script src="<?php echo e(asset('assets/DataTables/datatables.min.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/DataTables/DataTables-1.13.1/js/dataTables.bootstrap5.min.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/DataTables/Responsive-2.4.0/js/dataTables.responsive.min.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/DataTables/Responsive-2.4.0/js/responsive.bootstrap5.min.js')); ?>"></script>
  
  <script src="<?php echo e(asset('assets/momentjs/momentjs.js')); ?>"></script>
  <script src="<?php echo e(asset('assets/momentjs/moment-with-locales.js')); ?>"></script>
  
  <script src="<?php echo e(asset('assets/js/data_table_es.js')); ?>"></script>

  <script src="<?php echo e(asset('js/round.js')); ?>"></script>
  <?php echo $__env->yieldPushContent('js-scripts'); ?>

</body>

</html>
<?php /**PATH /Users/jesus/DataBussines/guias-electronicas-unificado/resources/views/layouts/app.blade.php ENDPATH**/ ?>