# README para Desarrolladores - Proyecto Cubos OLAP

## 1. Resumen tecnico
Proyecto mixto con dos servicios:
- **Laravel 12 (PHP 8.2)**: UI, autenticacion/autorizacion, orquestacion de consultas y exportaciones.
- **FastAPI (Python)**: integracion con cubos OLAP (SSAS) via ADODB/COM (`pywin32`).

Caso principal:
1. Usuario selecciona SIS/cubo + CLUES.
2. Laravel consulta FastAPI para preview.
3. Laravel crea exportacion async a Excel con colas por chunks.

## 2. Estructura principal
- `routes/web.php`: rutas de vistas y catalogos.
- `routes/api.php`: endpoints API internos usados por frontend.
- `app/Http/Controllers/BiologicosController.php`: preview y catalogos/cubos SIS.
- `app/Http/Controllers/CluesController.php`: busqueda de CLUES y consulta por estado.
- `app/Http/Controllers/ExportsController.php`: ciclo completo de exportacion (crear, estado, cancelar, descargar).
- `app/Jobs/FetchTransformChunk.php`: consume API por chunks de CLUES y guarda JSONL temporal.
- `app/Jobs/BuildExcelFromParts.php`: arma el XLSX final desde partes.
- `app/Exports/BiologicosExport.php`: generador streaming y formato avanzado de hoja.
- `app/Services/VacunasApiService.php`: cliente Laravel -> FastAPI.
- `api/app.py`: endpoints FastAPI y logica de negocio OLAP.
- `api/config.py`: cadena de conexion OLAP.
- `api/middlewares/auth.py`: Bearer token para FastAPI.

## 3. Flujo detallado
### 3.1 Preview
Frontend -> `POST /api/vacunas/biologicos/preview`
- Valida `catalogo`, `cubo`, `clues`.
- Consulta FastAPI (`/biologicos_por_clues_con_unidad`) con token.
- Aplana respuesta y arma estructura tabular (`fixed_columns`, `apartados`, `rows`).

### 3.2 Exportacion
Frontend -> `POST /api/vacunas/exports`
- Crea registro `exports` con estado inicial.
- Parte CLUES en chunks de 20.
- Crea batch:
- `FetchTransformChunk` x N
- `BuildExcelFromParts` final

Polling:
- `GET /api/vacunas/exports/{id}`

Cancelacion:
- `POST /api/vacunas/exports/{id}/cancel`

Descarga:
- `GET /api/vacunas/exports/{id}/download`

## 4. Contratos API Laravel (internos)
### `POST /api/vacunas/biologicos/preview`
Body:
```json
{
  "catalogo": "...",
  "cubo": "...",
  "clues": ["HG...", "HG..."]
}
```

### `POST /api/vacunas/exports`
Body:
```json
{
  "catalogo": "...",
  "cubo": "...",
  "clues": ["HG...", "HG..."]
}
```

### `GET /api/vacunas/exports/{id}`
Regresa estado y progreso.

### `POST /api/vacunas/exports/{id}/cancel`
Cancela batch y limpia estado.

### `GET /api/vacunas/exports/{id}/download`
Descarga XLSX final si existe.

## 5. Contratos API FastAPI
### `GET /catalogos_y_cubos_sis`
Lista catalogos SIS validos y cubos reales.

### `POST /biologicos_por_clues_con_unidad`
Entrada:
- `catalogo`, `cubo`, `clues_list`, `search_text`, `max_vars`, `incluir_ceros`.

Salida:
- Datos por CLUES con `unidad` + `biologicos` agrupados.

### `POST /clues_y_nombre_unidad_por_estado`
Entrada:
- `catalogo`, `cubo`, `estado`, `max_clues`.

Salida:
- Lista de `{clues, nombre_unidad}` filtrada.

## 6. Configuracion de entorno
## 6.1 Laravel `.env`
Minimo relevante:
- `DB_*`
- `QUEUE_CONNECTION=database`
- `VACUNAS_API_URL`
- `VACUNAS_API_TOKEN`
- `VACUNAS_API_BIOLOGICOS`

## 6.2 FastAPI `api/.env`
- `OLAP_USER`
- `OLAP_PASSWORD`
- `OLAP_SERVER`
- `OLAP_PROVIDER` (ej. `MSOLAP.8`)
- `API_TOKENS` (uno o varios, separados por coma)
- `CORS_ORIGINS`

## 7. Puesta en marcha local
## Laravel
```bash
composer install
npm install
php artisan key:generate
php artisan migrate --seed
php artisan queue:work
npm run dev
php artisan serve
```

## FastAPI
```bash
cd api
pip install fastapi uvicorn python-dotenv pywin32
uvicorn app:app --host 0.0.0.0 --port 8080 --reload
```

## 8. Seguridad y autorizacion
- Laravel usa Jetstream/Sanctum + permisos Spatie.
- Middleware `configUsuario` obliga flujo de configuracion de cuenta segun reglas.
- FastAPI protege endpoints con bearer token (`Authorization: Bearer ...`).

## 9. Cola y rendimiento
- Export por chunks de 20 CLUES.
- Persistencia temporal en `storage/app/exports/tmp/{exportId}` como `jsonl`.
- Archivo final en `storage/app/exports/final/`.
- Jobs usan `SkipIfBatchCancelled` para respetar cancelacion.

## 10. Puntos de mantenimiento importantes
1. **Jerarquias por anio en FastAPI**
- Diccionario `DIMENSIONES_POR_ANIO` en `api/app.py`.
- Si cambia estructura OLAP por anio, actualizar aqui.

2. **Formulas y formato Excel**
- Se concentran en `BiologicosExport.php`.
- Incluye formulas de cobertura y formateo por bloques.

3. **Permisos**
- Seeder base en `database/seeders/PermisosSeeder.php`.
- Permiso de acceso al modulo: `vacunas/biologicos`.

4. **Integracion token Laravel <-> FastAPI**
- `VACUNAS_API_TOKEN` debe existir en `API_TOKENS`.

## 11. Troubleshooting tecnico
### 401/403 al consumir FastAPI
- Verificar `VACUNAS_API_TOKEN` y `API_TOKENS`.
- Confirmar envio de header `Authorization`.

### Error de conexion OLAP
- Revisar `OLAP_SERVER`, usuario/password y conectividad.
- Validar proveedor `MSOLAP` instalado en servidor Windows.

### Export no avanza
- Verificar worker de cola corriendo (`php artisan queue:work`).
- Revisar tabla `jobs`, `job_batches`, y logs de Laravel.

### Archivo final no aparece
- Verificar permisos de escritura en `storage/app/exports`.
- Revisar campo `final_path` en tabla `exports`.

## 12. Mejoras recomendadas
1. Agregar pruebas automatizadas para endpoints de vacunas/exports.
2. Documentar OpenAPI para endpoints Laravel internos.
3. Homogeneizar codificacion UTF-8 en strings historicos.
4. Separar formulas de Excel en clase dedicada para facilitar pruebas.
