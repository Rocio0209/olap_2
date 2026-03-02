# Manual de Usuario Funcional - Cubos de Biologicos

## 1. Objetivo
Este modulo permite consultar y exportar informacion de biologicos por CLUES usando cubos SIS.

## 2. Que puede hacer el usuario
- Seleccionar un SIS (catalogo/cubo).
- Buscar y seleccionar CLUES manualmente.
- Cargar CLUES masivamente por prefijo (`HG`, `HGIMB`, `HGSSA`, segun año).
- Ver un preview de resultados.
- Generar y descargar un archivo Excel.
- Cancelar una exportacion en proceso.

## 3. Requisitos de acceso
- Tener usuario activo en el sistema.
- Tener permisos del modulo Biologicos (`vacunas/biologicos`).
- Haber iniciado sesion y validado cuenta segun politicas del sistema.

## 4. Ruta del modulo
- Menu -> Biologicos
- URL: `/vacunas/biologicos`

## 5. Flujo de uso recomendado
1. Entrar al modulo Biologicos.
2. Seleccionar el SIS en el campo "SIS".
3. Capturar CLUES en el buscador o usar botones de prefijo.
4. Verificar los CLUES seleccionados (chips).
5. Pulsar `Consultar (Preview)`.
6. Revisar la tabla de preview.
7. Pulsar `Exportar a Excel`.
8. Esperar avance en la barra de progreso.
9. Al finalizar, pulsar `Descargar Excel`.

## 6. Seleccion de CLUES
### Busqueda manual
- Escribir CLUES o nombre de unidad.
- Elegir opciones del listado sugerido.

### Carga por prefijo
- `HG`
- `HGIMB`
- `HGSSA`

Nota:
- En catalogos anteriores a 2024 puede mostrarse solo `HGSSA`.
- Si la carga masiva supera cierto volumen, el sistema solicita confirmacion.

## 7. Preview
El preview muestra:
- Metadatos: cuantas filas se muestran y cuantos CLUES se consultaron.
- Columnas fijas: CLUES, Unidad, Entidad, Jurisdiccion, Municipio, Institucion.
- Columnas dinamicas por apartados y variables de biologicos.

Importante:
- El preview es una muestra rapida (no necesariamente todo el universo exportable).

## 8. Exportacion a Excel
- La exportacion se ejecuta en segundo plano.
- Se muestra progreso aproximado en modal.
- Al completar, se habilita boton de descarga.
- Se puede cancelar mientras esta en proceso.

Estados posibles:
- `processing`: en proceso.
- `completed`: terminado.
- `failed`: fallo.
- `cancelled`: cancelado.

## 9. Errores comunes y accion sugerida
### "Selecciona al menos 1 CLUES"
- Agregar al menos un CLUES antes del preview/export.

### "Error al consultar preview"
- Validar que SIS y CLUES esten seleccionados.
- Reintentar.
- Si persiste, reportar a soporte.

### Exportacion se queda en proceso
- Esperar algunos minutos (depende del volumen).
- Cancelar y volver a intentar con menos CLUES.

### No descarga archivo
- Verificar que la exportacion termine en `completed`.
- Reintentar descarga.

## 10. Buenas practicas para usuario funcional
- Ejecutar consultas por bloques de CLUES si el volumen es muy alto.
- Verificar preview antes de exportar.
- Evitar multiples exportaciones simultaneas innecesarias.
- Reportar errores con hora, usuario y SIS utilizado.

## 11. Soporte
Para incidencias, compartir:
- Usuario.
- Fecha y hora.
- SIS/cubo seleccionado.
- CLUES usados (o prefijo).
- Captura del mensaje de error.
