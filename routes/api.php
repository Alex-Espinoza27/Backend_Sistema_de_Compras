<?php

use App\Http\Controllers\Api\V1\AdjudicacionController;
use App\Http\Controllers\Api\V1\AdminCatalogoController;
use App\Http\Controllers\Api\V1\AdminConfiguracionController;
use App\Http\Controllers\Api\V1\AdminProveedorController;
use App\Http\Controllers\Api\V1\AdminRolController;
use App\Http\Controllers\Api\V1\ArchivoController;
use App\Http\Controllers\Api\V1\AuditoriaController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ConvocatoriaController;
use App\Http\Controllers\Api\V1\GestionUsuariosController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DatosAbiertosController;
use App\Http\Controllers\Api\V1\EntidadController;
use App\Http\Controllers\Api\V1\EvaluacionController;
use App\Http\Controllers\Api\V1\NotificacionController;
use App\Http\Controllers\Api\V1\OfertaController;
use App\Http\Controllers\Api\V1\ProveedorController;
use App\Http\Controllers\Api\V1\PublicoController;
use App\Http\Controllers\Api\V1\ReporteController;
use App\Http\Controllers\Api\V1\AreaController;
use App\Http\Controllers\Api\V1\PresentacionOfertasController;
use App\Http\Controllers\Api\V1\RequerimientoController;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('/ping', function () {
        return ApiResponse::success(['pong' => true]);
    });

    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');
        Route::post('/recuperar-password', [AuthController::class, 'recuperarPassword'])->middleware('throttle:auth');
        Route::post('/restablecer-password', [AuthController::class, 'restablecerPassword'])->middleware('throttle:auth');

        Route::get('/captcha', [AuthController::class, 'captcha'])->middleware('throttle:auth');
        Route::post('/registro/consultar-ruc', [AuthController::class, 'consultarRuc'])->middleware('throttle:auth');
        Route::post('/registro', [AuthController::class, 'registro'])->middleware('throttle:auth');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/cambiar-password', [AuthController::class, 'cambiarPassword']);
        });
    });

    Route::prefix('proveedores')->middleware(['auth:sanctum', 'role:PROVEEDOR'])->group(function () {
        Route::get('/mis-datos', [ProveedorController::class, 'misDatos'])->middleware('permission:proveedores.ver_propio');
        Route::put('/mis-datos', [ProveedorController::class, 'actualizarMisDatos'])->middleware('permission:proveedores.editar_propio');
        Route::put('/mis-rubros', [ProveedorController::class, 'actualizarMisRubros'])->middleware('permission:proveedores.editar_propio');
        Route::post('/suspender', [ProveedorController::class, 'suspender'])->middleware('permission:proveedores.suspender_propio');
    });

    Route::prefix('publico')->middleware('throttle:publico')->group(function () {
        Route::get('/entidades', [PublicoController::class, 'entidades']);
        Route::post('/entidades/solicitud', [PublicoController::class, 'solicitudEntidad']);
        Route::get('/rubros', [PublicoController::class, 'rubros']);
        Route::get('/convocatorias', [PublicoController::class, 'convocatorias']);
        Route::get('/convocatorias/{id}', [PublicoController::class, 'convocatoria'])->whereNumber('id');
        Route::get('/convocatorias/{id}/tdr', [PublicoController::class, 'tdr'])->whereNumber('id');
        Route::get('/convocatorias/{id}/resultados.pdf', [PublicoController::class, 'resultadosPdf'])->whereNumber('id');
    });

    Route::prefix('datos-abiertos')->middleware('throttle:publico')->group(function () {
        Route::get('/convocatorias.csv', [DatosAbiertosController::class, 'convocatoriasCsv'])->middleware('throttle:exportar');
        Route::get('/convocatorias.xlsx', [DatosAbiertosController::class, 'convocatoriasXlsx'])->middleware('throttle:exportar');
        Route::get('/adjudicaciones.csv', [DatosAbiertosController::class, 'adjudicacionesCsv'])->middleware('throttle:exportar');
        Route::get('/adjudicaciones.xlsx', [DatosAbiertosController::class, 'adjudicacionesXlsx'])->middleware('throttle:exportar');
        Route::get('/estadisticas', [DatosAbiertosController::class, 'estadisticas']);
    });

    Route::prefix('requerimientos')->middleware('auth:sanctum')->group(function () {
        Route::get('/combo', [RequerimientoController::class, 'combo'])->middleware('permission:convocatorias.crear');
        Route::get('/unidades-medida', [RequerimientoController::class, 'unidadesMedida'])->middleware('permission:requerimientos.ver');
        Route::get('/', [RequerimientoController::class, 'index'])->middleware('permission:requerimientos.ver');
        Route::get('/{id}', [RequerimientoController::class, 'show'])->whereNumber('id')->middleware('permission:requerimientos.ver');
        Route::post('/', [RequerimientoController::class, 'store'])->middleware('permission:requerimientos.crear');
        Route::put('/{id}', [RequerimientoController::class, 'update'])->whereNumber('id')->middleware('permission:requerimientos.editar');
        Route::delete('/{id}', [RequerimientoController::class, 'destroy'])->whereNumber('id')->middleware('permission:requerimientos.eliminar');
        Route::delete('/{id}/tdr/{archivoId}', [RequerimientoController::class, 'eliminarTdr'])->whereNumber(['id', 'archivoId'])->middleware('permission:requerimientos.editar');
    });

    Route::prefix('areas')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [AreaController::class, 'index'])->middleware('permission:areas.ver');
        Route::post('/', [AreaController::class, 'store'])->middleware('permission:areas.gestionar');
        Route::put('/{id}', [AreaController::class, 'update'])->whereNumber('id')->middleware('permission:areas.gestionar');
    });

    Route::prefix('convocatorias')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [ConvocatoriaController::class, 'index'])->middleware('permission:convocatorias.ver');
        Route::get('/{id}', [ConvocatoriaController::class, 'show'])->whereNumber('id')->middleware('permission:convocatorias.ver');
        Route::post('/', [ConvocatoriaController::class, 'store'])->middleware('permission:convocatorias.crear');
        Route::put('/{id}', [ConvocatoriaController::class, 'update'])->whereNumber('id')->middleware('permission:convocatorias.editar');
        Route::delete('/{id}/plantilla-dj/{archivoId}', [ConvocatoriaController::class, 'eliminarPlantillaDj'])->whereNumber(['id', 'archivoId'])->middleware('permission:convocatorias.editar');
        Route::post('/{id}/publicar', [ConvocatoriaController::class, 'publicar'])->whereNumber('id')->middleware('permission:convocatorias.publicar');
        Route::post('/{id}/cancelar', [ConvocatoriaController::class, 'cancelar'])->whereNumber('id')->middleware('permission:convocatorias.cancelar');
        Route::get('/{id}/postulacion', [OfertaController::class, 'postulacion'])->whereNumber('id')->middleware(['role:PROVEEDOR', 'permission:ofertas.crear']);
        Route::get('/{id}/ofertas', [EvaluacionController::class, 'ofertas'])->whereNumber('id')->middleware('permission:evaluacion.ver');
        Route::get('/{id}/cuadro-comparativo', [EvaluacionController::class, 'cuadroComparativo'])->whereNumber('id')->middleware('permission:evaluacion.ver');
        Route::post('/{id}/adjudicar', [AdjudicacionController::class, 'adjudicar'])->whereNumber('id')->middleware('permission:adjudicacion.adjudicar');
        Route::post('/{id}/desierta', [AdjudicacionController::class, 'desierta'])->whereNumber('id')->middleware('permission:adjudicacion.declarar_desierta');
        Route::get('/{id}/resultados', [AdjudicacionController::class, 'resultados'])->whereNumber('id')->middleware('permission:adjudicacion.ver');
    });

    Route::prefix('presentacion-ofertas')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [PresentacionOfertasController::class, 'index'])->middleware('permission:presentacion_ofertas.ver');
        Route::get('/{id}', [PresentacionOfertasController::class, 'show'])->whereNumber('id')->middleware('permission:presentacion_ofertas.ver');
    });

    Route::prefix('ofertas')->middleware(['auth:sanctum', 'role:PROVEEDOR'])->group(function () {
        Route::post('/', [OfertaController::class, 'store'])->middleware('permission:ofertas.crear');
        Route::get('/resumen', [OfertaController::class, 'resumen'])->middleware('permission:ofertas.ver');
        Route::get('/', [OfertaController::class, 'index'])->middleware('permission:ofertas.ver');
        Route::get('/{id}', [OfertaController::class, 'show'])->whereNumber('id')->middleware('permission:ofertas.ver');
        Route::delete('/{id}', [OfertaController::class, 'destroy'])->whereNumber('id')->middleware('permission:ofertas.retirar');
        Route::get('/{id}/constancia.pdf', [OfertaController::class, 'constanciaPdf'])->whereNumber('id')->middleware('permission:ofertas.ver');
    });

    Route::prefix('ofertas')->middleware('auth:sanctum')->group(function () {
        Route::get('/{id}/evaluacion', [EvaluacionController::class, 'evaluacionDetalle'])->whereNumber('id')->middleware('permission:evaluacion.ver');
        Route::post('/{id}/evaluar', [EvaluacionController::class, 'evaluar'])->whereNumber('id')->middleware('permission:evaluacion.evaluar');
    });

    Route::get('/archivos/{id}/descargar', [ArchivoController::class, 'descargar'])->whereNumber('id')->middleware('auth:sanctum');

    Route::prefix('entidades')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [EntidadController::class, 'index'])->middleware('permission:entidades.ver');
        Route::post('/', [EntidadController::class, 'store'])->middleware('permission:entidades.gestionar');
        Route::get('/{id}', [EntidadController::class, 'show'])->whereNumber('id')->middleware('permission:entidades.ver');
        Route::post('/{id}/aprobar', [EntidadController::class, 'aprobar'])->whereNumber('id')->middleware('permission:entidades.aprobar');
        Route::post('/{id}/observar', [EntidadController::class, 'observar'])->whereNumber('id')->middleware('permission:entidades.aprobar');
        Route::post('/{id}/rechazar', [EntidadController::class, 'rechazar'])->whereNumber('id')->middleware('permission:entidades.aprobar');
        Route::post('/{id}/suspender', [EntidadController::class, 'suspender'])->whereNumber('id')->middleware('permission:entidades.gestionar');
        Route::post('/{id}/reactivar', [EntidadController::class, 'reactivar'])->whereNumber('id')->middleware('permission:entidades.gestionar');
        Route::get('/{id}/usuarios', [EntidadController::class, 'usuarios'])->whereNumber('id')->middleware('permission:entidades.gestionar');
        Route::post('/{id}/usuarios', [EntidadController::class, 'storeUsuario'])->whereNumber('id')->middleware('permission:entidades.gestionar');
        Route::put('/{id}/usuarios/{userId}', [EntidadController::class, 'updateUsuario'])->whereNumber(['id', 'userId'])->middleware('permission:entidades.gestionar');
        Route::delete('/{id}/usuarios/{userId}', [EntidadController::class, 'destroyUsuario'])->whereNumber(['id', 'userId'])->middleware('permission:entidades.gestionar');
    });

    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth:sanctum', 'permission:dashboard.ver']);

    Route::prefix('reportes')->middleware('auth:sanctum')->group(function () {
        Route::post('/buscar', [ReporteController::class, 'buscar'])->middleware('permission:reportes.ver');
        Route::get('/exportar.xlsx', [ReporteController::class, 'exportarXlsx'])->middleware(['permission:reportes.exportar', 'throttle:exportar']);
        Route::get('/exportar.pdf', [ReporteController::class, 'exportarPdf'])->middleware(['permission:reportes.exportar', 'throttle:exportar']);
    });

    Route::prefix('auditoria')->middleware(['auth:sanctum', 'permission:auditoria.ver'])->group(function () {
        Route::get('/exportar.xlsx', [AuditoriaController::class, 'exportarXlsx'])->middleware('throttle:exportar');
        Route::get('/', [AuditoriaController::class, 'index']);
        Route::get('/{id}', [AuditoriaController::class, 'show'])->whereNumber('id');
    });

    Route::prefix('notificaciones')->middleware('auth:sanctum')->group(function () {
        Route::get('/contador', [NotificacionController::class, 'contador']);
        Route::get('/', [NotificacionController::class, 'index']);
        Route::post('/leer-todas', [NotificacionController::class, 'leerTodas']);
        Route::post('/{id}/leer', [NotificacionController::class, 'leer'])->whereNumber('id');
    });

    Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
        // Usuarios de plataforma (05C hardening — módulo unificado: internos + Entidad, ver GestionUsuariosController).
        Route::get('/usuarios-plataforma', [GestionUsuariosController::class, 'index'])->middleware('permission:gestion_usuarios.ver');
        Route::post('/usuarios-plataforma', [GestionUsuariosController::class, 'store'])->middleware('permission:gestion_usuarios.gestionar');
        Route::get('/usuarios-plataforma/{id}', [GestionUsuariosController::class, 'show'])->whereNumber('id')->middleware('permission:gestion_usuarios.ver');
        Route::put('/usuarios-plataforma/{id}', [GestionUsuariosController::class, 'update'])->whereNumber('id')->middleware('permission:gestion_usuarios.gestionar');
        Route::delete('/usuarios-plataforma/{id}', [GestionUsuariosController::class, 'destroy'])->whereNumber('id')->middleware('permission:gestion_usuarios.gestionar');

        // Acepta también reportes.ver: AUDITOR/ENTIDAD no tienen gestion_proveedores.ver
        // pero sí necesitan este listado como combo del filtro proveedor_id en Reportes (J2).
        Route::get('/proveedores', [AdminProveedorController::class, 'index'])->middleware('permission:gestion_proveedores.ver|reportes.ver');
        Route::get('/proveedores/{id}', [AdminProveedorController::class, 'show'])->whereNumber('id')->middleware('permission:gestion_proveedores.ver');
        Route::post('/proveedores', [AdminProveedorController::class, 'store'])->middleware('permission:gestion_proveedores.gestionar');
        Route::put('/proveedores/{id}', [AdminProveedorController::class, 'update'])->whereNumber('id')->middleware('permission:gestion_proveedores.gestionar');
        Route::post('/proveedores/{id}/reactivar', [AdminProveedorController::class, 'reactivar'])->whereNumber('id')->middleware('permission:gestion_proveedores.gestionar');
        Route::post('/proveedores/{id}/suspender', [AdminProveedorController::class, 'suspender'])->whereNumber('id')->middleware('permission:gestion_proveedores.gestionar');

        Route::get('/roles', [AdminRolController::class, 'roles'])->middleware('permission:roles_permisos.ver');
        Route::get('/permisos', [AdminRolController::class, 'permisos'])->middleware('permission:roles_permisos.ver');
        Route::post('/roles', [AdminRolController::class, 'crear'])->middleware('permission:roles_permisos.gestionar');
        Route::put('/roles/{rol}/permisos', [AdminRolController::class, 'actualizarPermisos'])->middleware('permission:roles_permisos.gestionar');
        Route::delete('/roles/{rol}', [AdminRolController::class, 'eliminar'])->middleware('permission:roles_permisos.gestionar');

        Route::get('/configuracion', [AdminConfiguracionController::class, 'index'])->middleware('permission:administracion.configuracion');
        Route::put('/configuracion', [AdminConfiguracionController::class, 'update'])->middleware('permission:administracion.configuracion');
        Route::get('/uit', [AdminConfiguracionController::class, 'uitIndex'])->middleware('permission:administracion.configuracion');
        Route::post('/uit', [AdminConfiguracionController::class, 'uitStore'])->middleware('permission:administracion.configuracion');
        Route::put('/uit/{id}', [AdminConfiguracionController::class, 'uitUpdate'])->whereNumber('id')->middleware('permission:administracion.configuracion');

        Route::middleware('permission:administracion.catalogos')->group(function () {
            Route::get('/rubros', [AdminCatalogoController::class, 'rubrosIndex']);
            Route::post('/rubros', [AdminCatalogoController::class, 'rubrosStore']);
            Route::put('/rubros/{id}', [AdminCatalogoController::class, 'rubrosUpdate'])->whereNumber('id');
            Route::delete('/rubros/{id}', [AdminCatalogoController::class, 'rubrosDestroy'])->whereNumber('id');

            Route::get('/unidades-medida', [AdminCatalogoController::class, 'unidadesMedidaIndex']);
            Route::post('/unidades-medida', [AdminCatalogoController::class, 'unidadesMedidaStore']);
            Route::put('/unidades-medida/{id}', [AdminCatalogoController::class, 'unidadesMedidaUpdate'])->whereNumber('id');
            Route::delete('/unidades-medida/{id}', [AdminCatalogoController::class, 'unidadesMedidaDestroy'])->whereNumber('id');

            Route::get('/tipos-comprobante', [AdminCatalogoController::class, 'tiposComprobanteIndex']);
            Route::post('/tipos-comprobante', [AdminCatalogoController::class, 'tiposComprobanteStore']);
            Route::put('/tipos-comprobante/{id}', [AdminCatalogoController::class, 'tiposComprobanteUpdate'])->whereNumber('id');
            Route::delete('/tipos-comprobante/{id}', [AdminCatalogoController::class, 'tiposComprobanteDestroy'])->whereNumber('id');

            Route::get('/estados', [AdminCatalogoController::class, 'estadosIndex']);
            Route::post('/estados', [AdminCatalogoController::class, 'estadosStore']);
            Route::put('/estados/{id}', [AdminCatalogoController::class, 'estadosUpdate'])->whereNumber('id');
            Route::delete('/estados/{id}', [AdminCatalogoController::class, 'estadosDestroy'])->whereNumber('id');
        });
    });
});
