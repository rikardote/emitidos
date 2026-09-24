<?php
declare(strict_types=1);

class Parsers
{
    public const CUENTA_CONSTANTE = '120866091';

    /**
     * Parsea una línea o contenido completo de emitidos.txt
     * Retorna un generador o array de registros normalizados.
     */
    public static function parseEmitidos(string $content, string $loteId, string $filename = 'emitidos.txt'): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $content);
        $records = [];

        foreach ($lines as $lineNum => $line) {
            $line = rtrim($line, "\r\n");
            if (empty(trim($line))) {
                continue;
            }

            // Mínimo de caracteres esperado para extraer los campos clave
            if (strlen($line) < 33) {
                continue;
            }

            $cuenta = substr($line, 0, 9);
            if (empty(trim($cuenta))) {
                $cuenta = self::CUENTA_CONSTANTE;
            }

            $docRaw = substr($line, 9, 10);
            $cleanDoc = ltrim($docRaw, '0');
            if ($cleanDoc === '') {
                $cleanDoc = '0';
            }

            // Diferenciación entre cheque (4 dígitos) y recibo (7 dígitos)
            $len = strlen($cleanDoc);
            $tipo = ($len <= 4) ? 'CHEQUE' : 'RECIBO';

            // Monto (14 caracteres, últimos 2 centavos)
            $montoRaw = substr($line, 19, 14);
            $monto = self::formatMonto($montoRaw);

            // Fecha de emisión (6 caracteres: DDMMAA)
            $fechaRaw = (strlen($line) >= 39) ? substr($line, 33, 6) : '';
            $fecha = self::formatFechaDDMMAA($fechaRaw);

            // Trabajador (pos 40 a 79: 40 caracteres)
            $trabajador = (strlen($line) >= 40) ? trim(substr($line, 39, 40)) : '';

            // Número de empleado (pos 80 a 86: 7 caracteres, eliminar el primer '3')
            $numEmpRaw = (strlen($line) >= 80) ? trim(substr($line, 79, 7)) : '';
            $numEmp = self::formatNumeroEmpleado($numEmpRaw);

            $records[] = [
                'lote_id' => $loteId,
                'archivo_origen' => $filename,
                'tipo' => $tipo,
                'cuenta' => $cuenta,
                'numero_documento' => $cleanDoc,
                'monto' => $monto,
                'fecha_emision' => $fecha,
                'numero_persona' => $numEmp,
                'nombre_persona' => $trabajador,
            ];
        }

        return $records;
    }

    /**
     * Parsea el contenido de libera_pension_des.txt (CSV)
     */
    public static function parseLiberaPension(string $content, string $loteId, string $filename = 'libera_pension_des.txt'): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $content);
        $records = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $cols = str_getcsv($line);
            if (count($cols) < 3) {
                continue;
            }

            $fecha = trim($cols[0] ?? '');
            $docRaw = trim($cols[1] ?? '');
            $cleanDoc = ltrim($docRaw, '0');
            if ($cleanDoc === '') {
                $cleanDoc = '0';
            }

            // Diferenciación de tipo por longitud (4 dígitos = cheque, 7 dígitos = recibo)
            $len = strlen($cleanDoc);
            $tipo = ($len <= 4) ? 'CHEQUE' : 'RECIBO';

            // Monto (últimos 2 dígitos centavos)
            $montoRaw = trim($cols[2] ?? '0');
            $monto = self::formatMonto($montoRaw);

            $numBeneficiaria = trim($cols[3] ?? '');
            $beneficiaria = trim($cols[4] ?? '');

            $records[] = [
                'lote_id' => $loteId,
                'archivo_origen' => $filename,
                'tipo' => $tipo,
                'cuenta' => self::CUENTA_CONSTANTE,
                'numero_documento' => $cleanDoc,
                'monto' => $monto,
                'fecha_emision' => $fecha,
                'numero_persona' => $numBeneficiaria,
                'nombre_persona' => $beneficiaria,
            ];
        }

        return $records;
    }

    /**
     * Convierte string numérico a float considerando últimos 2 dígitos como centavos.
     */
    private static function formatMonto(string $raw): float
    {
        $digits = preg_replace('/\D/', '', $raw);
        if ($digits === '' || $digits === null) {
            return 0.0;
        }

        if (strlen($digits) === 1) {
            return (float)('0.0' . $digits);
        }
        if (strlen($digits) === 2) {
            return (float)('0.' . $digits);
        }

        $enteros = substr($digits, 0, -2);
        $centavos = substr($digits, -2);
        return (float)($enteros . '.' . $centavos);
    }

    /**
     * Convierte DDMMAA a DD/MM/AAAA
     */
    private static function formatFechaDDMMAA(string $raw): string
    {
        $raw = trim($raw);
        if (strlen($raw) === 6 && ctype_digit($raw)) {
            $dia = substr($raw, 0, 2);
            $mes = substr($raw, 2, 2);
            $anio = '20' . substr($raw, 4, 2);
            return "{$dia}/{$mes}/{$anio}";
        }
        return $raw;
    }

    /**
     * Si empieza con '3', elimina el primer dígito '3'
     */
    private static function formatNumeroEmpleado(string $raw): string
    {
        $raw = trim($raw);
        if (str_starts_with($raw, '3')) {
            return substr($raw, 1);
        }
        return $raw;
    }
}
