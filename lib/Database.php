<?php
declare(strict_types=1);

class Database
{
    private PDO $pdo;
    private static ?Database $instance = null;

    public function __construct(?string $dbPath = null)
    {
        if ($dbPath === null) {
            $dbPath = dirname(__DIR__) . '/nomina.db';
        }

        $this->pdo = new PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->initSchema();
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    private function initSchema(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS documentos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lote_id TEXT NOT NULL,
                archivo_origen TEXT NOT NULL,
                tipo TEXT NOT NULL, -- CHEQUE o RECIBO
                cuenta TEXT NOT NULL, -- 120866091
                numero_documento TEXT NOT NULL, -- ej 6396 o 4380996 (sin ceros izq)
                monto REAL NOT NULL,
                fecha_emision TEXT,
                numero_persona TEXT,
                nombre_persona TEXT,
                linea_original TEXT,
                creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE INDEX IF NOT EXISTS idx_tipo ON documentos(tipo);
            CREATE INDEX IF NOT EXISTS idx_lote ON documentos(lote_id);
            CREATE INDEX IF NOT EXISTS idx_num_doc ON documentos(numero_documento);
        ');

        try {
            $this->pdo->exec('ALTER TABLE documentos ADD COLUMN linea_original TEXT');
        } catch (Throwable $e) {
            // Ya existe la columna
        }
    }

    public function insertDocumento(array $data): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO documentos (
                lote_id, archivo_origen, tipo, cuenta, numero_documento,
                monto, fecha_emision, numero_persona, nombre_persona, linea_original
            ) VALUES (
                :lote_id, :archivo_origen, :tipo, :cuenta, :numero_documento,
                :monto, :fecha_emision, :numero_persona, :nombre_persona, :linea_original
            )
        ');
        $stmt->execute([
            ':lote_id' => $data['lote_id'],
            ':archivo_origen' => $data['archivo_origen'],
            ':tipo' => $data['tipo'],
            ':cuenta' => $data['cuenta'],
            ':numero_documento' => $data['numero_documento'],
            ':monto' => $data['monto'],
            ':fecha_emision' => $data['fecha_emision'] ?? '',
            ':numero_persona' => $data['numero_persona'] ?? '',
            ':nombre_persona' => $data['nombre_persona'] ?? '',
            ':linea_original' => $data['linea_original'] ?? ''
        ]);
    }

    public function clearAll(): void
    {
        $this->pdo->exec('DELETE FROM documentos');
    }

    public function getResumen(): array
    {
        $res = $this->pdo->query('
            SELECT 
                tipo,
                COUNT(*) as total_registros,
                SUM(monto) as total_monto
            FROM documentos
            GROUP BY tipo
        ')->fetchAll();

        $summary = [
            'CHEQUE' => ['total' => 0, 'monto' => 0.0],
            'RECIBO' => ['total' => 0, 'monto' => 0.0],
        ];

        foreach ($res as $row) {
            $summary[$row['tipo']] = [
                'total' => (int)$row['total_registros'],
                'monto' => (float)$row['total_monto']
            ];
        }

        return $summary;
    }

    public function getDocumentosPorTipo(string $tipo, ?string $archivoOrigen = null): array
    {
        if ($archivoOrigen !== null) {
            $stmt = $this->pdo->prepare('
                SELECT cuenta, numero_documento, monto, fecha_emision, numero_persona, nombre_persona, archivo_origen, linea_original
                FROM documentos
                WHERE tipo = :tipo AND archivo_origen = :archivo_origen
                ORDER BY id ASC
            ');
            $stmt->execute([':tipo' => $tipo, ':archivo_origen' => $archivoOrigen]);
        } else {
            $stmt = $this->pdo->prepare('
                SELECT cuenta, numero_documento, monto, fecha_emision, numero_persona, nombre_persona, archivo_origen, linea_original
                FROM documentos
                WHERE tipo = :tipo
                ORDER BY id ASC
            ');
            $stmt->execute([':tipo' => $tipo]);
        }
        return $stmt->fetchAll();
    }

    public function getLineasOriginalesPorTipo(string $tipo, ?string $archivoOrigen = null): array
    {
        if ($archivoOrigen !== null) {
            $stmt = $this->pdo->prepare('
                SELECT linea_original
                FROM documentos
                WHERE tipo = :tipo AND archivo_origen = :archivo_origen AND linea_original IS NOT NULL AND linea_original != ""
                ORDER BY id ASC
            ');
            $stmt->execute([':tipo' => $tipo, ':archivo_origen' => $archivoOrigen]);
        } else {
            $stmt = $this->pdo->prepare('
                SELECT linea_original
                FROM documentos
                WHERE tipo = :tipo AND linea_original IS NOT NULL AND linea_original != ""
                ORDER BY id ASC
            ');
            $stmt->execute([':tipo' => $tipo]);
        }
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getUltimos(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare('
            SELECT id, tipo, archivo_origen, cuenta, numero_documento, monto, fecha_emision, numero_persona, nombre_persona
            FROM documentos
            ORDER BY id DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
