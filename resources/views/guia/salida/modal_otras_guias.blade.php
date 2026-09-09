<div class="modal fade" tabindex="-1" id="modalOtrasGuias" data-bs-backdrop="static" data-bs-keyboard="false"  >
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-primary text-light">
        <h5 class="modal-title"><i class="fa fa-check-double"></i> Registro de Guia</h5>
        {{-- <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button> --}}
      </div>
      <div class="modal-body">
        <form name="form_buscar_otras_guias" id="form_buscar_otras_guias">
          @csrf
          <div class="row">
            <div class="col-md-3">
              <label class="form-label">Estado</label>
              <select name="estado_id" class="form-select">
                <option value="0">Anuladas</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Serie</label>
              <input type="text" class="form-control" name="serie" placeholder="Serie">
            </div>
            <div class="col-md-4">
              <label class="form-label">Numero</label>
              <input type="text" class="form-control" name="numero" placeholder="Numero">
            </div>

            <div class="col-md-2">
              <button type="submit" class="btn btn-primary btn-sm mt-4"><i class="fa fa-search"></i> Buscar</button>
            </div>

          </div>

        </form>

        <div class="row">
          <div class="col-md-12">
            <div id="resultados_otras_guias"></div>
          </div>
        </div>


      </div>
      <div class="modal-footer justify-content-between" id="div_footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fa fa-arrow-circle-left"></i> Salir</button>
        {{-- <button class="btn btn-success"><i class="fa fa-download"></i> Cargar</button> --}}
      </div>
    </div>
  </div>
</div>