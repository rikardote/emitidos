<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/Parsers.php';
require_once __DIR__ . '/lib/SimpleXlsx.php';

$db = Database::getInstance();
$formato = $_GET['formato'] ?? 'xlsx';
$tipo = $_GET['tipo'] ?? 'todos'; // cheques, recibos, todos
$detalle = isset($_GET['detalle']) && $_GET['detalle'] === '1';

// ----------------------------------------------------
// 1. Exportación en formato TXT (emitidos.txt y emitidos_spei.txt)
// ----------------------------------------------------
if ($formato === 'txt') {
    if ($tipo === 'cheques') {
        $filename = 'emitidos.txt';
        $records = $db->getDocumentosPorTipo('CHEQUE');
    } else {
        $filename = 'emitidos_spei.txt';
        $records = $db->getDocumentosPorTipo('RECIBO');
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

if ($tipo === 'cheques' || $tipo === 'todos') {
    $chequesData = $db->getDocumentosPorTipo('CHEQUE');
    $sheetCheques = [];

    if ($detalle) {
        $sheetCheques[] = ['Cuenta', 'Cheque', 'Monto', 'Fecha Emisión', 'No. Empleado / Beneficiaria', 'Nombre'];
        foreach ($chequesData as $row) {
            $sheetCheques[] = [
                (string)$row['cuenta'],
                (int)$row['numero_documento'],
                (float)$row['monto'],
                $row['fecha_emision'],
                $row['numero_persona'],
                $row['nombre_persona'],
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

if ($tipo === 'recibos' || $tipo === 'todos') {
    $recibosData = $db->getDocumentosPorTipo('RECIBO');
    $sheetRecibos = [];

    if ($detalle) {
        $sheetRecibos[] = ['Cuenta', 'Recibo', 'Monto', 'Fecha Emisión', 'No. Empleado', 'Nombre'];
        foreach ($recibosData as $row) {
            $sheetRecibos[] = [
                (string)$row['cuenta'],
                (int)$row['numero_documento'],
                (float)$row['monto'],
                $row['fecha_emision'],
                $row['numero_persona'],
                $row['nombre_persona'],
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

$suffix = ($tipo === 'cheques') ? 'Cheques' : (($tipo === 'recibos') ? 'Recibos' : 'Nomina');
$filename = $suffix . '_' . date('Ymd_His') . ($detalle ? '_Detallado' : '') . '.xlsx';
$xlsx->download($filename);
