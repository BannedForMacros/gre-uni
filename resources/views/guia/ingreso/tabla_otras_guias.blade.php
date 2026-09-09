@inject('carbon', 'Carbon\Carbon')
<table class="table table-hover table-striped table-sm table-bordered" id="tabla_guias" style="width: 100%">
  <thead>
    <th>Serie</th>
    <th>Razon Social</th>
    <th>Fecha Emision</th>
    <th>Importe</th>
    <th>Ver</th>
    <th>Accion</th>
  </thead>
  <tbody>
    @foreach ($list as $item)
    <tr>
      <td class="align-middle">{{ $item->serie }}-{{$item->numero}}</td>
      <td class="align-middle">{{ "[{$item->proveedor_ruc}] {$item->proveedor_nombre}" }}</td>
      <td class="align-middle">{{ $carbon::parse($item->fecha_emision)->format('d/m/Y') }}</td>
      <td class="align-middle">{{ $item->total_venta }}</td>
      <td class="align-middle"><a href="{{ route('guiaingreso.pdf', ['guia'=>$item->id, 'valorada' => 1]) }}" class="btn btn-primary btn-xs" target="_blank"><i class="fa fa-external-link"></i> Ver</a></td>
      <td class="align-middle">
        <button class="btn btn-success btn-xs select_otra_guia" data-id="{{ $item->id }}"><i class="fa fa-hand-pointer"></i> Selec</button>
      </td>
    </tr>
        
    @endforeach
  </tbody>
</table>