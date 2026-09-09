<?php

namespace App\Support;

use App\Models\Parametro;
use Illuminate\Support\Facades\Storage;

/**
 * Resuelve el logo de la empresa de forma centralizada.
 *
 * Orden de prioridad:
 *   1) Logo subido desde Configuración > Empresa (parámetro 'logo_empresa', en storage/public).
 *   2) Fallback legacy: public/img/logo.png (si existe).
 *   3) null -> el PDF simplemente no pinta logo (nunca imagen rota).
 */
class Empresa
{
    /** Ruta relativa guardada en el parámetro (ej. 'empresa/logo.png') o null. */
    public static function logoRel(): ?string
    {
        $p = Parametro::where('nombre', 'logo_empresa')->first();
        $v = $p ? trim((string) $p->valor) : '';
        return $v !== '' ? $v : null;
    }

    /** URL pública para mostrar en pantalla (página de configuración), o null. */
    public static function logoUrl(): ?string
    {
        $rel = self::logoRel();
        if ($rel && Storage::disk('public')->exists($rel)) {
            return asset('storage/' . $rel);
        }
        if (file_exists(public_path('img/logo.png'))) {
            return asset('img/logo.png');
        }
        return null;
    }

    /** Ruta absoluta en disco para dompdf (PDF), o null si no hay logo. */
    public static function logoPath(): ?string
    {
        $rel = self::logoRel();
        if ($rel && Storage::disk('public')->exists($rel)) {
            return storage_path('app/public/' . $rel);
        }
        if (file_exists(public_path('img/logo.png'))) {
            return public_path('img/logo.png');
        }
        return null;
    }
}
