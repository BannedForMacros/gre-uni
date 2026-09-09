<?php

namespace App\Http\Controllers;

use App\Models\Parametro;
use App\Support\ConfiguracionEmpresa;
use App\Support\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ConfiguracionController extends Controller
{
    /**
     * Pantalla de Configuración de Empresa (logo + datos).
     */
    public function empresa()
    {
        $empresa = (object) [
            'ruc'        => optional(Parametro::find(2))->valor,
            'razon'      => optional(Parametro::find(3))->valor,
            'direccion'  => optional(Parametro::find(4))->valor,
            'telefonos'  => optional(Parametro::find(5))->valor,
        ];

        return view('configuracion.empresa', [
            'empresa'        => $empresa,
            'logoUrl'        => Empresa::logoUrl(),
            'usaConsignados' => ConfiguracionEmpresa::usaConsignados(),
        ]);
    }

    /**
     * Guarda el logo (subida) y los datos de empresa.
     */
    public function empresaUpdate(Request $request)
    {
        $request->validate([
            'logo'      => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
            'razon'     => 'nullable|string|max:255',
            'ruc'       => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'telefonos'       => 'nullable|string|max:100',
            'usa_consignados' => 'nullable|in:0,1',
        ], [
            'logo.image' => 'El archivo debe ser una imagen (PNG, JPG, SVG o WEBP).',
            'logo.max'   => 'El logo no debe pesar más de 2MB.',
        ]);

        // --- Logo ---
        if ($request->hasFile('logo')) {
            // sin mass-assignment: el modelo Parametro está protegido
            $param = Parametro::where('nombre', 'logo_empresa')->first();
            if (!$param) {
                $param = new Parametro();
                $param->id = (int) Parametro::max('id') + 1; // la tabla no es auto_increment
                $param->nombre = 'logo_empresa';
            }

            // borrar el logo anterior si existía
            if (!empty($param->valor) && Storage::disk('public')->exists($param->valor)) {
                Storage::disk('public')->delete($param->valor);
            }

            // guardar con nombre único (cache-busting) en storage/app/public/empresa
            $ext  = strtolower($request->file('logo')->getClientOriginalExtension());
            $name = 'logo_' . substr(md5(uniqid('', true)), 0, 8) . '.' . $ext;
            $path = $request->file('logo')->storeAs('empresa', $name, 'public'); // -> 'empresa/logo_xxx.png'

            $param->valor  = $path;
            $param->activo = 1;
            $param->save();
        }

        // --- Datos de empresa (parámetros existentes) ---
        $map = [2 => 'ruc', 3 => 'razon', 4 => 'direccion', 5 => 'telefonos'];
        foreach ($map as $id => $campo) {
            if ($request->filled($campo)) {
                $p = Parametro::find($id);
                if ($p) {
                    $p->valor = $request->input($campo);
                    $p->save();
                }
            }
        }

        // --- Consignados: se administra POR EMPRESA, no por .env ---
        // Apagado -> la funcionalidad queda inerte: no se muestra el check en
        // Ingreso ni en Salida, no se guarda es_consignado y no se envía a la
        // ApiGRE. El cliente no necesita las columnas en su SQL Server.
        ConfiguracionEmpresa::set(
            'usa_consignados',
            $request->input('usa_consignados') === '1' ? '1' : '0'
        );

        return back()->with('ok', 'Configuración de empresa actualizada correctamente.');
    }

    /**
     * Quitar el logo (volver al fallback).
     */
    public function empresaLogoEliminar()
    {
        $param = Parametro::where('nombre', 'logo_empresa')->first();
        if ($param) {
            if (!empty($param->valor) && Storage::disk('public')->exists($param->valor)) {
                Storage::disk('public')->delete($param->valor);
            }
            $param->valor = '';
            $param->save();
        }
        return back()->with('ok', 'Logo eliminado.');
    }
}
