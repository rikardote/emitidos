# Procesador de Nómina (Cheques y Recibos)

Herramienta ligera desarrollada en **PHP nativo (sin frameworks)** y **SQLite** para segmentar y procesar archivos de nómina y pensión alimenticia, exportando los resultados a un libro de **Excel (.xlsx)** con pestañas separadas para Cheques y Recibos.

## Características

- **Sin dependencias externas ni Composer**: Utiliza `ZipArchive` y manipulación OpenXML nativa para generar hojas `.xlsx` reales y compatibles con Microsoft Excel, LibreOffice y Google Sheets.
- **Base de datos SQLite embebida**: Persistencia rápida en `nomina.db` con soporte de transacciones e índices.
- **Segmentación automática**:
  - **Cheques**: 4 dígitos (sin ceros a la izquierda).
  - **Recibos**: 7 dígitos (sin ceros a la izquierda).
  - **Monto**: Últimos 2 dígitos normalizados a decimales.
  - **Cuenta**: Constante `120866091`.
  - **No. Empleado**: Eliminación automática del prefijo `3`.
- **Doble interfaz**:
  - **Web**: Pantalla responsiva para subir archivos, ver métricas, filtrar registros en tiempo real y descargar Excel.
  - **CLI**: Procesamiento por línea de comandos para automatización rápida.

## Requisitos

- PHP 8.0 o superior (con extensiones `pdo_sqlite` y `zip`).

## Uso

### 1. Interfaz Web (Recomendado)

Inicia el servidor local de desarrollo de PHP:

```bash
php -S localhost:8000
```

Abre en tu navegador: [http://localhost:8000](http://localhost:8000)

1. Sube el archivo general `emitidos.txt` y opcionalmente `libera_pension_des.txt`.
2. Revisa el resumen y la vista previa con buscador integrado.
3. Haz clic en **Descargar Excel** para obtener el archivo con las pestañas separadas.

### 2. Línea de comandos (CLI)

Coloca los archivos en la raíz (`emitidos.txt` y/o `libera_pension_des.txt`) y ejecuta:

```bash
php procesar.php
```

El script procesará los registros, creará la base de datos `nomina.db` y generará `reporte_nomina.xlsx`.

## Archivos Generados

### 1. Archivos de Texto (.txt de 86 caracteres)
- **`emitidos.txt`**: Contiene únicamente las líneas correspondientes a **Cheques** (4 dígitos).
- **`emitidos_spei.txt`**: Contiene únicamente las líneas correspondientes a **Recibos SPEI** (7 dígitos).

### 2. Archivos Excel (.xlsx)

#### `reporte_nomina.xlsx` (Nómina)
- **Pestaña 1 ("Cheques")**:
  - Columna A: Cuenta (`120866091`)
  - Columna B: Cheque
  - Columna C: Monto
- **Pestaña 2 ("Recibos")**:
  - Columna A: Cuenta (`120866091`)
  - Columna B: Recibo
  - Columna C: Monto

#### `reporte_pension.xlsx` (Pensión Alimenticia - Archivo Propio)
- **Pestaña 1 ("Pensión")**:
  - Columna A: Cuenta (`120866091`)
  - Columna B: Cheque
  - Columna C: Monto


