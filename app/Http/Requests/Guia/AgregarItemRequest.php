<?php

namespace App\Http\Requests\Guia;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validacion de "agregar articulo al detalle".
 *
 * Antes no habia ninguna: el controller leia $request->post() campo por campo
 * y confiaba en lo que llegara. Un precio vacio se convertia en 0.00 sin aviso.
 */
class AgregarItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'producto_id'        => 'required|string|max:50',
            'descripcion'        => 'required|string|max:255',
            'codigo_barra'       => 'nullable|string|max:50',
            'cod_plu'            => 'nullable|string|max:50',
            'precio_sin_igv'     => 'nullable|numeric|min:0',
            'precio_publico'     => 'nullable|numeric|min:0',
            'costo_articulo'     => 'nullable|numeric|min:0',
            'precio_visual'      => 'nullable|numeric|min:0',
            'peso'               => 'nullable|numeric|min:0',
            'cod_unidad'         => 'nullable|integer',
            'desc_unidad_medida' => 'nullable|string|max:100',
            'sigla_umfe'         => 'nullable|string|max:20',
            'tipo_igv'           => 'nullable|integer',
            'items'              => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'producto_id.required' => 'No se recibio el codigo del articulo.',
            'descripcion.required' => 'El articulo no tiene descripcion.',
            'precio_sin_igv.numeric' => 'El precio debe ser un numero.',
        ];
    }
}
