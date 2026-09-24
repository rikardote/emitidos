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
        if ($replace) {
            $db->clearAll();
        }

        $loteId = 'LOTE_' . date('Ymd_His');
        $totalEmitidos = 0;
        $totalLibera = 0;

        $db->getPdo()->beginTransaction();
        try {
            // Archivo 1: Emitidos
            if (isset($_FILES['file_emitidos']) && $_FILES['file_emitidos']['error'] === UPLOAD_ERR_OK) {
                $content = file_get_contents($_FILES['file_emitidos']['tmp_name']);
                $records = Parsers::parseEmitidos($content, $loteId, $_FILES['file_emitidos']['name']);
                foreach ($records as $r) {
                    $db->insertDocumento($r);
                }
                $totalEmitidos = count($records);
            }

            // Archivo 2: Libera Pensión (opcional)
            if (isset($_FILES['file_libera']) && $_FILES['file_libera']['error'] === UPLOAD_ERR_OK) {
                $content = file_get_contents($_FILES['file_libera']['tmp_name']);
                $records = Parsers::parseLiberaPension($content, $loteId, $_FILES['file_libera']['name']);
                foreach ($records as $r) {
                    $db->insertDocumento($r);
                }
                $totalLibera = count($records);
            }

            $db->getPdo()->commit();

            if ($totalEmitidos > 0 || $totalLibera > 0) {
                $message = "Procesamiento completado con éxito: {$totalEmitidos} registros de emitidos y {$totalLibera} registros de pensión.";
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
                    $db->insertDocumento($r);
                }
                $totalEmitidos = count($records);
            }

            if (file_exists($liberaPath)) {
                $content = file_get_contents($liberaPath);
                $records = Parsers::parseLiberaPension($content, $loteId, 'libera_pension_des.txt');
                foreach ($records as $r) {
                    $db->insertDocumento($r);
                }
                $totalLibera = count($records);
            }

            $db->getPdo()->commit();
            $message = "Archivos locales procesados: {$totalEmitidos} registros de emitidos y {$totalLibera} de pensión.";
            $messageType = 'success';
        } catch (Throwable $e) {
            $db->getPdo()->rollBack();
            $message = 'Error: ' . htmlspecialchars($e->getMessage());
            $messageType = 'danger';
        }
    }
}

// Consultar datos para la vista
$resumen = $db->getResumen();
$chequesCount = $resumen['CHEQUE']['total'] ?? 0;
$chequesMonto = $resumen['CHEQUE']['monto'] ?? 0.0;
$recibosCount = $resumen['RECIBO']['total'] ?? 0;
$recibosMonto = $resumen['RECIBO']['monto'] ?? 0.0;
$totalRegistros = $chequesCount + $recibosCount;
$totalMonto = $chequesMonto + $recibosMonto;

