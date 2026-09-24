<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/Parsers.php';

$db = Database::getInstance();
$message = null;
$messageType = 'info';

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'clear') {
        $db->clearAll();
        $message = 'Base de datos vaciada con éxito.';
        $messageType = 'success';
    } elseif ($action === 'upload') {
        $replace = isset($_POST['replace_data']) && $_POST['replace_data'] === '1';
        $loteId = 'LOTE_' . date('Ymd_His');
        $totalEmitidos = 0;
        $totalLibera = 0;

        $db->getPdo()->beginTransaction();
        try {
            // Archivo 1: Emitidos (Nómina)
            if (isset($_FILES['file_emitidos']) && $_FILES['file_emitidos']['error'] === UPLOAD_ERR_OK) {
                if ($replace) {
                    $db->clearEmitidos();
                }
                $content = file_get_contents($_FILES['file_emitidos']['tmp_name']);
                $records = Parsers::parseEmitidos($content, $loteId, $_FILES['file_emitidos']['name']);
                foreach ($records as $r) {
                    $db->insertEmitido($r);
                }
                $totalEmitidos = count($records);
            }

            // Archivo 2: Libera Pensión (Independiente)
            if (isset($_FILES['file_libera']) && $_FILES['file_libera']['error'] === UPLOAD_ERR_OK) {
                if ($replace) {
                    $db->clearPensiones();
                }
                $content = file_get_contents($_FILES['file_libera']['tmp_name']);
                $records = Parsers::parseLiberaPension($content, $loteId, $_FILES['file_libera']['name']);
                foreach ($records as $r) {
                    $db->insertPension($r);
                }
                $totalLibera = count($records);
            }

            $db->getPdo()->commit();

            if ($totalEmitidos > 0 || $totalLibera > 0) {
                $message = "Procesamiento completado: {$totalEmitidos} registros de emitidos y {$totalLibera} registros de pensión almacenados por separado.";
                $messageType = 'success';
            } else {
                $message = 'No se subió ningún archivo válido para procesar.';
                $messageType = 'warning';
            }
        } catch (Throwable $e) {
            $db->getPdo()->rollBack();
            $message = 'Error al procesar archivos: ' . htmlspecialchars($e->getMessage());
            $messageType = 'danger';
        }
    } elseif ($action === 'load_local') {
        // Carga rápida de archivos que ya están en el directorio raíz
        $replace = isset($_POST['replace_data']) && $_POST['replace_data'] === '1';
        if ($replace) {
            $db->clearAll();
        }

        $loteId = 'LOTE_' . date('Ymd_His');
        $emitidosPath = __DIR__ . '/emitidos.txt';
        $liberaPath = __DIR__ . '/libera_pension_des.txt';
        $totalEmitidos = 0;
        $totalLibera = 0;

        $db->getPdo()->beginTransaction();
        try {
            if (file_exists($emitidosPath)) {
                $content = file_get_contents($emitidosPath);
                $records = Parsers::parseEmitidos($content, $loteId, 'emitidos.txt');
                foreach ($records as $r) {
                    $db->insertEmitido($r);
                }
                $totalEmitidos = count($records);
            }

            if (file_exists($liberaPath)) {
                $content = file_get_contents($liberaPath);
                $records = Parsers::parseLiberaPension($content, $loteId, 'libera_pension_des.txt');
                foreach ($records as $r) {
                    $db->insertPension($r);
                }
                $totalLibera = count($records);
            }

            $db->getPdo()->commit();
            $message = "Archivos locales procesados: {$totalEmitidos} de emitidos y {$totalLibera} de pensión (separados).";
            $messageType = 'success';
        } catch (Throwable $e) {
            $db->getPdo()->rollBack();
            $message = 'Error: ' . htmlspecialchars($e->getMessage());
            $messageType = 'danger';
        }
    }
}

// Consultar datos de EMITIDOS
$resumenEmitidos = $db->getResumenEmitidos();
$chequesCount = $resumenEmitidos['CHEQUE']['total'] ?? 0;
$chequesMonto = $resumenEmitidos['CHEQUE']['monto'] ?? 0.0;
$recibosCount = $resumenEmitidos['RECIBO']['total'] ?? 0;
$recibosMonto = $resumenEmitidos['RECIBO']['monto'] ?? 0.0;
$totalEmitidosCount = $chequesCount + $recibosCount;
$totalEmitidosMonto = $chequesMonto + $recibosMonto;

