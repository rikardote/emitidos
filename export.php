<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/SimpleXlsx.php';

$db = Database::getInstance();
$detalle = isset($_GET['detalle']) && $_GET['detalle'] === '1';

$chequesData = $db->getDocumentosPorTipo('CHEQUE');
$recibosData = $db->getDocumentosPorTipo('RECIBO');

$xlsx = new SimpleXlsx();

// Hoja 1: Cheques
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

// Hoja 2: Recibos
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

$filename = 'Nomina_' . date('Ymd_His') . ($detalle ? '_Detallado' : '') . '.xlsx';
$xlsx->download($filename);
