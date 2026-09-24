<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/Parsers.php';
require_once __DIR__ . '/lib/SimpleXlsx.php';

echo "=== Procesador de Nómina (CLI) ===\n";

$db = Database::getInstance();
$db->clearAll();

$emitidosPath = __DIR__ . '/emitidos.txt';
$liberaPath = __DIR__ . '/libera_pension_des.txt';
$loteId = 'CLI_' . date('Ymd_His');

$totalEmitidos = 0;
$totalLibera = 0;

$db->getPdo()->beginTransaction();

if (file_exists($emitidosPath)) {
    echo "- Procesando " . basename($emitidosPath) . "...\n";
    $records = Parsers::parseEmitidos(file_get_contents($emitidosPath), $loteId, 'emitidos.txt');
    foreach ($records as $r) {
        $db->insertDocumento($r);
    }
    $totalEmitidos = count($records);
} else {
    echo "(!) Advertencia: No se encontró emitidos.txt en la raíz.\n";
}

if (file_exists($liberaPath)) {
    echo "- Procesando " . basename($liberaPath) . "...\n";
    $records = Parsers::parseLiberaPension(file_get_contents($liberaPath), $loteId, 'libera_pension_des.txt');
    foreach ($records as $r) {
        $db->insertDocumento($r);
    }
    $totalLibera = count($records);
} else {
    echo "(!) Advertencia: No se encontró libera_pension_des.txt en la raíz.\n";
}

$db->getPdo()->commit();

$resumen = $db->getResumen();
$chequesCount = $resumen['CHEQUE']['total'] ?? 0;
$chequesMonto = $resumen['CHEQUE']['monto'] ?? 0.0;
$recibosCount = $resumen['RECIBO']['total'] ?? 0;
$recibosMonto = $resumen['RECIBO']['monto'] ?? 0.0;

echo "\n--- Resumen de Base de Datos SQLite ---\n";
echo "Cheques (4 dígitos): {$chequesCount} registros | Monto: $" . number_format($chequesMonto, 2) . "\n";
echo "Recibos (7 dígitos): {$recibosCount} registros | Monto: $" . number_format($recibosMonto, 2) . "\n";
echo "Total general: " . ($chequesCount + $recibosCount) . " registros | Monto: $" . number_format($chequesMonto + $recibosMonto, 2) . "\n";

// Generar Excel
$outputFile = __DIR__ . '/reporte_nomina.xlsx';
echo "\n- Generando {$outputFile}...\n";

$xlsx = new SimpleXlsx();

// Hoja 1: Cheques
$sheetCheques = [
    ['Cuenta', 'Cheque', 'Monto']
];
foreach ($db->getDocumentosPorTipo('CHEQUE') as $doc) {
    $sheetCheques[] = [
        (string)$doc['cuenta'],
        (int)$doc['numero_documento'],
        (float)$doc['monto'],
    ];
}
$xlsx->addSheet('Cheques', $sheetCheques);

// Hoja 2: Recibos
$sheetRecibos = [
    ['Cuenta', 'Recibo', 'Monto']
];
foreach ($db->getDocumentosPorTipo('RECIBO') as $doc) {
    $sheetRecibos[] = [
        (string)$doc['cuenta'],
        (int)$doc['numero_documento'],
        (float)$doc['monto'],
    ];
}
$xlsx->addSheet('Recibos', $sheetRecibos);

if ($xlsx->save($outputFile)) {
    echo "✓ Archivo Excel generado exitosamente: {$outputFile}\n";
} else {
    echo "✗ Error al guardar el archivo Excel.\n";
}

// Generar archivos de texto separados
$outputDir = __DIR__ . '/output';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

// 1. emitidos.txt (Cheques)
$chequesDocs = $db->getDocumentosPorTipo('CHEQUE');
$chequesLines = [];
foreach ($chequesDocs as $doc) {
    $chequesLines[] = Parsers::toEmitidosLine($doc);
}
$chequesFile = $outputDir . '/emitidos.txt';
file_put_contents($chequesFile, implode("\r\n", $chequesLines) . "\r\n");
echo "✓ Archivo de Cheques TXT generado: {$chequesFile} (" . count($chequesLines) . " líneas)\n";

// 2. emitidos_spei.txt (Recibos)
$recibosDocs = $db->getDocumentosPorTipo('RECIBO');
$recibosLines = [];
foreach ($recibosDocs as $doc) {
    $recibosLines[] = Parsers::toEmitidosLine($doc);
}
$speiFile = $outputDir . '/emitidos_spei.txt';
file_put_contents($speiFile, implode("\r\n", $recibosLines) . "\r\n");
echo "✓ Archivo SPEI / Recibos TXT generado: {$speiFile} (" . count($recibosLines) . " líneas)\n";

