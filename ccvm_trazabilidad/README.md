# CCVM Trazabilidad v4

Reemplaza por completo la versión anterior. Se agregó: panel de inicio
rediseñado (tarjetas), reporte mensual exportable a Word y PDF, y la
tercera línea de Garantías y Reparaciones con fotos.

## ⚠️ Importante antes de instalar

El script `01_ccvm_trazabilidad.sql` **borra la base de datos anterior**
(la tabla de escaneos cambió para soportar garantías). Si ya tenías
datos reales cargados que no quieres perder, avísame antes de correrlo.

## Instalación (XAMPP)

1. Copia esta carpeta a `htdocs/ccvm_trazabilidad` (que `login.php` quede
   directo en `htdocs/ccvm_trazabilidad/login.php`).
2. En phpMyAdmin, importa en este orden los archivos de `sql/`:
   1. `01_ccvm_trazabilidad.sql`
   2. `02_seed_usuario.sql`
   3. `03_seed_usuarios_estaciones.sql`
   4. `04_seed_datos_demo.sql`
3. Verifica que la carpeta `uploads/garantias/` tenga permisos de
   escritura para el servidor (en XAMPP normalmente ya los tiene).
4. Entra a `http://localhost/ccvm_trazabilidad/login.php`

Códigos de prueba: los mismos de siempre — **1234** (programador),
**1001–1012** (operarios), **9001** (jefe de planta), **9002** (gerencia).

## Qué hay nuevo

### Panel de inicio (`panel.php`)
Ahora al entrar (si no eres operario) ves un menú de tarjetas — cada
función en su propio espacio, en vez de todo junto en una sola pantalla:
Mapa de seguimiento, Reporte mensual, Garantías y reparaciones, Cargar
lote (jefe de planta/programador), Buscar cuba y Usuarios (programador).

### Reporte mensual (`reportes.php`)
Elige un mes y ves: unidades creadas/entregadas/rechazadas, pedidos del
mes con su cliente y cantidad de unidades, y producción por cada
estación/zona. Dos botones de descarga:
- **Descargar en Word** — abre directo en Microsoft Word.
- **Descargar en PDF** — listo para enviar por correo.

Ninguno de los dos requiere instalar nada adicional en el servidor
(no usa Composer ni depende de internet); la librería de PDF ya viene
incluida en `/lib/fpdf`.

### Garantías y reparaciones (`garantias.php`)
Tercera línea, con casos propios (CCVM) o de otra marca, garantía o
reparación. Flujo de 5 pasos, cada uno con su foto:

1. **Llegada** — código, cliente, kVA, fase, aceite, tipo de caso,
   marca (propia o de otra empresa) y foto de cómo llega.
2. **Desencube** — foto del estado ya abierto.
3. **Diagnóstico** — qué se dañó, foto del daño, y la decisión
   (reformar la cuba existente o crear una pieza nueva). En cualquiera
   de los dos casos se conserva el mismo código y los mismos emblemas,
   aunque sea de otra marca — queda "como nueva" en apariencia.
4. **Paso por producción** — una vez diagnosticado, el mismo código se
   puede escanear en Metalmecánica, Embobinado y las demás estaciones
   normales de planta, igual que cualquier otra unidad; el sistema
   identifica automáticamente que es un caso de garantía/reparación y
   no permite duplicar el registro en una misma estación.
5. **Cierre** — se escribe qué se le hizo (proceso, reemplazos,
   mejoras) y se sube una foto final. Queda todo el historial completo
   en la ficha del caso: fotos de cada etapa + cada estación de planta
   por la que pasó, con responsable y hora.

Solo el jefe de planta y el programador pueden crear casos y avanzar
las etapas; gerencia puede ver todo en modo consulta.

## Notas / próximos pasos

- El límite de un lote sigue siendo 20.000 unidades por carga.
- La fusión del serial de la bobina con el del pote en Encube (línea
  normal de producción) sigue sin implementarse.
- Cambia `$DB_USER` / `$DB_PASS` en `config.php` si tu MySQL no usa
  usuario `root` sin clave.
