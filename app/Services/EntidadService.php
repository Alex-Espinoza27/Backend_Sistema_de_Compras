<?php

namespace App\Services;

use App\Models\Entidad;
use App\Models\Estado;
use App\Models\TipoDocumento;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Ciclo de vida de ENTIDAD (02B M02): solicitud pública → aprobar/observar/
 * rechazar por la muni → suspender/reactivar. Los cambios de estado pasan
 * por EstadoService; el correo con credenciales se envía después de que la
 * transacción confirma (nunca dentro de ella).
 */
class EntidadService
{
    public function __construct(
        private EstadoService $estadoService,
        private AuditoriaService $auditoria,
        private NotificacionService $notificacion,
    ) {}

    public function solicitar(array $datos): Entidad
    {
        $entidad = DB::transaction(function () use ($datos) {
            $entidad = $this->crearFilaEntidad($datos, 'SOLICITADA');

            $this->auditoria->registrar('ENTIDADES', 'CREAR', 'ENTIDAD', $entidad->ENTI_ID);

            return $entidad;
        });

        $this->notificacion->notificarAdministradores(
            'Nueva solicitud de alta de entidad',
            "La entidad \"{$entidad->ENTI_RAZON_SOCIAL}\" ({$entidad->ENTI_NUMERO_DOCUMENTO}) solicitó darse de alta en la plataforma.",
        );

        return $entidad;
    }

    public function altaDirecta(array $datos): Entidad
    {
        [$entidad, $usuario] = DB::transaction(function () use ($datos) {
            $entidad = $this->crearFilaEntidad($datos, 'APROBADA');
            $entidad->ENTI_FECHA_RESOLUCION = now();
            $entidad->ENTI_RESUELTA_POR = auth()->id();
            $entidad->save();

            $usuario = $this->crearFilaUsuario($entidad, $datos['usuario_inicial']);

            $this->auditoria->registrar('ENTIDADES', 'CREAR', 'ENTIDAD', $entidad->ENTI_ID);

            return [$entidad, $usuario];
        });

        Password::sendResetLink(['email' => $usuario->email]);

        return $entidad;
    }

    public function aprobar(Entidad $entidad, ?array $usuarioInicial): Entidad
    {
        $estadoCodigo = Estado::where('ESTA_ID', $entidad->ENTI_ESTADO_ID)->value('ESTA_CODIGO');

        if (! in_array($estadoCodigo, ['SOLICITADA', 'OBSERVADA'], true)) {
            throw new HttpException(409, 'Esta entidad no está en un estado que permita aprobarla.');
        }

        $tieneUsuarios = User::where('entidad_id', $entidad->ENTI_ID)->exists();

        if (! $tieneUsuarios && ! $usuarioInicial) {
            throw new HttpException(422, 'Debe indicar los datos del usuario inicial.');
        }

        $usuarioCreado = null;

        DB::transaction(function () use ($entidad, $usuarioInicial, $tieneUsuarios, &$usuarioCreado) {
            $entidad->ENTI_FECHA_RESOLUCION = now();
            $entidad->ENTI_RESUELTA_POR = auth()->id();
            $entidad->save();

            $this->estadoService->transicionar(
                modelo: $entidad,
                columnaEstado: 'ENTI_ESTADO_ID',
                contexto: 'ENTIDAD',
                codigoDestino: 'APROBADA',
                transicionesPermitidas: ['SOLICITADA' => ['APROBADA'], 'OBSERVADA' => ['APROBADA']],
                modulo: 'ENTIDADES',
                tabla: 'ENTIDAD',
            );

            if (! $tieneUsuarios) {
                $usuarioCreado = $this->crearFilaUsuario($entidad, $usuarioInicial);
            }
        });

        if ($usuarioCreado) {
            Password::sendResetLink(['email' => $usuarioCreado->email]);
        }

        return $entidad->fresh();
    }

    public function observar(Entidad $entidad, string $motivo): Entidad
    {
        DB::transaction(function () use ($entidad, $motivo) {
            $entidad->ENTI_MOTIVO_OBSERVACION = $motivo;
            $entidad->save();

            $this->estadoService->transicionar(
                modelo: $entidad,
                columnaEstado: 'ENTI_ESTADO_ID',
                contexto: 'ENTIDAD',
                codigoDestino: 'OBSERVADA',
                transicionesPermitidas: ['SOLICITADA' => ['OBSERVADA']],
                modulo: 'ENTIDADES',
                tabla: 'ENTIDAD',
                datosAdicionales: ['motivo' => $motivo],
            );
        });

        $this->notificacion->enviarCorreo(
            $entidad->ENTI_CORREO,
            'Su solicitud de alta requiere subsanación',
            "Motivo: {$motivo}",
        );

        return $entidad->fresh();
    }

