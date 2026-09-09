@inject('carbon', 'Carbon\Carbon')
<table class="table table-hover table-striped table-sm table-bordered" id="tabla_guias" style="width: 100%">
  <thead>
    <th>Condicion</th>
    <th>Serie</th>
    <th>Proveedor</th>
    <th>Fecha Emision</th>
    <th>Importe</th>
    <th>Accion</th>
  </thead>
  <tbody>
    @foreach ($list as $item)
      <tr>
        <td class="align-middle">{{ $item->estado_nombre }}</td>
        <td class="align-middle">{{ $item->serie }}-{{ $item->numero }}</td>
        <td class="align-middle">{{ $item->proveedor_nombre }}</td>
        <td class="align-middle">{{ $carbon::parse($item->fecha_emision)->format('d/m/Y') }}</td>
        <td class="align-middle">{{ $item->total_venta }}</td>
        <td class="align-middle">
          {{-- <a href="{{ route('guiaingreso.pdf', ['guia'=>$item->id]) }}" target="_blank" class="btn btn-sm btn-primary"><i class="fa fa-external-link"></i> Ver</a> --}}

          <!-- Example split danger button -->
          <div class="btn-group btn-group-sm">
            <a href="{{ route('guiaingreso.pdf', ['guia'=>$item->id, 'valorada' => 0]) }}" target="_blank" class="btn btn-sm btn-primary"><i class="fa fa-external-link"></i> Ver</a>
            <button type="button" class="btn btn-dark dropdown-toggle dropdown-toggle-split"
              data-bs-toggle="dropdown" aria-expanded="false">
              <span class="visually-hidden">Toggle Dropdown</span>
            </button>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item text-success" href="{{ route('guiaingreso.pdf', ['guia'=>$item->id, 'valorada' => 1]) }}" target="_blank"><i class="fa fa-file"></i> <b>Guia Valorada</b></a></li>
              <li><hr class="dropdown-divider"></li>
              @if ($item->guia_estado_id == 4)
              <li><a class="dropdown-item" href="{{ route('guiaingreso.continuar', ['guia'=>$item->id]) }}"><i class="fa fa-edit"></i> Continuar</a></li>
              @endif
              
              @if ($item->mostrarGuardarDatamarket == true)
              
              <li><a class="dropdown-item text-success reenviar_datamarket" style="cursor: pointer" data-id="{{ $item->id }}"><i class="fa fa-paper-plane"></i> <b>Re-Enviar DMK</b></a></li>
              <li><hr class="dropdown-divider"></li>
              @endif
              @if ($item->mostrar_eliminar == true)
                <li><a class="dropdown-item text-danger eliminar_guia" style="cursor: pointer" data-id="{{ $item->id }}"><i class="fa fa-times"></i> <b>Eliminar</b></a></li>
              @endif

            </ul>
          </div>
        </td>
      </tr>
    @endforeach
  </tbody>
</table>
