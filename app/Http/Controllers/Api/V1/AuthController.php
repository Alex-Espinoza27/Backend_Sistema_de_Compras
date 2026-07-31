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
use App\Services\AuditoriaService;
use App\Services\Captcha\CaptchaInternoService;
use App\Services\Captcha\CaptchaService;
use App\Services\SunatService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private AuditoriaService $auditoria) {}

    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return ApiResponse::error('Las credenciales no son válidas.', null, 401);
        }

        $request->session()->regenerate();

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

    public function recuperarPassword(RecuperarPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        return ApiResponse::success(null, 'Si el correo existe, se envió un enlace de recuperación.');
    }

    public function restablecerPassword(RestablecerPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->password = $password;
                $user->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return ApiResponse::error('El enlace de recuperación no es válido o expiró.', null, 422);
        }

        return ApiResponse::success(null, 'Contraseña restablecida.');
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

        return ApiResponse::success($this->construirMe($usuario->fresh()), 'Cuenta registrada correctamente.', 201);
    }

    private function motivoSuspension(User $user): ?string
    {
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
            ->values();

        $menu = Modulo::whereIn('MODU_CODIGO', $modulosPermitidos)
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

        if ($rol === 'ENTIDAD' && $user->entidad) {
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
        ];
    }
}
