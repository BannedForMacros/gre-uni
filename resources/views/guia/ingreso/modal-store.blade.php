<div class="modal fade" tabindex="-1" id="modalStore" data-bs-backdrop="static" data-bs-keyboard="false"  >
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-light">
        <h5 class="modal-title"><i class="fa fa-check-double"></i> Registro de Guia Ingreso</h5>
        {{-- <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button> --}}
      </div>
      <div class="modal-body">

        <ul class="list-group">
          <li class="list-group-item d-flex justify-content-between align-items-center" id="li_store">
            <b>Registrando guia...</b>
            <span class=""><i class="fa-solid fa-spinner fa-spin fa-lg"></i></span>
          </li>
          <li class="list-group-item d-flex justify-content-between align-items-center" id="li_store_datamart">
            <b>Registrando en DataMart...</b>
            <span class=""><i class="fa-solid fa-spinner fa-spin fa-lg"></i></span>
          </li>
        </ul>

      </div>
      <div class="modal-footer justify-content-between" id="div_footer">
        <a type="button" class="btn btn-danger float-start" href="{{ route('guiaingreso.index') }}" ><i class="fa fa-arrow-circle-left"></i> Salir</a>
      </div>
    </div>
  </div>
</div>