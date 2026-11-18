<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CambiarPasswordRequest;
use App\Http\Requests\Auth\ConsultarRucRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RecuperarPasswordRequest;
use App\Http\Requests\Auth\RegistroProveedorRequest;
use App\Http\Requests\Auth\RestablecerPasswordRequest;
use App\Models\Estado;
use App\Models\Modulo;
use App\Models\Proveedor;
use App\Models\TipoDocumento;
use App\Models\User;
use App\Notifications\RestablecerPasswordNotification;
use App\Services\AuditoriaService;
use App\Services\Captcha\CaptchaInternoService;
use App\Services\Captcha\CaptchaService;
use App\Services\CorreoPlataformaService;
use App\Services\SunatService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Brokers cuyo enlace puede usarse en /restablecer-password, en este
     * orden: `recuperacion` (10 min, "Olvidé mi contraseña") y `users`
     * (60 min, alta/restablecimiento hecho por un administrador). */
    private const BROKERS_RESTABLECER = ['recuperacion', 'users'];

    public function __construct(
        private AuditoriaService $auditoria,
        private CorreoPlataformaService $correos,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return ApiResponse::error('Las credenciales no son válidas.', null, 401);
        }

        $request->session()->regenerate();
        $request->session()->put('login_at', now()->timestamp);

        /** @var User $user */
        $user = Auth::user();

        if ($motivo = $this->motivoSuspension($user)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return ApiResponse::error($motivo, null, 423);
        }

        $this->auditoria->registrar('AUTENTICACION', 'LOGIN', 'users', $user->id, userId: $user->id);

        return ApiResponse::success($this->construirMe($user));
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success($this->construirMe($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auditoria->registrar('AUTENTICACION', 'LOGOUT', 'users', $request->user()->id, userId: $request->user()->id);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::success();
    }

    public function cambiarPassword(CambiarPasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($request->string('password_actual'), $user->password)) {
            throw ValidationException::withMessages([
                'password_actual' => ['La contraseña actual no es correcta.'],
            ]);
        }

        $user->password = $request->string('password');
        $user->save();

        $this->auditoria->registrar('AUTENTICACION', 'ACTUALIZAR', 'users', $user->id);

        return ApiResponse::success(null, 'Contraseña actualizada.');
    }

    public function recuperarPassword(RecuperarPasswordRequest $request, CaptchaService $captcha): JsonResponse
    {
        if (! $captcha->verificar($request->input('captcha'))) {
            return ApiResponse::error('El captcha no es válido.', null, 422);
        }

        $minutos = (int) config('auth.passwords.recuperacion.expire');

        Password::broker('recuperacion')->sendResetLink(
            $request->only('email'),
            fn (User $user, string $token) => $user->notify(new RestablecerPasswordNotification($token, $minutos)),
        );

        // Misma respuesta exista o no el correo (no revela qué cuentas hay).
        return ApiResponse::success(
            [
                'vigencia_minutos' => $minutos,
                // Mientras no pase este tiempo el broker no emite otro enlace
                // para el mismo correo: el botón "Reintentar envío" del
                // frontend se desbloquea recién entonces.
                'reintento_en_segundos' => (int) config('auth.passwords.recuperacion.throttle'),
            ],
            'Si el correo existe, se envió un enlace de recuperación.',
        );
    }

    public function restablecerPassword(RestablecerPasswordRequest $request): JsonResponse
    {
        $credenciales = $request->only('email', 'password', 'password_confirmation', 'token');
        $status = Password::INVALID_TOKEN;

        foreach (self::BROKERS_RESTABLECER as $broker) {
            $status = Password::broker($broker)->reset($credenciales, function (User $user, string $password) {
                $user->password = $password;
                $user->save();

                // Con la contraseña ya cambiada, cualquier otro enlace pendiente
                // de esta cuenta (de cualquiera de los dos tipos) deja de servir.
                foreach (self::BROKERS_RESTABLECER as $otro) {
                    Password::broker($otro)->deleteToken($user);
                }
            });

            if ($status === Password::PASSWORD_RESET) {
                break;
            }
        }

        if ($status !== Password::PASSWORD_RESET) {
            return ApiResponse::error('El enlace de recuperación no es válido o expiró.', null, 422);
        }

        return ApiResponse::success(null, 'Contraseña restablecida.');
    }

    /** Lo consulta la pantalla de restablecer antes de mostrar el formulario:
     * dice si el enlace sigue vigente y cuántos segundos le quedan, para la
     * cuenta regresiva. Responde igual de genérico ante un enlace inexistente,
     * vencido o ya usado. */
    public function validarEnlaceRestablecer(Request $request): JsonResponse
    {
        $token = (string) $request->query('token');
        $correo = (string) $request->query('email');
        $usuario = ($token !== '' && $correo !== '') ? User::where('email', $correo)->first() : null;

        if ($usuario) {
            foreach (self::BROKERS_RESTABLECER as $broker) {
                if (! Password::broker($broker)->tokenExists($usuario, $token)) {
                    continue;
                }

                $creado = DB::table(config("auth.passwords.{$broker}.table"))
                    ->where('email', $usuario->getEmailForPasswordReset())
                    ->value('created_at');
                $vence = Carbon::parse($creado)->addMinutes((int) config("auth.passwords.{$broker}.expire"));

                return ApiResponse::success([
                    'valido' => true,
                    'expira_en_segundos' => max(0, (int) now()->diffInSeconds($vence, false)),
                ]);
            }
        }

        return ApiResponse::success(['valido' => false, 'expira_en_segundos' => null]);
    }

    public function captcha(CaptchaInternoService $captcha): JsonResponse
    {
        return ApiResponse::success($captcha->generarReto());
    }

    public function consultarRuc(ConsultarRucRequest $request, CaptchaService $captcha, SunatService $sunat): JsonResponse
    {
        if (! $captcha->verificar($request->input('captcha'))) {
            return ApiResponse::error('El captcha no es válido.', null, 422);
        }

        $numeroDocumento = $request->input('numero_documento');

        if (Proveedor::where('PROV_NUMERO_DOCUMENTO', $numeroDocumento)->exists()) {
            return ApiResponse::error('Ya existe una cuenta con este número de documento.', null, 409);
        }

        $datos = $sunat->consultar($numeroDocumento);

        return ApiResponse::success(array_merge(['numero_documento' => $numeroDocumento], $datos));
    }

    public function registro(RegistroProveedorRequest $request, CaptchaService $captcha, SunatService $sunat): JsonResponse
    {
        if (! $captcha->verificar($request->input('captcha'))) {
            return ApiResponse::error('El captcha no es válido.', null, 422);
        }

        $usuario = DB::transaction(function () use ($request, $sunat) {
            $user = User::create([
                'name' => $request->input('razon_social'),
                'email' => $request->input('correo'),
                'password' => $request->input('password'),
            ]);
            $user->assignRole('PROVEEDOR');

            $tipoDocumento = TipoDocumento::where('TIDO_CODIGO', $request->input('tipo_documento'))->firstOrFail();
            $estadoActivo = Estado::where('ESTA_CONTEXTO', 'PROVEEDOR')->where('ESTA_CODIGO', 'ACTIVO')->firstOrFail();

            $origen = $request->input('origen');
            $respuestaSunat = $origen === 'SUNAT' ? $sunat->consultar($request->input('numero_documento')) : null;

            $proveedor = Proveedor::create([
                'PROV_USER_ID' => $user->id,
                'PROV_TIPO_DOCUMENTO_ID' => $tipoDocumento->TIDO_ID,
                'PROV_NUMERO_DOCUMENTO' => $request->input('numero_documento'),
                'PROV_TIPO_PERSONA' => $request->input('tipo_persona'),
                'PROV_RAZON_SOCIAL' => $request->input('razon_social'),
                'PROV_CORREO' => $request->input('correo'),
                'PROV_TELEFONO' => $request->input('telefono'),
                'PROV_PERSONA_CONTACTO' => $request->input('persona_contacto'),
                'PROV_ESTADO_ID' => $estadoActivo->ESTA_ID,
                'PROV_RESPUESTA_SUNAT' => $respuestaSunat,
                'PROV_ORIGEN_DATO' => $origen,
            ]);

            $rubroIds = array_merge($request->input('rubros_servicios', []), $request->input('rubros_bienes', []));
            $proveedor->rubros()->attach($rubroIds);

            $this->auditoria->registrar('PROVEEDORES', 'CREAR', 'PROVEEDOR', $proveedor->PROV_ID, userId: $user->id);

            return $user;
        });

        Auth::login($usuario);
        $request->session()->regenerate();
        $request->session()->put('login_at', now()->timestamp);

        $this->correos->bienvenidaProveedor(
            $request->string('correo')->toString(),
            $request->string('razon_social')->toString(),
            $request->string('tipo_documento')->toString(),
            $request->string('numero_documento')->toString(),
        );

        return ApiResponse::success($this->construirMe($usuario->fresh()), 'Cuenta registrada correctamente.', 201);
    }

    private function motivoSuspension(User $user): ?string
    {
        if (! $user->activo) {
            return 'Tu cuenta está desactivada. Contacta al administrador.';
        }

        if ($user->hasRole('PROVEEDOR') && $user->proveedor && $user->proveedor->estado->ESTA_CODIGO === 'SUSPENDIDO') {
            return 'Tu cuenta de proveedor está suspendida.';
        }

        if ($user->entidad_id && $user->entidad && $user->entidad->estado->ESTA_CODIGO === 'SUSPENDIDA') {
            return 'La entidad a la que perteneces está suspendida.';
        }

        return null;
    }

    private function construirMe(User $user): array
    {
        $permisos = $user->getAllPermissions()->pluck('name')->values();
        $rol = $user->getRoleNames()->first();

        $modulosPermitidos = $permisos
            ->map(fn (string $permiso) => strtoupper(explode('.', $permiso)[0]))
            ->unique()
            // EVALUACION/ADJUDICACION/PRESENTACION_OFERTAS SÍ tienen pantalla
            // propia de nivel superior (M06/M07/M08 del TDR, 00_CONTEXTO_TDR.md
            // §4) desde el Bloque E de este hardening — ya no se excluyen.
            // PROVEEDORES se excluye siempre para cualquier rol: la ficha
            // propia del proveedor ya se accede desde "Mis datos" en el menú
            // de perfil (AppBar.vue), un ítem de sidebar aparte es 100%
            // redundante. OFERTAS es del PROVEEDOR: ADMINISTRADOR tiene el
            // permiso porque el rol reúne todos los permisos del sistema,
            // pero el backend exige `role:PROVEEDOR` en la ruta — sin este
            // filtro, ADMINISTRADOR veía el ítem y la pantalla le devolvía 403.
            ->reject(fn (string $codigo) => $codigo === 'PROVEEDORES')
            ->reject(fn (string $codigo) => $codigo === 'OFERTAS' && $rol !== 'PROVEEDOR')
            ->values();

        $menu = Modulo::whereIn('MODU_CODIGO', $modulosPermitidos)
            // Módulos ocultos (MODU_VISIBLE = false, p. ej. Entidades): no se
            // muestran a nadie, aunque el rol conserve sus permisos.
            ->where('MODU_VISIBLE', true)
            ->orderBy('MODU_ORDEN')
            ->get()
            ->map(fn (Modulo $modulo) => [
                'codigo' => $modulo->MODU_CODIGO,
                'nombre' => $modulo->MODU_NOMBRE,
                'icono' => $modulo->MODU_ICONO,
                'orden' => $modulo->MODU_ORDEN,
            ])
            ->values();

        $perfil = ['tipo' => 'INTERNO'];

        // Perfil ENTIDAD = limitado a su entidad (rol ENTIDAD o rol
        // personalizado con entidad asignada): el frontend decide con esto.
        if ($user->limitadoAEntidad() && $user->entidad) {
            $perfil = [
                'tipo' => 'ENTIDAD',
                'entidad' => [
                    'entidad_id' => $user->entidad->ENTI_ID,
                    'razon_social' => $user->entidad->ENTI_RAZON_SOCIAL,
                    'sigla' => $user->entidad->ENTI_SIGLA,
                    'estado' => $user->entidad->estado->ESTA_CODIGO,
                ],
            ];
        } elseif ($rol === 'PROVEEDOR' && $user->proveedor) {
            $perfil = [
                'tipo' => 'PROVEEDOR',
                'proveedor' => [
                    'proveedor_id' => $user->proveedor->PROV_ID,
                    'razon_social' => $user->proveedor->PROV_RAZON_SOCIAL,
                    'estado' => $user->proveedor->estado->ESTA_CODIGO,
                ],
            ];
        }

        return [
            'usuario' => [
                'id' => $user->id,
                'nombre' => $user->name,
                'email' => $user->email,
                'rol' => $rol,
            ],
            'permisos' => $permisos,
            'menu' => $menu,
            'perfil' => $perfil,
            'sesion' => [
                'expira_en_segundos' => $this->segundosRestantesSesion(),
            ],
        ];
    }

    /** Segundos hasta el tope absoluto de sesión (null si no aplica). Se
     * entrega relativo, no como hora, para que el reloj del navegador no
     * pueda desfasar el cálculo. */
    private function segundosRestantesSesion(): ?int
    {
        $limite = (int) config('session.absolute_lifetime') * 60;
        $request = request();

        if ($limite <= 0 || ! $request->hasSession()) {
            return null;
        }

        $inicio = (int) $request->session()->get('login_at', now()->timestamp);

        return max(0, $inicio + $limite - now()->timestamp);
    }
}