$chequesList = $db->getDocumentosPorTipo('CHEQUE');
$recibosList = $db->getDocumentosPorTipo('RECIBO');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procesador de Nómina - Cheques y Recibos</title>
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
                    <p class="text-xs text-indigo-200">Segmentación de Cheques, Recibos y Exportación Excel</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="export.php" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white font-medium text-sm px-4 py-2.5 rounded-lg shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Descargar Excel
                </a>
                <a href="export.php?detalle=1" title="Incluye columnas de empleado, beneficiaria y fecha" class="inline-flex items-center gap-2 bg-indigo-700 hover:bg-indigo-600 text-white font-medium text-sm px-4 py-2.5 rounded-lg shadow-sm transition">
                    Excel Detallado
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

        <!-- Métricas / Resumen -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <!-- Cheques -->
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Cheques (4 dígitos)</span>
                    <span class="px-2 py-0.5 text-xs font-bold bg-amber-100 text-amber-800 rounded-full">Pestaña 1</span>
                </div>
                <div class="mt-3 flex items-baseline justify-between">
                    <div class="text-2xl font-bold text-slate-800"><?= number_format($chequesCount) ?></div>
                    <div class="text-lg font-semibold text-amber-600">$<?= number_format($chequesMonto, 2) ?></div>
                </div>
                <p class="text-xs text-slate-400 mt-2">Cuenta: <?= Parsers::CUENTA_CONSTANTE ?></p>
            </div>

            <!-- Recibos -->
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Recibos (7 dígitos)</span>
                    <span class="px-2 py-0.5 text-xs font-bold bg-blue-100 text-blue-800 rounded-full">Pestaña 2</span>
                </div>
                <div class="mt-3 flex items-baseline justify-between">
                    <div class="text-2xl font-bold text-slate-800"><?= number_format($recibosCount) ?></div>
                    <div class="text-lg font-semibold text-blue-600">$<?= number_format($recibosMonto, 2) ?></div>
                </div>
                <p class="text-xs text-slate-400 mt-2">Cuenta: <?= Parsers::CUENTA_CONSTANTE ?></p>
            </div>

            <!-- Total General -->
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Acumulado</span>
                    <span class="px-2 py-0.5 text-xs font-bold bg-emerald-100 text-emerald-800 rounded-full">Nómina Total</span>
                </div>
                <div class="mt-3 flex items-baseline justify-between">
                    <div class="text-2xl font-bold text-slate-800"><?= number_format($totalRegistros) ?></div>
                    <div class="text-lg font-semibold text-emerald-600">$<?= number_format($totalMonto, 2) ?></div>
                </div>
                <p class="text-xs text-slate-400 mt-2">Base de datos SQLite activa</p>
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
                            1. Archivo Emitidos (Ancho fijo) <span class="text-rose-500">*</span>
                        </label>
                        <p class="text-xs text-slate-500 mb-2">Archivo general con cheques de 4 dígitos y recibos de 7 dígitos (`emitidos.txt`).</p>
                        <input type="file" name="file_emitidos" accept=".txt,.csv,.dat" class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                    </div>

                    <!-- File 2: Libera Pension -->
                    <div class="p-4 rounded-xl border border-dashed border-slate-300 hover:border-indigo-400 bg-slate-50/50 transition">
                        <label class="block text-sm font-semibold text-slate-700 mb-1">
                            2. Archivo Liberación Pensión (CSV) <span class="text-slate-400 text-xs">(Opcional)</span>
                        </label>
                        <p class="text-xs text-slate-500 mb-2">Archivo complementario separado por comas (`libera_pension_des.txt`).</p>
                        <input type="file" name="file_libera" accept=".txt,.csv,.dat" class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between pt-2 gap-3">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="replace_data" value="1" checked class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500 border-slate-300">
                        <span class="text-xs font-medium text-slate-600">Reemplazar registros anteriores (limpiar antes de importar)</span>
                    </label>

                    <div class="flex items-center gap-2">
                        <!-- Botón para procesar archivos locales existentes -->
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
                <div class="flex items-center gap-2">
                    <button id="tabBtnCheques" onclick="switchTab('cheques')" class="px-4 py-2 text-xs font-bold rounded-lg bg-indigo-600 text-white transition shadow-sm">
                        Cheques (<?= count($chequesList) ?>)
                    </button>
                    <button id="tabBtnRecibos" onclick="switchTab('recibos')" class="px-4 py-2 text-xs font-bold rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 transition">
                        Recibos (<?= count($recibosList) ?>)
                    </button>
                </div>

                <div class="flex items-center gap-3 w-full sm:w-auto">
                    <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Buscar por número, nombre..." class="w-full sm:w-64 text-xs px-3 py-2 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    
                    <form action="index.php" method="POST" onsubmit="return confirm('¿Seguro que deseas vaciar todos los registros de la base de datos?');">
                        <input type="hidden" name="action" value="clear">
                        <button type="submit" class="text-xs text-rose-600 hover:text-rose-700 hover:bg-rose-50 px-3 py-2 rounded-lg font-medium transition">
                            Vaciar Datos
                        </button>
                    </form>
                </div>
            </div>

            <!-- Tabla Cheques -->
            <div id="tabCheques" class="overflow-x-auto max-h-[500px]">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-50 text-slate-700 uppercase font-semibold sticky top-0 border-b border-slate-200 z-10">
                        <tr>
                            <th class="py-3 px-4">Cuenta (A)</th>
                            <th class="py-3 px-4">Cheque (B)</th>
                            <th class="py-3 px-4 text-right">Monto (C)</th>
                            <th class="py-3 px-4">Fecha</th>
                            <th class="py-3 px-4">Empleado / Beneficiario</th>
                            <th class="py-3 px-4">Nombre</th>
                            <th class="py-3 px-4">Origen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100" id="chequesTableBody">
                        <?php if (empty($chequesList)): ?>
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-400">No hay cheques registrados. Sube un archivo para comenzar.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($chequesList as $c): ?>
                                <tr class="hover:bg-slate-50/80 transition search-row">
                                    <td class="py-2.5 px-4 font-mono font-medium text-slate-700"><?= htmlspecialchars((string)$c['cuenta']) ?></td>
                                    <td class="py-2.5 px-4 font-mono font-bold text-amber-700"><?= htmlspecialchars((string)$c['numero_documento']) ?></td>
                                    <td class="py-2.5 px-4 font-mono font-semibold text-right text-slate-900">$<?= number_format((float)$c['monto'], 2) ?></td>
                                    <td class="py-2.5 px-4 text-slate-500"><?= htmlspecialchars((string)$c['fecha_emision']) ?></td>
                                    <td class="py-2.5 px-4 font-mono text-slate-500"><?= htmlspecialchars((string)$c['numero_persona']) ?></td>
                                    <td class="py-2.5 px-4 font-medium text-slate-800"><?= htmlspecialchars((string)$c['nombre_persona']) ?></td>
                                    <td class="py-2.5 px-4 text-[10px] text-slate-400"><?= htmlspecialchars((string)$c['archivo_origen']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Tabla Recibos -->
            <div id="tabRecibos" class="overflow-x-auto max-h-[500px] hidden">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-50 text-slate-700 uppercase font-semibold sticky top-0 border-b border-slate-200 z-10">
                        <tr>
                            <th class="py-3 px-4">Cuenta (A)</th>
                            <th class="py-3 px-4">Recibo (B)</th>
                            <th class="py-3 px-4 text-right">Monto (C)</th>
                            <th class="py-3 px-4">Fecha</th>
                            <th class="py-3 px-4">No. Empleado</th>
                            <th class="py-3 px-4">Nombre</th>
                            <th class="py-3 px-4">Origen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100" id="recibosTableBody">
                        <?php if (empty($recibosList)): ?>
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-400">No hay recibos registrados. Sube un archivo para comenzar.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recibosList as $r): ?>
                                <tr class="hover:bg-slate-50/80 transition search-row">
                                    <td class="py-2.5 px-4 font-mono font-medium text-slate-700"><?= htmlspecialchars((string)$r['cuenta']) ?></td>
                                    <td class="py-2.5 px-4 font-mono font-bold text-blue-700"><?= htmlspecialchars((string)$r['numero_documento']) ?></td>
                                    <td class="py-2.5 px-4 font-mono font-semibold text-right text-slate-900">$<?= number_format((float)$r['monto'], 2) ?></td>
                                    <td class="py-2.5 px-4 text-slate-500"><?= htmlspecialchars((string)$r['fecha_emision']) ?></td>
                                    <td class="py-2.5 px-4 font-mono text-slate-500"><?= htmlspecialchars((string)$r['numero_persona']) ?></td>
                                    <td class="py-2.5 px-4 font-medium text-slate-800"><?= htmlspecialchars((string)$r['nombre_persona']) ?></td>
                                    <td class="py-2.5 px-4 text-[10px] text-slate-400"><?= htmlspecialchars((string)$r['archivo_origen']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="p-3 bg-slate-50 border-t border-slate-100 text-xs text-slate-500 flex justify-between items-center">
                <span>Estructura de salida Excel: <strong>Hoja 1 (Cheques)</strong> y <strong>Hoja 2 (Recibos)</strong></span>
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
            const tabCheques = document.getElementById('tabCheques');
            const tabRecibos = document.getElementById('tabRecibos');

            if (tab === 'cheques') {
                btnCheques.className = 'px-4 py-2 text-xs font-bold rounded-lg bg-indigo-600 text-white transition shadow-sm';
                btnRecibos.className = 'px-4 py-2 text-xs font-bold rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 transition';
                tabCheques.classList.remove('hidden');
                tabRecibos.classList.add('hidden');
            } else {
                btnRecibos.className = 'px-4 py-2 text-xs font-bold rounded-lg bg-indigo-600 text-white transition shadow-sm';
                btnCheques.className = 'px-4 py-2 text-xs font-bold rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 transition';
                tabRecibos.classList.remove('hidden');
                tabCheques.classList.add('hidden');
            }
            filterTable();
        }

        function filterTable() {
            const query = document.getElementById('searchInput').value.toLowerCase();
            const containerId = currentTab === 'cheques' ? 'chequesTableBody' : 'recibosTableBody';
            const rows = document.getElementById(containerId).getElementsByClassName('search-row');

            for (let i = 0; i < rows.length; i++) {
                const text = rows[i].textContent.toLowerCase();
                rows[i].style.display = text.includes(query) ? '' : 'none';
            }
        }
    </script>
</body>
</html>
