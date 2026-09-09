<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\Parametro;
use App\Models\Perfil;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class EmpleadoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $list = Empleado::where('activo', 1)->get();

        return view('empleados.index', compact('list'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        // dd('create empleado');
        $api_datos = Parametro::find(6)->valor;

        $listTrabajadores = Http::get("{$api_datos}/ObtenerTrabajador?CodigoTrabajador=-1")->object()->trabajador;
        // dd($listTrabajadores);
        return view('empleados.create', compact('listTrabajadores'));
    }

    public function getEmpleadoDmk(Request $request)
    {
        // dd($request->post());
        $api_datos = Parametro::find(6)->valor;

        $empleado_codigo = $request->post('empleado_codigo');
        $procede = true;
        $msj = "Datos obtenidos de DMK";
        $msj_tipo = "success";
        $log = "";

        try {
            
            $empleado = Http::get("{$api_datos}/ObtenerTrabajador?CodigoTrabajador={$empleado_codigo}")->object()->trabajador[0];
            // dd($empleado);
        } catch (Exception $e) {
            //throw $th;
            $procede = false;
            $msj = "No se pudo completar la consulta a DMK";
            $msj_tipo = "error";
            $log = "{$e}";
        }
        if ($procede == true) {
            // dd(explode(' ', $empleado->apellidos, 2));
            // dd('hola');
            $explode_apellidos = explode(' ', $empleado->apellidos, 2);
            // dd($explode_apellidos);
            $empleado->ape_paterno = $explode_apellidos[0] ?? '';
            $empleado->ape_materno = $explode_apellidos[1] ?? '';
        }

        return response()->json(['procede' => $procede, 'msj' => $msj, 'msj_tipo' => $msj_tipo, 'log' => $log, 'empleado' => $empleado]);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // dd($request->post());

        $datos = $request->post();

        $procede = true;
        $msj = "Empleado registrado";
        $msj_tipo = "success";
        $log = "";
        $url_redirect = "";
        

        $duplicado = Empleado::Where('nro_documento', $datos['nro_documento'])->where('activo', 1)->first();

        if ($duplicado != null) {
            $procede = false;
            $msj = "Ya existe un empleado con este Nro de Documento";
            $msj_tipo = "error";
            
        }
        
        if ($procede == true) {
            $duplicado_dmk = Empleado::where('empleado_dmk_id', $datos['empleado_dmk_id'])->where('activo',1)->first();
            
            if ($duplicado_dmk != null) {
                $procede = false;
                $msj = "Ya existe un empleado DNK previamente registrado en nube";
                $msj_tipo = "error";
                
            }
        }

        if ($procede == true) {
            
            try {
                $empleado = Empleado::create($datos);
                // dd($empleado);
                $url_redirect = route('empleados.edit', ['empleado' => $empleado->id]);
    
            } catch (Exception $e) {
                //throw $th;
                // dd($e);
                $procede = false;
                $msj = "No se pudo registrar empleado";
                $msj_tipo = "error";
                $log = "{$e}";
    
            }
        }


        if ($procede == true) {
            $user = new User();
            $user->name = "{$empleado->ape_paterno} {$empleado->ape_materno}";
            // $user->email = "{$empleado->nro_documento}@mail.com";
            $user->empleado_id = $empleado->id;
            $user->perfil_id = $datos['perfil_id'];
            $user->username = $datos['usuario'];
            $user->password_alt = base64_encode($datos['password']);
            $user->password = Hash::make($datos['password']);

            try {
                $user->save();
            } catch (Exception $e) {
                // dd($e);
                $procede = false;
                $msj = "No se pudo registrar Usuario";
                $msj_tipo = "error";
                $log = "{$e}";
            }
        }

        if ($procede == false) {
            if (($empleado ?? null) != null) {
                $del_empleado = Empleado::find($empleado->id);
                
                $del_empleado->delete();
                
            }
        }

        return response()->json(['procede' => $procede, 'msj' => $msj, 'msj_tipo' => $msj_tipo, 'log' => $log, 'url_redirect' => $url_redirect]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Empleado $empleado)
    {
        $api_datos = Parametro::find(6)->valor;
        // dd($empleado);
        $listPerfiles = Perfil::where('activo', 1)->get();

        $user = User::where('empleado_id', $empleado->id)->first();
        $listTrabajadores = [];
        if ($empleado->empleado_dmk_id != null) {
            $listTrabajadores = Http::get("{$api_datos}/ObtenerTrabajador?CodigoTrabajador={$empleado->empleado_dmk_id}")->object()->trabajador;
            
        }

        return  view('empleados.edit', compact('empleado', 'listPerfiles', 'user', 'listTrabajadores'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        // dd($request->post());

        $datos = $request->post();
        $id = $request->post('id');
        $procede = true;
        $msj = "Empleado actualizado";
        $msj_tipo = "success";
        $log = "";
        $url_redirect = "";

        $empleado = Empleado::find($id);

        try {
            unset($datos['id']);
            // dd($datos);
            $empleado->update($datos);
            // dd($empleado);
            $url_redirect = route('empleados.edit', ['empleado' => $empleado->id]);

        } catch (Exception $e) {
            //throw $th;
            // dd($e);
            $procede = false;
            $msj = "No se pudo actualizar empleado";
            $msj_tipo = "error";
            $log = "{$e}";

        }

        if ($procede == true) {
            $user = User::where('empleado_id', $empleado->id)->first();
            $user->name = "{$empleado->ape_paterno} {$empleado->ape_materno}";
            // $user->email = "{$empleado->nro_documento}@mail.com";
            $user->empleado_id = $empleado->id;
            $user->perfil_id = $datos['perfil_id'];
            $user->username = $datos['usuario'];
            $user->password_alt = base64_encode($datos['password']);
            $user->password = Hash::make($datos['password']);

            try {
                $user->save();
            } catch (Exception $e) {
                // dd($e);
                $procede = false;
                $msj = "No se pudo registrar Usuario";
                $msj_tipo = "error";
                $log = "{$e}";
            }
        }

        return response()->json(['procede' => $procede, 'msj' => $msj, 'msj_tipo' => $msj_tipo, 'log' => $log, 'url_redirect' => $url_redirect]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