    public function rechazar(Entidad $entidad, string $motivo): Entidad
    {
        DB::transaction(function () use ($entidad, $motivo) {
            $entidad->ENTI_MOTIVO_OBSERVACION = $motivo;
            $entidad->save();

            $this->estadoService->transicionar(
                modelo: $entidad,
                columnaEstado: 'ENTI_ESTADO_ID',
                contexto: 'ENTIDAD',
                codigoDestino: 'RECHAZADA',
                transicionesPermitidas: ['SOLICITADA' => ['RECHAZADA']],
                modulo: 'ENTIDADES',
                tabla: 'ENTIDAD',
                datosAdicionales: ['motivo' => $motivo],
            );
        });

        $this->notificacion->enviarCorreo(
            $entidad->ENTI_CORREO,
            'Su solicitud de alta fue rechazada',
            "Motivo: {$motivo}",
        );

        return $entidad->fresh();
    }

    public function suspender(Entidad $entidad, string $motivo): Entidad
    {
        DB::transaction(function () use ($entidad, $motivo) {
            $entidad->ENTI_MOTIVO_OBSERVACION = $motivo;
            $entidad->save();

            $this->estadoService->transicionar(
                modelo: $entidad,
                columnaEstado: 'ENTI_ESTADO_ID',
                contexto: 'ENTIDAD',
                codigoDestino: 'SUSPENDIDA',
                transicionesPermitidas: ['APROBADA' => ['SUSPENDIDA']],
                modulo: 'ENTIDADES',
                tabla: 'ENTIDAD',
                datosAdicionales: ['motivo' => $motivo],
            );
        });

        $this->notificacion->enviarCorreo(
            $entidad->ENTI_CORREO,
            'Su entidad ha sido suspendida',
            "Motivo: {$motivo}",
        );

        return $entidad->fresh();
    }

    public function reactivar(Entidad $entidad): Entidad
    {
        DB::transaction(function () use ($entidad) {
            $this->estadoService->transicionar(
                modelo: $entidad,
                columnaEstado: 'ENTI_ESTADO_ID',
                contexto: 'ENTIDAD',
                codigoDestino: 'APROBADA',
                transicionesPermitidas: ['SUSPENDIDA' => ['APROBADA']],
                modulo: 'ENTIDADES',
                tabla: 'ENTIDAD',
            );
        });

        return $entidad->fresh();
    }

    public function crearUsuario(Entidad $entidad, array $datos): User
    {
        $usuario = DB::transaction(function () use ($entidad, $datos) {
            $usuario = $this->crearFilaUsuario($entidad, $datos);

            $this->auditoria->registrar('ENTIDADES', 'CREAR', 'users', $usuario->id);

            return $usuario;
        });

        Password::sendResetLink(['email' => $usuario->email]);

        return $usuario;
    }

    public function actualizarUsuario(User $usuario, array $datos): User
    {
        $valorAnterior = $usuario->only(['name', 'email']);

        $usuario->update(['name' => $datos['nombre'], 'email' => $datos['correo']]);

        $this->auditoria->registrar('ENTIDADES', 'ACTUALIZAR', 'users', $usuario->id, $valorAnterior, $usuario->only(['name', 'email']));

        return $usuario;
    }

    public function eliminarUsuario(User $usuario): void
    {
        $userId = $usuario->id;

        $usuario->delete();

        $this->auditoria->registrar('ENTIDADES', 'ELIMINAR', 'users', $userId);
    }

    private function crearFilaEntidad(array $datos, string $codigoEstadoInicial): Entidad
    {
        $tipoDocumentoId = TipoDocumento::where('TIDO_CODIGO', 'RUC')->value('TIDO_ID');
        $estadoId = Estado::where('ESTA_CONTEXTO', 'ENTIDAD')->where('ESTA_CODIGO', $codigoEstadoInicial)->value('ESTA_ID');

        return Entidad::create([
            'ENTI_TIPO_DOCUMENTO_ID' => $tipoDocumentoId,
            'ENTI_NUMERO_DOCUMENTO' => $datos['numero_documento'],
            'ENTI_RAZON_SOCIAL' => $datos['razon_social'],
            'ENTI_SIGLA' => $datos['sigla'],
            'ENTI_CORREO' => $datos['correo'],
            'ENTI_TELEFONO' => $datos['telefono'],
            'ENTI_DIRECCION' => $datos['direccion'],
            'ENTI_PERSONA_CONTACTO' => $datos['persona_contacto'],
            'ENTI_ESTADO_ID' => $estadoId,
            'ENTI_FECHA_SOLICITUD' => now(),
        ]);
    }

    private function crearFilaUsuario(Entidad $entidad, array $datos): User
    {
        $usuario = User::create([
            'name' => $datos['nombre'],
            'email' => $datos['correo'],
            'password' => Str::random(32),
            'entidad_id' => $entidad->ENTI_ID,
        ]);

        $usuario->assignRole('ENTIDAD');

        return $usuario;
    }
}
