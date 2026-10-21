# Mikesoft TeamVault

Gestión documental privada para equipos WordPress, con almacenamiento protegido y control de acceso granular

[![CI](https://github.com/TheStreamCode/mikesoft-teamvault/actions/workflows/ci.yml/badge.svg)](https://github.com/TheStreamCode/mikesoft-teamvault/actions/workflows/ci.yml)
[![WordPress.org](https://img.shields.io/wordpress/plugin/v/mikesoft-teamvault?label=WordPress.org)](https://wordpress.org/plugins/mikesoft-teamvault/)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-blue.svg)](LICENSE)

![Banner de TeamVault para la ficha del plugin en WordPress.org](.wordpress-org/assets/banner-772x250.png)

[English](README.md) · [Italiano](README.it.md) · [Français](README.fr.md) · **Español** · [Deutsch](README.de.md)

Mikesoft TeamVault añade un espacio de trabajo documental privado dentro del administrador de WordPress para equipos, agencias, socios y áreas de back-office que necesitan un acceso controlado a archivos compartidos. Los documentos se almacenan fuera de la Biblioteca de medios y se distribuyen mediante gestores autenticados de WordPress en lugar de URL públicas de medios. Este repositorio es el espejo público del código fuente: las instalaciones y actualizaciones llegan desde [WordPress.org](https://wordpress.org/plugins/mikesoft-teamvault/), y el soporte a usuarios se gestiona en el [foro de soporte oficial](https://wordpress.org/support/plugin/mikesoft-teamvault/).

Mikesoft TeamVault es un plugin independiente; no está afiliado, patrocinado ni respaldado por WordPress, Automattic o la WordPress Foundation.

![Gestor de archivos de TeamVault, con árbol de carpetas, tarjetas de archivo con iconos por tipo y miniaturas de imágenes, y panel de detalles con vista previa integrada](.wordpress-org/assets/screenshot-1.jpg)

## Instalación

1. En el administrador de WordPress, ve a `Plugins > Add New`.
2. Busca `Mikesoft TeamVault`.
3. Haz clic en `Install Now` y activa el plugin.
4. Abre `TeamVault > Settings` para revisar las reglas de acceso, almacenamiento y archivos.

Alternativa manual: descarga el paquete de la versión desde [WordPress.org](https://wordpress.org/plugins/mikesoft-teamvault/), súbelo a `wp-content/plugins/mikesoft-teamvault/` y actívalo desde la pantalla de Plugins.

## Inicio rápido

1. Abre `TeamVault > Settings` y revisa la ruta de almacenamiento, las extensiones permitidas y el límite de subida.
2. Concede la capability `manage_private_documents` solo a los roles o usuarios que necesiten acceso al vault.
3. Crea grupos cuando los permisos deban seguir a departamentos o equipos de proyecto, y después crea las carpetas de documentos.
4. Sube un archivo desde la cuadrícula o la vista de lista y comprueba el comportamiento de vista previa y descarga con una cuenta que no sea administradora.

## Capturas de pantalla

| Permisos por carpeta | Búsqueda en el vault | Cuotas de almacenamiento |
| :---: | :---: | :---: |
| [![Control de acceso por carpeta](.wordpress-org/assets/screenshot-2.jpg)](.wordpress-org/assets/screenshot-2.jpg) | [![Búsqueda con distintivos de tipo de archivo](.wordpress-org/assets/screenshot-3.jpg)](.wordpress-org/assets/screenshot-3.jpg) | [![Cuotas por usuario y por grupo](.wordpress-org/assets/screenshot-4.jpg)](.wordpress-org/assets/screenshot-4.jpg) |
| **Grupos** | **Registro de actividad** | **Ajustes** |
| [![Grupos de usuarios](.wordpress-org/assets/screenshot-5.jpg)](.wordpress-org/assets/screenshot-5.jpg) | [![Registro de auditoría](.wordpress-org/assets/screenshot-6.jpg)](.wordpress-org/assets/screenshot-6.jpg) | [![Ajustes del plugin](.wordpress-org/assets/screenshot-7.jpg)](.wordpress-org/assets/screenshot-7.jpg) |

## Funcionalidades

- Almacenamiento privado fuera del flujo habitual de la Biblioteca de medios.
- Controles de acceso por capability, lista de permitidos opcional, usuarios, grupos y carpetas, con herencia y anulaciones explícitas en las subcarpetas.
- Operaciones sobre carpetas (creación, cambio de nombre, movimiento, eliminación) y subidas validadas mediante arrastrar y soltar con vistas previas integradas, incluidos los PDF.
- Acceso de solo vista previa que bloquea descargas y exportaciones ZIP.
- Cuotas de almacenamiento por usuario y por grupo, aplicadas antes de la subida.
- Informes de acceso, registro de actividad, exportación CSV y notificaciones por correo electrónico que tienen en cuenta la carpeta para subidas, descargas, eliminaciones y accesos denegados.
- Exportación ZIP, limpieza de archivos huérfanos y herramientas de mantenimiento para la reindexación del almacenamiento.
- Interfaces de administración en inglés, italiano, francés, español y alemán.

Todas las funcionalidades de gobernanza están incluidas en el plugin gratuito.

## Requisitos

- WordPress 6.0 o posterior.
- PHP 8.0 o posterior.
- Una ruta de almacenamiento escribible para los documentos privados.
- `ZipArchive` en el servidor para las funciones de exportación.

## Configuración

Abre `TeamVault > Settings` después de la activación. Los ajustes principales son:

| Ajuste | Propósito | Valor predeterminado |
| --- | --- | --- |
| Idioma de la interfaz | Sigue la configuración regional de WordPress o fuerza un idioma compatible | Automático |
| Lista de usuarios permitidos | Añade una lista explícita de usuarios autorizados además de la capability requerida | Desactivada |
| Extensiones permitidas | Restringe las subidas a los tipos documento, imagen, archivo comprimido, audio y vídeo configurados | Lista segura integrada |
| Tamaño máximo de archivo | Aplica un límite de subida del plugin además de los límites de PHP y del servidor web | 50 MB |
| Vista previa PDF | Permite vistas previas PDF integradas y autenticadas | Activada |
| Registro de actividad | Registra las operaciones sobre documentos para auditoría e informes | Activado |
| Eliminar datos al desinstalar | Elimina tablas, opciones, capabilities y almacenamiento marcado del plugin al desinstalar | Desactivado |

Los grupos, los permisos por carpeta, las cuotas, los informes y las notificaciones tienen páginas dedicadas en el administrador. Configura el acceso antes de invitar a usuarios que no sean administradores.

La configuración en tiempo de ejecución se guarda en las opciones de WordPress; TeamVault no lee ajustes de variables de entorno ni de un archivo `.env` del repositorio. Mantén las credenciales de la base de datos y los salts en el `wp-config.php` del sitio.

## Privacidad y seguridad

Los archivos privados nunca se sirven mediante URL públicas de medios: las descargas y las vistas previas pasan por gestores autenticados de WordPress con respuestas privadas y no-cache.

- El acceso al vault usa la capability `manage_private_documents`; las nuevas activaciones solo la conceden a los administradores.
- La lista de permitidos opcional añade una segunda capa de autorización para los usuarios seleccionados.
- Los permisos por carpeta limitan el acceso a los usuarios, grupos y acciones concedidos, con herencia desde las carpetas principales. Las carpetas sin reglas mantienen el comportamiento basado en la capability, y los administradores conservan siempre el acceso completo.
- Los ajustes, grupos, cuotas, notificaciones, informes, registros, gestión de la lista de permitidos, herramientas de mantenimiento y controles de datos al desinstalar requieren `manage_options`.
- El almacenamiento predeterminado es `wp-content/uploads/private-documents/` y está protegido con archivos de denegación a nivel de servidor: el archivo `.htaccess` generado en Apache y LiteSpeed, el archivo `web.config` en IIS y una regla Nginx equivalente que deniega las solicitudes directas a esa ruta.
- Para despliegues de alta sensibilidad, prefiere una ruta de almacenamiento personalizada fuera de la raíz web pública. El widget de almacenamiento muestra solo el uso de TeamVault.

Consulta [`SECURITY.md`](SECURITY.md) para reportar vulnerabilidades.

## Solución de problemas

### La exportación ZIP falla

La exportación requiere la extensión PHP `ZipArchive` y una ruta de almacenamiento escribible. Activa la extensión o pide al proveedor de alojamiento que la active y vuelve a intentarlo.

### Un administrador queda bloqueado tras activar la lista de permitidos

La lista de permitidos es una lista positiva. Mantén la cuenta de administrador actual en la lista de usuarios permitidos antes de guardar los ajustes.

### Faltan archivos después de migrar el sitio

Un sitio migrado sin la carpeta de almacenamiento privado mantiene los registros de TeamVault en la base de datos mientras faltan los archivos originales. Copia el directorio de almacenamiento durante las migraciones y usa después las herramientas de limpieza y reindexación de la pantalla de ajustes tras confirmar la copia.

### Las subidas se rechazan

Comprueba las extensiones permitidas, el tamaño máximo de archivo y los límites de subida de PHP. TeamVault valida la extensión, el tipo MIME detectado, el tamaño y los patrones de contenido peligroso antes de almacenar un archivo, e indica el tipo de contenido detectado cuando no coincide.

## Desarrollo

El desarrollo requiere PHP 8.2 o posterior para PHPUnit 11, Composer 2, Node.js 24 para el tooling JavaScript y Plugin Check, y Git; el plugin en sí es compatible con PHP 8.0 o posterior.

Instala las dependencias de desarrollo bloqueadas y ejecuta las comprobaciones PHP estándar:

```bash
composer install --no-interaction --prefer-dist
composer validate --strict
composer audit --locked
composer ci
```

`composer ci` ejecuta el lint PHP y la suite PHPUnit. GitHub Actions ejecuta además WordPress Plugin Check sobre un paquete de ejecución limpio.

Comprobaciones adicionales:

```bash
node --check assets/js/admin-app-core.js
node --check assets/js/admin-app-governance.js
node --check assets/js/admin-app.js
node --check assets/js/admin-notice-dismiss.js
node --test tests/plugin-check-output.test.mjs
```

No existe una fase de compilación de assets: el PHP, el CSS y el JavaScript de ejecución se versionan como código fuente. `git archive --format=zip --prefix=mikesoft-teamvault/ --output=mikesoft-teamvault.zip HEAD` genera un paquete desde una revisión confirmada usando las exclusiones de exportación de `.gitattributes`.

Notas para mantenedores y desarrolladores:

- [`docs/developer/hooks.md`](docs/developer/hooks.md) - hooks y filtros
- [`docs/developer/i18n.md`](docs/developer/i18n.md) - flujo de trabajo de traducción
- [`docs/maintainer/local-development.md`](docs/maintainer/local-development.md) - desarrollo local y QA manual
- [`docs/maintainer/release.md`](docs/maintainer/release.md) - publicación y despliegue en WordPress.org
- [`docs/maintainer/security-review.md`](docs/maintainer/security-review.md) - última revisión de seguridad

## Soporte

- Soporte para usuarios finales: [foro de soporte de WordPress.org](https://wordpress.org/support/plugin/mikesoft-teamvault/)
- Correo electrónico: [teamvault@mikesoft.it](mailto:teamvault@mikesoft.it)
- Sitio web: [mikesoft.it](https://mikesoft.it)
- Reportes de seguridad: consulta [`SECURITY.md`](SECURITY.md), nunca una issue pública.

## Contribución

Este repositorio es el espejo público del código fuente del plugin. No se aceptan pull requests externas; consulta [`CONTRIBUTING.md`](CONTRIBUTING.md) para la política de reportes y el flujo de trabajo de los mantenedores.

## Licencia

GPL-2.0-or-later - consulta [`LICENSE`](LICENSE).

---

Maintained by [Michael Gasperini (Mikesoft)](https://mikesoft.it). Si el proyecto te resulta útil, valora [patrocinar su mantenimiento](https://github.com/sponsors/TheStreamCode).

