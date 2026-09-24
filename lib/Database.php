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
        // Tabla para emitidos (Cheques y Recibos SPEI de nómina)
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS emitidos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lote_id TEXT NOT NULL,
                tipo TEXT NOT NULL, -- CHEQUE o RECIBO
                cuenta TEXT NOT NULL,
                numero_documento TEXT NOT NULL,
                monto REAL NOT NULL,
                fecha_emision TEXT,
                numero_empleado TEXT,
                nombre_empleado TEXT,
                linea_original TEXT,
                creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE INDEX IF NOT EXISTS idx_emitidos_tipo ON emitidos(tipo);
            CREATE INDEX IF NOT EXISTS idx_emitidos_doc ON emitidos(numero_documento);

            -- Tabla separada para pensiones alimenticias (NO se mezcla con emitidos)
            CREATE TABLE IF NOT EXISTS pensiones (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lote_id TEXT NOT NULL,
                cuenta TEXT NOT NULL,
                cheque TEXT NOT NULL,
                monto REAL NOT NULL,
                numero_beneficiaria TEXT,
                beneficiaria TEXT,
                fecha_emision TEXT,
                linea_original TEXT,
                creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE INDEX IF NOT EXISTS idx_pensiones_cheque ON pensiones(cheque);
        ');
    }

    public function clearEmitidos(): void
    {
        $this->pdo->exec('DELETE FROM emitidos');
    }

    public function clearPensiones(): void
    {
        $this->pdo->exec('DELETE FROM pensiones');
    }

    public function clearAll(): void
    {
        $this->clearEmitidos();
        $this->clearPensiones();
        // Limpiar tabla antigua si existía
        try {
            $this->pdo->exec('DROP TABLE IF EXISTS documentos');
        } catch (Throwable $e) {}
    }

    public function insertEmitido(array $data): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO emitidos (
                lote_id, tipo, cuenta, numero_documento,
                monto, fecha_emision, numero_empleado, nombre_empleado, linea_original
            ) VALUES (
                :lote_id, :tipo, :cuenta, :numero_documento,
                :monto, :fecha_emision, :numero_empleado, :nombre_empleado, :linea_original
            )
        ');
        $stmt->execute([
            ':lote_id' => $data['lote_id'],
            ':tipo' => $data['tipo'],
            ':cuenta' => $data['cuenta'],
            ':numero_documento' => $data['numero_documento'],
            ':monto' => $data['monto'],
            ':fecha_emision' => $data['fecha_emision'] ?? '',
            ':numero_empleado' => $data['numero_empleado'] ?? '',
            ':nombre_empleado' => $data['nombre_empleado'] ?? '',
            ':linea_original' => $data['linea_original'] ?? ''
        ]);
    }

    public function insertPension(array $data): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO pensiones (
                lote_id, cuenta, cheque, monto,
                numero_beneficiaria, beneficiaria, fecha_emision, linea_original
            ) VALUES (
                :lote_id, :cuenta, :cheque, :monto,
                :numero_beneficiaria, :beneficiaria, :fecha_emision, :linea_original
            )
        ');
        $stmt->execute([
            ':lote_id' => $data['lote_id'],
            ':cuenta' => $data['cuenta'],
            ':cheque' => $data['cheque'],
            ':monto' => $data['monto'],
            ':numero_beneficiaria' => $data['numero_beneficiaria'] ?? '',
            ':beneficiaria' => $data['beneficiaria'] ?? '',
            ':fecha_emision' => $data['fecha_emision'] ?? '',
            ':linea_original' => $data['linea_original'] ?? ''
        ]);
    }

    public function getResumenEmitidos(): array
    {
        $res = $this->pdo->query('
            SELECT 
                tipo,
                COUNT(*) as total_registros,
                SUM(monto) as total_monto
            FROM emitidos
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

    public function getResumenPensiones(): array
    {
        $row = $this->pdo->query('
            SELECT 
                COUNT(*) as total_registros,
                COALESCE(SUM(monto), 0) as total_monto
            FROM pensiones
        ')->fetch();

        return [
            'total' => (int)($row['total_registros'] ?? 0),
            'monto' => (float)($row['total_monto'] ?? 0.0)
        ];
    }

    public function getEmitidosPorTipo(string $tipo): array
    {
        $stmt = $this->pdo->prepare('
            SELECT cuenta, numero_documento, monto, fecha_emision, numero_empleado, nombre_empleado, linea_original
            FROM emitidos
            WHERE tipo = :tipo
            ORDER BY id ASC
        ');
        $stmt->execute([':tipo' => $tipo]);
        return $stmt->fetchAll();
    }

    public function getLineasEmitidosPorTipo(string $tipo): array
    {
        $stmt = $this->pdo->prepare('
            SELECT linea_original
            FROM emitidos
            WHERE tipo = :tipo AND linea_original IS NOT NULL AND linea_original != ""
            ORDER BY id ASC
        ');
        $stmt->execute([':tipo' => $tipo]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getPensiones(): array
    {
        return $this->pdo->query('
            SELECT cuenta, cheque, monto, numero_beneficiaria, beneficiaria, fecha_emision, linea_original
            FROM pensiones
            ORDER BY id ASC
        ')->fetchAll();
    }
}
