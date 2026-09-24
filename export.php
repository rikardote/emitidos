<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/Parsers.php';
require_once __DIR__ . '/lib/SimpleXlsx.php';

$db = Database::getInstance();
$formato = $_GET['formato'] ?? 'xlsx';
$tipo = $_GET['tipo'] ?? 'todos'; // cheques, recibos, pension, todos
$detalle = isset($_GET['detalle']) && $_GET['detalle'] === '1';

// ----------------------------------------------------
// 1. Exportación en formato TXT (SOLO para emitidos)
// ----------------------------------------------------
if ($formato === 'txt') {
    if ($tipo === 'cheques') {
        $filename = 'emitidos.txt';
        $records = $db->getEmitidosPorTipo('CHEQUE');
    } else {
        $filename = 'emitidos_spei.txt';
        $records = $db->getEmitidosPorTipo('RECIBO');
    }

    $lines = [];
    foreach ($records as $doc) {
        $lines[] = Parsers::toEmitidosLine($doc);
    }

    $content = implode("\r\n", $lines) . "\r\n";

    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($content));
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    echo $content;
    exit;
}

// ----------------------------------------------------
// 2. Exportación en formato EXCEL (.xlsx)
// ----------------------------------------------------
$xlsx = new SimpleXlsx();

// Hoja Cheques (solo emitidos)
if ($tipo === 'cheques' || $tipo === 'todos') {
    $chequesData = $db->getEmitidosPorTipo('CHEQUE');
    $sheetCheques = [];

    if ($detalle) {
        $sheetCheques[] = ['Cuenta', 'Cheque', 'Monto', 'Fecha Emisión', 'No. Empleado', 'Nombre Trabajador'];
        foreach ($chequesData as $row) {
            $sheetCheques[] = [
                (string)$row['cuenta'],
                (int)$row['numero_documento'],
                (float)$row['monto'],
                $row['fecha_emision'],
                $row['numero_empleado'],
                $row['nombre_empleado'],
            ];
        }
    } else {
        $sheetCheques[] = ['Cuenta', 'Cheque', 'Monto'];
        foreach ($chequesData as $row) {
            $sheetCheques[] = [
                (string)$row['cuenta'],
                (int)$row['numero_documento'],
                (float)$row['monto'],
            ];
        }
    }
    $xlsx->addSheet('Cheques', $sheetCheques);
}

// Hoja Recibos (solo emitidos)
if ($tipo === 'recibos' || $tipo === 'todos') {
    $recibosData = $db->getEmitidosPorTipo('RECIBO');
    $sheetRecibos = [];

    if ($detalle) {
        $sheetRecibos[] = ['Cuenta', 'Recibo', 'Monto', 'Fecha Emisión', 'No. Empleado', 'Nombre Trabajador'];
        foreach ($recibosData as $row) {
            $sheetRecibos[] = [
                (string)$row['cuenta'],
                (int)$row['numero_documento'],
                (float)$row['monto'],
                $row['fecha_emision'],
                $row['numero_empleado'],
                $row['nombre_empleado'],
            ];
        }
    } else {
        $sheetRecibos[] = ['Cuenta', 'Recibo', 'Monto'];
        foreach ($recibosData as $row) {
            $sheetRecibos[] = [
                (string)$row['cuenta'],
                (int)$row['numero_documento'],
                (float)$row['monto'],
            ];
        }
    }
    $xlsx->addSheet('Recibos', $sheetRecibos);
}

// Hoja Pensión Alimenticia (totalmente independiente)
if ($tipo === 'pension' || ($tipo === 'todos')) {
    $pensionData = $db->getPensiones();
    if (!empty($pensionData)) {
        $sheetPension = [];
        if ($detalle) {
            $sheetPension[] = ['Cuenta', 'Cheque', 'Monto', 'Fecha Emisión', 'No. Beneficiaria', 'Nombre Beneficiaria'];
            foreach ($pensionData as $row) {
                $sheetPension[] = [
                    (string)$row['cuenta'],
                    (int)$row['cheque'],
                    (float)$row['monto'],
                    $row['fecha_emision'],
                    $row['numero_beneficiaria'],
                    $row['beneficiaria'],
                ];
            }
        } else {
            $sheetPension[] = ['Cuenta', 'Cheque', 'Monto'];
            foreach ($pensionData as $row) {
                $sheetPension[] = [
                    (string)$row['cuenta'],
                    (int)$row['cheque'],
                    (float)$row['monto'],
                ];
            }
        }
        $xlsx->addSheet('Pensión', $sheetPension);
    }
}

$suffix = ($tipo === 'cheques') ? 'Cheques' : (($tipo === 'recibos') ? 'Recibos' : (($tipo === 'pension') ? 'Pension' : 'Nomina'));
$filename = $suffix . '_' . date('Ymd_His') . ($detalle ? '_Detallado' : '') . '.xlsx';
$xlsx->download($filename);