// Consultar datos de PENSIÓN (separados)
$resumenPension = $db->getResumenPensiones();
$pensionCount = $resumenPension['total'] ?? 0;
$pensionMonto = $resumenPension['monto'] ?? 0.0;

$chequesList = $db->getEmitidosPorTipo('CHEQUE');
$recibosList = $db->getEmitidosPorTipo('RECIBO');
$pensionList = $db->getPensiones();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procesador de Nómina y Pensión</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">

    <!-- Navbar -->
    <header class="bg-indigo-900 text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-indigo-700 flex items-center justify-center font-bold text-xl shadow-inner">
                    N
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">Procesador de Nómina</h1>
                    <p class="text-xs text-indigo-200">Segmentación de Emitidos (Cheques / SPEI) y Pensión Alimenticia</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="export.php?formato=txt&tipo=cheques" title="Descargar solo cheques de emitidos" class="inline-flex items-center gap-1.5 bg-amber-600 hover:bg-amber-500 text-white font-medium text-xs px-3.5 py-2 rounded-lg shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    emitidos.txt (Cheques)
                </a>
                <a href="export.php?formato=txt&tipo=recibos" title="Descargar solo recibos SPEI de emitidos" class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-500 text-white font-medium text-xs px-3.5 py-2 rounded-lg shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    emitidos_spei.txt (Recibos)
                </a>
                <a href="export.php?formato=xlsx" title="Descargar libro Excel con pestañas separadas" class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-medium text-xs px-3.5 py-2 rounded-lg shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Excel (.xlsx)
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <!-- Mensajes de alerta -->
        <?php if ($message): ?>
            <?php
                $alertClasses = [
                    'success' => 'bg-emerald-50 border-emerald-300 text-emerald-800',
                    'danger' => 'bg-rose-50 border-rose-300 text-rose-800',
                    'warning' => 'bg-amber-50 border-amber-300 text-amber-800',
                    'info' => 'bg-blue-50 border-blue-300 text-blue-800',
                ][$messageType] ?? 'bg-blue-50 border-blue-300 text-blue-800';
            ?>
            <div class="p-4 rounded-xl border <?= $alertClasses ?> flex items-center justify-between shadow-sm">
                <span class="text-sm font-medium"><?= htmlspecialchars($message) ?></span>
                <button onclick="this.parentElement.remove()" class="text-xs opacity-70 hover:opacity-100 font-bold ml-4">✕</button>
            </div>
        <?php endif; ?>

        <!-- Sección 1: Métricas de EMITIDOS (Nómina) -->
        <div>
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-600 inline-block"></span>
                    Métricas de Emitidos (Nómina)
                </h2>
                <span class="text-xs text-slate-400">Cuenta: <?= Parsers::CUENTA_CONSTANTE ?></span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Cheques Emitidos -->
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Cheques (4 dígitos)</span>
                        <span class="px-2 py-0.5 text-xs font-bold bg-amber-100 text-amber-800 rounded-full">emitidos.txt</span>
                    </div>
                    <div class="mt-3 flex items-baseline justify-between">
                        <div class="text-2xl font-bold text-slate-800"><?= number_format($chequesCount) ?></div>
                        <div class="text-lg font-semibold text-amber-600">$<?= number_format($chequesMonto, 2) ?></div>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-2">Pestaña 1 en Excel</p>
                </div>

                <!-- Recibos SPEI Emitidos -->
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Recibos SPEI (7 dígitos)</span>
                        <span class="px-2 py-0.5 text-xs font-bold bg-blue-100 text-blue-800 rounded-full">emitidos_spei.txt</span>
                    </div>
                    <div class="mt-3 flex items-baseline justify-between">
                        <div class="text-2xl font-bold text-slate-800"><?= number_format($recibosCount) ?></div>
                        <div class="text-lg font-semibold text-blue-600">$<?= number_format($recibosMonto, 2) ?></div>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-2">Pestaña 2 en Excel</p>
                </div>

                <!-- Subtotal Emitidos -->
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Subtotal Emitidos</span>
                        <span class="px-2 py-0.5 text-xs font-bold bg-indigo-100 text-indigo-800 rounded-full">Total Nómina</span>
                    </div>
                    <div class="mt-3 flex items-baseline justify-between">
                        <div class="text-2xl font-bold text-slate-800"><?= number_format($totalEmitidosCount) ?></div>
                        <div class="text-lg font-semibold text-indigo-600">$<?= number_format($totalEmitidosMonto, 2) ?></div>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-2">Suma exclusiva de emitidos</p>
                </div>
            </div>
        </div>

        <!-- Sección 2: Métrica de PENSIÓN (Separada e independiente) -->
        <div class="bg-purple-50/60 p-4 rounded-2xl border border-purple-200">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-purple-600 text-white flex items-center justify-center font-bold text-sm">
                        P
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-purple-950">Pensión Alimenticia (Archivo Independiente)</h3>
                        <p class="text-xs text-purple-700">No forma parte de la división de emitidos ni se suma con la nómina.</p>
                    </div>
                </div>
                <div class="flex items-center gap-6">
                    <div>
                        <span class="text-[11px] uppercase font-semibold text-purple-700 block">Registros</span>
                        <span class="text-lg font-bold text-purple-950"><?= number_format($pensionCount) ?></span>
                    </div>
                    <div>
                        <span class="text-[11px] uppercase font-semibold text-purple-700 block">Monto Total Pensión</span>
                        <span class="text-lg font-bold text-purple-700">$<?= number_format($pensionMonto, 2) ?></span>
                    </div>
                    <div>
                        <a href="export.php?formato=xlsx&tipo=pension" class="inline-flex items-center gap-1.5 bg-purple-700 hover:bg-purple-800 text-white font-semibold text-xs py-2 px-3.5 rounded-lg shadow-sm transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Descargar Excel Pensión
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel de Descarga de Archivos Separados -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <h2 class="text-base font-bold text-slate-800 mb-1 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Descarga de Archivos
            </h2>
            <p class="text-xs text-slate-500 mb-5">Descarga cada archivo de forma independiente según tus necesidades:</p>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- 1. TXT Cheques -->
                <div class="p-4 rounded-xl border border-amber-200 bg-amber-50/50 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-amber-900 text-sm">emitidos.txt</span>
                            <span class="text-[10px] bg-amber-200 text-amber-900 px-2 py-0.5 rounded font-bold"><?= $chequesCount ?> Cheques</span>
                        </div>
                        <p class="text-xs text-amber-800/80 mb-4">Texto (86 caracteres) con únicamente cheques de nómina.</p>
                    </div>
                    <a href="export.php?formato=txt&tipo=cheques" class="inline-flex items-center justify-center gap-2 bg-amber-600 hover:bg-amber-500 text-white font-semibold text-xs py-2 px-3 rounded-lg shadow-sm transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Descargar emitidos.txt
                    </a>
                </div>

                <!-- 2. TXT Recibos -->
                <div class="p-4 rounded-xl border border-blue-200 bg-blue-50/50 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-blue-900 text-sm">emitidos_spei.txt</span>
                            <span class="text-[10px] bg-blue-200 text-blue-900 px-2 py-0.5 rounded font-bold"><?= $recibosCount ?> Recibos</span>
                        </div>
                        <p class="text-xs text-blue-800/80 mb-4">Texto (86 caracteres) con únicamente recibos SPEI.</p>
                    </div>
                    <a href="export.php?formato=txt&tipo=recibos" class="inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs py-2 px-3 rounded-lg shadow-sm transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Descargar emitidos_spei.txt
                    </a>
                </div>

                <!-- 3. Excel Nómina -->
                <div class="p-4 rounded-xl border border-emerald-200 bg-emerald-50/50 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-emerald-900 text-sm">Excel Nómina</span>
                            <span class="text-[10px] bg-emerald-200 text-emerald-900 px-2 py-0.5 rounded font-bold">Cheques y Recibos</span>
                        </div>
                        <p class="text-xs text-emerald-800/80 mb-4">Libro con hojas "Cheques" y "Recibos" de emitidos.</p>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <a href="export.php?formato=xlsx&tipo=nomina" class="flex-1 inline-flex items-center justify-center gap-1 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs py-2 px-2 rounded-lg shadow-sm transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Excel Nómina
                        </a>
                        <a href="export.php?formato=xlsx&tipo=nomina&detalle=1" title="Incluye columnas con nombre y fechas" class="inline-flex items-center justify-center bg-emerald-100 hover:bg-emerald-200 text-emerald-800 font-semibold text-xs py-2 px-2 rounded-lg transition">
                            Detallado
                        </a>
                    </div>
                </div>

                <!-- 4. Excel Pensión -->
                <div class="p-4 rounded-xl border border-purple-200 bg-purple-50/50 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-purple-900 text-sm">Excel Pensión</span>
                            <span class="text-[10px] bg-purple-200 text-purple-900 px-2 py-0.5 rounded font-bold"><?= $pensionCount ?> Registros</span>
                        </div>
                        <p class="text-xs text-purple-800/80 mb-4">Libro exclusivo para pensión alimenticia.</p>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <a href="export.php?formato=xlsx&tipo=pension" class="flex-1 inline-flex items-center justify-center gap-1 bg-purple-700 hover:bg-purple-600 text-white font-semibold text-xs py-2 px-2 rounded-lg shadow-sm transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Excel Pensión
                        </a>
                        <a href="export.php?formato=xlsx&tipo=pension&detalle=1" title="Incluye número y nombre de beneficiaria" class="inline-flex items-center justify-center bg-purple-100 hover:bg-purple-200 text-purple-900 font-semibold text-xs py-2 px-2 rounded-lg transition">
                            Detallado
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulario de Subida -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <h2 class="text-base font-bold text-slate-800 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                Subir archivos para procesar
            </h2>

            <form action="index.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="upload">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- File 1: Emitidos -->
                    <div class="p-4 rounded-xl border border-dashed border-slate-300 hover:border-indigo-400 bg-slate-50/50 transition">
                        <label class="block text-sm font-semibold text-slate-700 mb-1">
                            1. Archivo Emitidos (Nómina)
                        </label>
                        <p class="text-xs text-slate-500 mb-2">Archivo general con cheques de 4 dígitos y recibos de 7 dígitos (`emitidos.txt`).</p>
                        <input type="file" name="file_emitidos" accept=".txt,.csv,.dat" class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                    </div>

                    <!-- File 2: Libera Pension -->
                    <div class="p-4 rounded-xl border border-dashed border-slate-300 hover:border-indigo-400 bg-slate-50/50 transition">
                        <label class="block text-sm font-semibold text-slate-700 mb-1">
                            2. Archivo Liberación Pensión (Independiente)
                        </label>
                        <p class="text-xs text-slate-500 mb-2">Archivo separado por comas (`libera_pension_des.txt`). No se divide ni se suma a emitidos.</p>
                        <input type="file" name="file_libera" accept=".txt,.csv,.dat" class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between pt-2 gap-3">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="replace_data" value="1" checked class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300">
                        <span class="text-xs font-medium text-slate-600">Reemplazar registros anteriores (limpiar antes de importar)</span>
                    </label>

                    <div class="flex items-center gap-2">
                        <button type="submit" formaction="index.php" onclick="this.form.action.value='load_local'" class="text-xs text-slate-600 bg-slate-100 hover:bg-slate-200 px-3 py-2 rounded-lg transition font-medium">
                            Cargar archivos locales en raíz
                        </button>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-xs px-5 py-2.5 rounded-lg shadow-sm transition">
                            Procesar y Guardar
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabla con Vista Previa -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-2">
                    <button id="tabBtnCheques" onclick="switchTab('cheques')" class="px-4 py-2 text-xs font-bold rounded-lg bg-indigo-600 text-white transition shadow-sm">
                        Cheques Emitidos (<?= count($chequesList) ?>)
                    </button>
                    <button id="tabBtnRecibos" onclick="switchTab('recibos')" class="px-4 py-2 text-xs font-bold rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 transition">
                        Recibos Emitidos SPEI (<?= count($recibosList) ?>)
                    </button>
                    <button id="tabBtnPension" onclick="switchTab('pension')" class="px-4 py-2 text-xs font-bold rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 transition">
                        Pensión Alimenticia (<?= count($pensionList) ?>)
                    </button>
                </div>

                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Buscar por número, nombre..." class="w-full sm:w-64 text-xs px-3 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    
                    <form action="index.php" method="POST" onsubmit="return confirm('¿Seguro que deseas vaciar todos los registros?');">
                        <input type="hidden" name="action" value="clear">
                        <button type="submit" class="text-xs text-rose-600 hover:text-rose-700 hover:bg-rose-50 px-3 py-2 rounded-lg font-medium transition">
                            Vaciar Datos
                        </button>
                    </form>
                </div>
            </div>

            <!-- Tabla 1: Cheques Emitidos -->
            <div id="tabCheques" class="overflow-x-auto max-h-[500px]">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-50 text-slate-700 uppercase font-semibold sticky top-0 border-b border-slate-200 z-10">
                        <tr>
                            <th class="py-3 px-4">Cuenta</th>
                            <th class="py-3 px-4">Cheque (4 dígitos)</th>
                            <th class="py-3 px-4 text-right">Monto</th>
                            <th class="py-3 px-4">Fecha</th>
                            <th class="py-3 px-4">No. Empleado</th>
                            <th class="py-3 px-4">Nombre Trabajador</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100" id="chequesTableBody">
                        <?php if (empty($chequesList)): ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400">No hay cheques registrados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($chequesList as $c): ?>
                                <tr class="hover:bg-slate-50/80 transition search-row">
                                    <td class="py-2.5 px-4 font-mono font-medium text-slate-700"><?= htmlspecialchars((string)$c['cuenta']) ?></td>
                                    <td class="py-2.5 px-4 font-mono font-bold text-amber-700"><?= htmlspecialchars((string)$c['numero_documento']) ?></td>
                                    <td class="py-2.5 px-4 font-mono font-semibold text-right text-slate-900">$<?= number_format((float)$c['monto'], 2) ?></td>
                                    <td class="py-2.5 px-4 text-slate-500"><?= htmlspecialchars((string)$c['fecha_emision']) ?></td>
                                    <td class="py-2.5 px-4 font-mono text-slate-500"><?= htmlspecialchars((string)$c['numero_empleado']) ?></td>
                                    <td class="py-2.5 px-4 font-medium text-slate-800"><?= htmlspecialchars((string)$c['nombre_empleado']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Tabla 2: Recibos Emitidos SPEI -->
            <div id="tabRecibos" class="overflow-x-auto max-h-[500px] hidden">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-50 text-slate-700 uppercase font-semibold sticky top-0 border-b border-slate-200 z-10">
                        <tr>
                            <th class="py-3 px-4">Cuenta</th>
                            <th class="py-3 px-4">Recibo SPEI (7 dígitos)</th>
                            <th class="py-3 px-4 text-right">Monto</th>
                            <th class="py-3 px-4">Fecha</th>
                            <th class="py-3 px-4">No. Empleado</th>
                            <th class="py-3 px-4">Nombre Trabajador</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100" id="recibosTableBody">
                        <?php if (empty($recibosList)): ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400">No hay recibos registrados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recibosList as $r): ?>
                                <tr class="hover:bg-slate-50/80 transition search-row">
                                    <td class="py-2.5 px-4 font-mono font-medium text-slate-700"><?= htmlspecialchars((string)$r['cuenta']) ?></td>
                                    <td class="py-2.5 px-4 font-mono font-bold text-blue-700"><?= htmlspecialchars((string)$r['numero_documento']) ?></td>
                                    <td class="py-2.5 px-4 font-mono font-semibold text-right text-slate-900">$<?= number_format((float)$r['monto'], 2) ?></td>
                                    <td class="py-2.5 px-4 text-slate-500"><?= htmlspecialchars((string)$r['fecha_emision']) ?></td>
                                    <td class="py-2.5 px-4 font-mono text-slate-500"><?= htmlspecialchars((string)$r['numero_empleado']) ?></td>
                                    <td class="py-2.5 px-4 font-medium text-slate-800"><?= htmlspecialchars((string)$r['nombre_empleado']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Tabla 3: Pensión Alimenticia (Independiente) -->
            <div id="tabPension" class="overflow-x-auto max-h-[500px] hidden">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-purple-50 text-purple-900 uppercase font-semibold sticky top-0 border-b border-purple-200 z-10">
                        <tr>
                            <th class="py-3 px-4">Cuenta</th>
                            <th class="py-3 px-4">Cheque</th>
                            <th class="py-3 px-4 text-right">Monto</th>
                            <th class="py-3 px-4">Fecha</th>
                            <th class="py-3 px-4">No. Beneficiaria</th>
                            <th class="py-3 px-4">Nombre Beneficiaria</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-purple-100" id="pensionTableBody">
                        <?php if (empty($pensionList)): ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400">No hay registros de pensión alimenticia.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pensionList as $p): ?>
                                <tr class="hover:bg-purple-50/50 transition search-row">
                                    <td class="py-2.5 px-4 font-mono font-medium text-slate-700"><?= htmlspecialchars((string)$p['cuenta']) ?></td>
                                    <td class="py-2.5 px-4 font-mono font-bold text-purple-700"><?= htmlspecialchars((string)$p['cheque']) ?></td>
                                    <td class="py-2.5 px-4 font-mono font-semibold text-right text-slate-900">$<?= number_format((float)$p['monto'], 2) ?></td>
                                    <td class="py-2.5 px-4 text-slate-500"><?= htmlspecialchars((string)$p['fecha_emision']) ?></td>
                                    <td class="py-2.5 px-4 font-mono text-slate-500"><?= htmlspecialchars((string)$p['numero_beneficiaria']) ?></td>
                                    <td class="py-2.5 px-4 font-medium text-slate-800"><?= htmlspecialchars((string)$p['beneficiaria']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="p-3 bg-slate-50 border-t border-slate-100 text-xs text-slate-500 flex justify-between items-center">
                <span>Estructura Excel: <strong>Hoja Cheques</strong> | <strong>Hoja Recibos</strong> | <strong>Hoja Pensión</strong></span>
                <span>Columnas: <strong>Cuenta</strong> | <strong>Cheque / Recibo</strong> | <strong>Monto</strong></span>
            </div>
        </div>

    </main>

    <script>
        let currentTab = 'cheques';

        function switchTab(tab) {
            currentTab = tab;
            const btnCheques = document.getElementById('tabBtnCheques');
            const btnRecibos = document.getElementById('tabBtnRecibos');
            const btnPension = document.getElementById('tabBtnPension');

            const tabCheques = document.getElementById('tabCheques');
            const tabRecibos = document.getElementById('tabRecibos');
            const tabPension = document.getElementById('tabPension');

            // Reset buttons
            [btnCheques, btnRecibos, btnPension].forEach(b => {
                b.className = 'px-4 py-2 text-xs font-bold rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 transition';
            });
            // Hide all tabs
            [tabCheques, tabRecibos, tabPension].forEach(t => t.classList.add('hidden'));

            if (tab === 'cheques') {
                btnCheques.className = 'px-4 py-2 text-xs font-bold rounded-lg bg-indigo-600 text-white transition shadow-sm';
                tabCheques.classList.remove('hidden');
            } else if (tab === 'recibos') {
                btnRecibos.className = 'px-4 py-2 text-xs font-bold rounded-lg bg-indigo-600 text-white transition shadow-sm';
                tabRecibos.classList.remove('hidden');
            } else if (tab === 'pension') {
                btnPension.className = 'px-4 py-2 text-xs font-bold rounded-lg bg-purple-600 text-white transition shadow-sm';
                tabPension.classList.remove('hidden');
            }
            filterTable();
        }

        function filterTable() {
            const query = document.getElementById('searchInput').value.toLowerCase();
            let containerId = 'chequesTableBody';
            if (currentTab === 'recibos') containerId = 'recibosTableBody';
            if (currentTab === 'pension') containerId = 'pensionTableBody';

            const rows = document.getElementById(containerId).getElementsByClassName('search-row');
            for (let i = 0; i < rows.length; i++) {
                const text = rows[i].textContent.toLowerCase();
                rows[i].style.display = text.includes(query) ? '' : 'none';
            }
        }
    </script>
</body>
</html>
