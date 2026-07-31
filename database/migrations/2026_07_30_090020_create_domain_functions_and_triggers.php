<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Funciones y trigger de 01 §6. La transición PUBLICADA→EN_EVALUACION se
 * orquesta desde la aplicación (para auditar), pero la usa vía esta función;
 * fn_oferta_recalcular_total la invoca el servicio de ofertas tras escribir
 * ítems (no es un trigger, a diferencia de trg_oferta_item_precio_total).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION fn_oferta_recalcular_total(p_oferta_id BIGINT)
            RETURNS VOID AS $$
            BEGIN
                UPDATE "OFERTA"
                SET "OFER_TOTAL_GENERAL" = COALESCE((
                    SELECT SUM("OFIT_PRECIO_TOTAL")
                    FROM "OFERTA_ITEM"
                    WHERE "OFIT_OFERTA_ID" = p_oferta_id
                ), 0)
                WHERE "OFER_ID" = p_oferta_id;
            END;
            $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION fn_oferta_items_completos(p_oferta_id BIGINT)
            RETURNS BOOLEAN AS $$
            DECLARE
                v_requerimiento_id BIGINT;
                v_total_items INTEGER;
                v_total_cotizados INTEGER;
            BEGIN
                SELECT c."CONV_REQUERIMIENTO_ID" INTO v_requerimiento_id
                FROM "OFERTA" o
                JOIN "CONVOCATORIA" c ON c."CONV_ID" = o."OFER_CONVOCATORIA_ID"
                WHERE o."OFER_ID" = p_oferta_id;

                SELECT COUNT(*) INTO v_total_items
                FROM "REQUERIMIENTO_ITEM"
                WHERE "REIT_REQUERIMIENTO_ID" = v_requerimiento_id;

                SELECT COUNT(*) INTO v_total_cotizados
                FROM "OFERTA_ITEM" oi
                JOIN "REQUERIMIENTO_ITEM" ri ON ri."REIT_ID" = oi."OFIT_REQUERIMIENTO_ITEM_ID"
                WHERE oi."OFIT_OFERTA_ID" = p_oferta_id
                  AND oi."OFIT_PRECIO_UNITARIO" >= 0;

                RETURN v_total_items > 0 AND v_total_items = v_total_cotizados;
            END;
            $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION fn_convocatoria_vencer()
            RETURNS VOID AS $$
            DECLARE
                v_estado_publicada BIGINT;
                v_estado_en_evaluacion BIGINT;
            BEGIN
                SELECT "ESTA_ID" INTO v_estado_publicada FROM "ESTADO" WHERE "ESTA_CONTEXTO" = 'CONVOCATORIA' AND "ESTA_CODIGO" = 'PUBLICADA';
                SELECT "ESTA_ID" INTO v_estado_en_evaluacion FROM "ESTADO" WHERE "ESTA_CONTEXTO" = 'CONVOCATORIA' AND "ESTA_CODIGO" = 'EN_EVALUACION';

                UPDATE "CONVOCATORIA"
                SET "CONV_ESTADO_ID" = v_estado_en_evaluacion,
                    "CONV_FECHA_APERTURA" = now()
                WHERE "CONV_ESTADO_ID" = v_estado_publicada
                  AND "CONV_FECHA_FIN" < now();
            END;
            $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION fn_convocatoria_urgencia(p_conv_id BIGINT)
            RETURNS INTEGER AS $$
            DECLARE
                v_fecha_fin TIMESTAMPTZ;
            BEGIN
                SELECT "CONV_FECHA_FIN" INTO v_fecha_fin FROM "CONVOCATORIA" WHERE "CONV_ID" = p_conv_id;

                RETURN CEIL(EXTRACT(EPOCH FROM (v_fecha_fin - now())) / 86400)::INTEGER;
            END;
            $$ LANGUAGE plpgsql;

            CREATE OR REPLACE FUNCTION trg_oferta_item_precio_total()
            RETURNS TRIGGER AS $$
            DECLARE
                v_cantidad NUMERIC(14,2);
            BEGIN
                SELECT "REIT_CANTIDAD" INTO v_cantidad
                FROM "REQUERIMIENTO_ITEM"
                WHERE "REIT_ID" = NEW."OFIT_REQUERIMIENTO_ITEM_ID";

                NEW."OFIT_PRECIO_TOTAL" := v_cantidad * NEW."OFIT_PRECIO_UNITARIO";

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_oferta_item_precio_total_biu
            BEFORE INSERT OR UPDATE ON "OFERTA_ITEM"
            FOR EACH ROW
            EXECUTE FUNCTION trg_oferta_item_precio_total();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS trg_oferta_item_precio_total_biu ON "OFERTA_ITEM";
            DROP FUNCTION IF EXISTS trg_oferta_item_precio_total();
            DROP FUNCTION IF EXISTS fn_convocatoria_urgencia(BIGINT);
            DROP FUNCTION IF EXISTS fn_convocatoria_vencer();
            DROP FUNCTION IF EXISTS fn_oferta_items_completos(BIGINT);
            DROP FUNCTION IF EXISTS fn_oferta_recalcular_total(BIGINT);
        SQL);
    }
};
