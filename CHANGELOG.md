## v4.8.4
- Corrige la descarga PDF de imágenes usando una acción dedicada y cabeceras de descarga endurecidas.
- Limpia buffers antes de enviar el PDF para evitar que el navegador reciba el archivo original o mezcle salida HTML.

## v4.8.3
- Corrige la conversion de imagen a PDF con fallback por editor de WordPress, Imagick y GD.
- Corrige cabeceras de descarga PDF.

## 4.8.1
- Corrige la descarga PDF de imágenes: ahora el botón principal descarga el PDF y deja un botón secundario para el archivo original.
- Mejora las cabeceras de descarga para que el navegador respete la extensión .pdf.

## v4.7
- Se elimina WebDAV del plugin.
- Horarios usa solo URL compartida.
- Nóminas mantiene URL individual por empleado.
- Contabilidad, Documentos y demás suben y descargan archivos desde la web.

## v4.6.1
- Si WebDAV responde con error (como HTTP 405), la subida cae automáticamente a almacenamiento local descargable desde la web.
- Se conserva WebDAV cuando funciona.

## v4.6
- WebDAV global solo para subida de archivos.
- Horarios sigue usando URL compartida para visualización.
- Nóminas permite configurar una URL propia por empleado desde Almacenamiento.
- Subidas web permiten cualquier tipo de archivo.

## v4.5
- Almacenamiento ahora muestra el estado de la conexión WebDAV.
- Botón para probar la conexión manualmente desde el panel.
- Al guardar la configuración se valida WebDAV y se muestra confirmación o error.

## v4.4
- Contabilidad y Documentos con subida híbrida: WebDAV si está configurado, o almacenamiento local descargable desde la web.
- Descarga y eliminación desde el panel manteniendo compatibilidad con Synology WebDAV.

## v4.3
- Vacaciones sin horas; franjas por fechas y plazas.
- Cambios de turno cancelados se eliminan.
- Incidencias con fecha, hora y turno.
- Estupefacientes con CN, medicamento, tipo de movimiento, stock y exportación CSV por mes.
- Documentos y Contabilidad por mes actual, subida rápida y descarga/eliminación para administración.
- Nóminas centradas en descargas; la gestión administrativa se mueve a almacenamiento.

## v4.2
- Horarios ya no redirige desde el menú; mantiene panel interno con botón externo y tableros.
- Vacaciones con creación de franjas por administración usando fecha y hora.
- Tablas de horarios y vacaciones muestran rango horario.

# Changelog

## 4.1.0
- Vacaciones por franjas creadas por administración y reserva por empleados.
- Tablero en Horarios con vacaciones aprobadas y cambios de turno aprobados.
- Horarios deja de abrir una ventana intermedia y muestra botón directo al enlace configurado.
- Cambios de turno visibles para implicados, administración y farmacéuticos.

## 4.0.0
- Incidencias internas con creación, seguimiento y cierre.
- Solicitudes de cambio de turno y vacaciones con aprobación.
- Permisos ajustados a los perfiles definidos.
- Se mantiene el nombre base `farmacia-portal-interno`.
