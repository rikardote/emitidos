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
        $db->insertEmitido($r);
    }
    $totalEmitidos = count($records);
} else {
    echo "(!) Advertencia: No se encontró emitidos.txt en la raíz.\n";
}

if (file_exists($liberaPath)) {
    echo "- Procesando " . basename($liberaPath) . "...\n";
    $records = Parsers::parseLiberaPension(file_get_contents($liberaPath), $loteId, 'libera_pension_des.txt');
    foreach ($records as $r) {
        $db->insertPension($r);
    }
    $totalLibera = count($records);
} else {
    echo "(!) Advertencia: No se encontró libera_pension_des.txt en la raíz.\n";
}

$db->getPdo()->commit();

$resumenEmitidos = $db->getResumenEmitidos();
$chequesCount = $resumenEmitidos['CHEQUE']['total'] ?? 0;
$chequesMonto = $resumenEmitidos['CHEQUE']['monto'] ?? 0.0;
$recibosCount = $resumenEmitidos['RECIBO']['total'] ?? 0;
$recibosMonto = $resumenEmitidos['RECIBO']['monto'] ?? 0.0;

$resumenPension = $db->getResumenPensiones();
$pensionCount = $resumenPension['total'] ?? 0;
$pensionMonto = $resumenPension['monto'] ?? 0.0;

echo "\n--- Resumen EMITIDOS (Nómina) ---\n";
echo "Cheques (4 dígitos): {$chequesCount} registros | Monto: $" . number_format($chequesMonto, 2) . "\n";
echo "Recibos (7 dígitos): {$recibosCount} registros | Monto: $" . number_format($recibosMonto, 2) . "\n";
echo "Subtotal Emitidos: " . ($chequesCount + $recibosCount) . " registros | Monto: $" . number_format($chequesMonto + $recibosMonto, 2) . "\n";

echo "\n--- Resumen PENSIÓN (Archivo independiente) ---\n";
echo "Cheques de Pensión: {$pensionCount} registros | Monto: $" . number_format($pensionMonto, 2) . "\n";

// Crear carpeta output
$outputDir = __DIR__ . '/output';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

// 1. emitidos.txt (SOLO Cheques de emitidos)
$chequesDocs = $db->getEmitidosPorTipo('CHEQUE');
$chequesLines = [];
foreach ($chequesDocs as $doc) {
    $chequesLines[] = Parsers::toEmitidosLine($doc);
}
$chequesFile = $outputDir . '/emitidos.txt';
file_put_contents($chequesFile, implode("\r\n", $chequesLines) . "\r\n");
echo "\n✓ Archivo emitidos.txt (Cheques) generado: {$chequesFile} (" . count($chequesLines) . " líneas)\n";

// 2. emitidos_spei.txt (SOLO Recibos de emitidos)
$recibosDocs = $db->getEmitidosPorTipo('RECIBO');
$recibosLines = [];
foreach ($recibosDocs as $doc) {
    $recibosLines[] = Parsers::toEmitidosLine($doc);
}
$speiFile = $outputDir . '/emitidos_spei.txt';
file_put_contents($speiFile, implode("\r\n", $recibosLines) . "\r\n");
echo "✓ Archivo emitidos_spei.txt (Recibos) generado: {$speiFile} (" . count($recibosLines) . " líneas)\n";

// 3. Generar Excel
$outputFile = $outputDir . '/reporte_nomina.xlsx';
echo "- Generando {$outputFile}...\n";

$xlsx = new SimpleXlsx();

// Hoja 1: Cheques
$sheetCheques = [['Cuenta', 'Cheque', 'Monto']];
foreach ($chequesDocs as $doc) {
    $sheetCheques[] = [
        (string)$doc['cuenta'],
        (int)$doc['numero_documento'],
        (float)$doc['monto'],
    ];
}
$xlsx->addSheet('Cheques', $sheetCheques);

// Hoja 2: Recibos
$sheetRecibos = [['Cuenta', 'Recibo', 'Monto']];
foreach ($recibosDocs as $doc) {
    $sheetRecibos[] = [
        (string)$doc['cuenta'],
        (int)$doc['numero_documento'],
        (float)$doc['monto'],
    ];
}
$xlsx->addSheet('Recibos', $sheetRecibos);

// Hoja 3: Pensión
if ($pensionCount > 0) {
    $sheetPension = [['Cuenta', 'Cheque', 'Monto']];
    foreach ($db->getPensiones() as $doc) {
        $sheetPension[] = [
            (string)$doc['cuenta'],
            (int)$doc['cheque'],
            (float)$doc['monto'],
        ];
    }
    $xlsx->addSheet('Pensión', $sheetPension);
}

if ($xlsx->save($outputFile)) {
    echo "✓ Archivo Excel generado exitosamente: {$outputFile}\n";
} else {
    echo "✗ Error al guardar el archivo Excel.\n";
}
