# Mikesoft TeamVault

[![CI](https://github.com/TheStreamCode/mikesoft-teamvault/actions/workflows/ci.yml/badge.svg)](https://github.com/TheStreamCode/mikesoft-teamvault/actions/workflows/ci.yml)
[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/mikesoft-teamvault?label=WordPress.org)](https://wordpress.org/plugins/mikesoft-teamvault/)
[![WordPress Tested](https://img.shields.io/wordpress/plugin/tested/mikesoft-teamvault?label=Tested%20up%20to)](https://wordpress.org/plugins/mikesoft-teamvault/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](LICENSE)
[![Sponsor](https://img.shields.io/badge/Sponsor-%E2%9D%A4-db61a2?logo=githubsponsors&logoColor=white)](https://github.com/sponsors/TheStreamCode)

[English](README.md) · [Italiano](README.it.md) · [Français](README.fr.md) · **Español** · [Deutsch](README.de.md)

Espacio de trabajo de documentos privados para equipos, agencias y operaciones de WordPress que necesitan compartir archivos de forma controlada fuera de la Biblioteca de medios.

Versión actual del plugin: `3.2.2`.

**Más de 2.000 descargas totales** en WordPress.org, con decenas de nuevas descargas cada día.

Si TeamVault te resulta útil, considera [patrocinar el proyecto en GitHub](https://github.com/sponsors/TheStreamCode): se desarrolla y mantiene de forma gratuita, y los patrocinios ayudan a que siga adelante.

## Descripción general

Mikesoft TeamVault añade un espacio de trabajo de documentos privados dentro del administrador de WordPress.
Está diseñado para equipos que necesitan organizar, previsualizar, exportar y compartir archivos confidenciales sin exponerlos a través de las URL habituales de la Biblioteca de medios.

Los archivos se almacenan en un almacenamiento protegido y se entregan a través de gestores autenticados de WordPress en lugar de URL de medios públicas.

![Gestor de archivos de TeamVault — árbol de carpetas, tarjetas de archivo con iconos por tipo y miniaturas, panel de detalles con vista previa integrada](.wordpress-org/assets/screenshot-1.jpg)

| Permisos por carpeta | Búsqueda en el vault | Cuotas de almacenamiento |
| :---: | :---: | :---: |
| [![Control de acceso por carpeta](.wordpress-org/assets/screenshot-2.jpg)](.wordpress-org/assets/screenshot-2.jpg) | [![Búsqueda con distintivos de tipo de archivo](.wordpress-org/assets/screenshot-3.jpg)](.wordpress-org/assets/screenshot-3.jpg) | [![Cuotas por usuario y por grupo](.wordpress-org/assets/screenshot-4.jpg)](.wordpress-org/assets/screenshot-4.jpg) |
| **Grupos** | **Registro de actividad** | **Ajustes** |
| [![Grupos de usuarios](.wordpress-org/assets/screenshot-5.jpg)](.wordpress-org/assets/screenshot-5.jpg) | [![Registro de auditoría](.wordpress-org/assets/screenshot-6.jpg)](.wordpress-org/assets/screenshot-6.jpg) | [![Ajustes del plugin](.wordpress-org/assets/screenshot-7.jpg)](.wordpress-org/assets/screenshot-7.jpg) |

Los casos de uso típicos incluyen:

- documentos internos de la empresa
- entrega de documentos de agencia a cliente desde el administrador de WordPress
- intercambios de archivos con socios o proveedores
- archivos administrativos que deben mantenerse fuera de la Biblioteca de medios pública

Las capacidades principales incluyen:

- almacenamiento privado fuera del flujo de trabajo habitual de la Biblioteca de medios
- acceso compartido para usuarios internos autorizados
- operaciones de creación, cambio de nombre, movimiento y eliminación de carpetas
- subidas mediante arrastrar y soltar con validación de archivos
- vista previa integrada para los tipos de archivo compatibles, incluidos los PDF
- exportación en ZIP para carpetas o para toda la biblioteca de documentos
- registro de actividad para la trazabilidad operativa
- herramientas de mantenimiento para la limpieza de archivos huérfanos y la reindexación del almacenamiento

Capacidades de gobernanza (todas gratuitas, desde la versión 2.6):

- grupos de TeamVault para organizar a los usuarios en departamentos o equipos, independientemente de los roles de WordPress
- permisos por carpeta con acciones granulares (ver, subir, descargar, eliminar, gestionar) para usuarios y grupos, con herencia y anulaciones explícitas en las subcarpetas
- acceso de solo vista previa que permite la visualización sin descarga ni exportación en ZIP
- cuotas de almacenamiento por usuario y por grupo, aplicadas antes de la subida
- informes de acceso (quién vio o descargó qué) con filtros y una exportación en CSV del registro de actividad
- notificaciones por correo electrónico para los eventos de subida, descarga, eliminación y acceso denegado

## Última versión

La versión `3.2.2` renueva los **iconos de tipo de archivo** en todo el gestor de archivos: los archivos PDF, Word, Excel, PowerPoint, CSV, texto, archivo comprimido, audio, vídeo e imagen ahora muestran distintivos de color claros y reconocibles con la etiqueta del formato — en la cuadrícula, la vista de lista y la vista previa del panel de detalles — en lugar de los antiguos glifos monocromos.

La versión `3.0.0` es un hito de seguridad y fiabilidad. Los resultados de búsqueda ahora se filtran a través del motor de permisos por carpeta, de modo que los usuarios restringidos ya no pueden descubrir nombres de archivo ni metadatos de carpetas que no pueden ver. El archivo `.htaccess` de almacenamiento generado deniega el acceso directo en Apache 2.4 además de en Apache 2.2 e IIS, y las cuotas de almacenamiento se aplican con un bloqueo de base de datos para que las subidas concurrentes no puedan superar conjuntamente un límite. Las descargas y las vistas previas integradas incorporan compatibilidad con HTTP Range (`Accept-Ranges` / `206 Partial Content`) para transferencias reanudables y visores de PDF con búsqueda por rangos en archivos grandes. El cuadro de diálogo de permisos de carpeta ahora advierte cuando existen reglas pero la raíz no tiene ninguna, el icono del menú de administración coincide con el estilo nativo de WordPress, y el JavaScript de administración se dividió en módulos específicos sin cambios en el comportamiento.

La versión `2.6` introdujo la **suite de gobernanza** de documentos gratuita: grupos de TeamVault, permisos por carpeta con herencia y acciones granulares (ver, subir, descargar, eliminar, gestionar), acceso de solo vista previa, cuotas de almacenamiento por usuario y por grupo, informes de acceso con exportación en CSV, notificaciones por correo electrónico. Las instalaciones existentes no se ven afectadas, porque las carpetas sin reglas mantienen el comportamiento anterior.

Por qué los equipos adoptan TeamVault:

- crea un área de documentos privados dedicada en lugar de sobrecargar la Biblioteca de medios
- añade control de acceso basado en capacidades con una capa opcional de lista blanca, además de permisos por carpeta y grupos para una gobernanza más precisa
- mantiene los flujos de trabajo de exportación, mantenimiento y recuperación centrados en los archivos operativos

## Requisitos

- WordPress 6.0 o posterior
- PHP 8.0 o posterior
- Ruta de almacenamiento con permisos de escritura para los documentos privados
- `ZipArchive` disponible en el servidor para las funciones de exportación

## Instalación

### Recomendada

Instala el plugin desde el [Directorio de plugins de WordPress.org](https://wordpress.org/plugins/mikesoft-teamvault/) para que el sitio reciba las notificaciones de actualización estándar.

1. En el administrador de WordPress, ve a `Plugins > Añadir nuevo`.
2. Busca `Mikesoft TeamVault`.
3. Haz clic en `Instalar ahora` y activa el plugin.
4. Abre `TeamVault > Ajustes` para revisar las reglas de acceso, almacenamiento y archivos.

### Manual

1. Descarga el paquete de la versión desde WordPress.org.
2. Súbelo a `wp-content/plugins/mikesoft-teamvault/`.
3. Activa el plugin desde la pantalla de Plugins.

## Modelo de acceso

- El acceso al espacio de trabajo de archivos utiliza la capacidad `manage_private_documents`.
- Las nuevas activaciones conceden esa capacidad únicamente a los Administradores.
- La capacidad `manage_private_documents` concede acceso completo al espacio de trabajo de TeamVault, incluidas las acciones de subida, cambio de nombre, movimiento, descarga, exportación y eliminación.
- El modo de lista blanca opcional añade una segunda capa de autorización para usuarios seleccionados.
- Los permisos por carpeta (desde la versión 2.6) añaden un control detallado sobre la capacidad: cuando una carpeta tiene reglas explícitas, el acceso se limita a los usuarios/grupos y acciones concedidos, con herencia de las carpetas superiores; las carpetas sin reglas mantienen el comportamiento basado en capacidades. Los Administradores siempre conservan el acceso completo.
- Los ajustes, grupos, cuotas, notificaciones, informes, registros de actividad, gestión de la lista blanca, herramientas de mantenimiento y controles de datos de desinstalación requieren `manage_options`.

Cuando el modo de lista blanca está activado, mantén la cuenta de administrador actual en la lista de usuarios permitidos antes de guardar los ajustes.
En sitios actualizados desde versiones anteriores, revisa las capacidades de rol y los ajustes de la lista blanca existentes si los Editores tenían acceso a TeamVault anteriormente.

## Almacenamiento

- Ruta de almacenamiento predeterminada: `wp-content/uploads/private-documents/`
- El plugin puede usar una ruta personalizada con permisos de escritura configurada en los ajustes.
- El almacenamiento está protegido con archivos de denegación a nivel de servidor cuando es compatible.
- Apache/LiteSpeed pueden aplicar el `.htaccess` generado; IIS puede aplicar `web.config`; Nginx requiere una regla de servidor equivalente que deniegue las solicitudes directas a `/wp-content/uploads/private-documents/`.
- Para despliegues de alta sensibilidad, es preferible una ruta de almacenamiento personalizada fuera del directorio raíz web público.
- El widget de almacenamiento de la barra lateral muestra únicamente el espacio utilizado por los archivos de TeamVault, para evitar exponer valores de cuota de alojamiento engañosos en entornos compartidos.

Si un sitio se migra sin copiar la carpeta de almacenamiento privado, los registros de TeamVault pueden permanecer en la base de datos mientras faltan los binarios originales. La pantalla de ajustes incluye herramientas de limpieza y reindexación para esos escenarios.

## Soporte

- Soporte para usuarios finales: [foro de soporte de WordPress.org](https://wordpress.org/support/plugin/mikesoft-teamvault/)
- Correo electrónico: [teamvault@mikesoft.it](mailto:teamvault@mikesoft.it)
- Sitio web: [mikesoft.it](https://mikesoft.it)
- Informes de seguridad: consulta [SECURITY.md](SECURITY.md)
- Apoya el mantenimiento continuo de código abierto: [GitHub Sponsors](https://github.com/sponsors/TheStreamCode)

## Comprobación rápida de desarrollo

Instala las dependencias de desarrollo con Composer y luego ejecuta los comandos de validación estándar:

```bash
composer install
composer lint
composer test
composer ci
```

`composer lint` comprueba todos los archivos PHP del repositorio fuera de las dependencias generadas. `composer test` ejecuta la suite ligera de PHPUnit con el bootstrap del repositorio. GitHub Actions también ejecuta WordPress Plugin Check contra una compilación limpia del entorno de ejecución del plugin.

## Guía del repositorio

Este repositorio es el espejo público del código fuente del plugin.

- La información de producto e instalación para los usuarios de WordPress.org se encuentra en [`readme.txt`](readme.txt).
- El historial completo de versiones se encuentra en [`changelog.txt`](changelog.txt).
- Las políticas del repositorio se encuentran en [`CONTRIBUTING.md`](CONTRIBUTING.md), [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md) y [`SECURITY.md`](SECURITY.md).
- Las notas para responsables de mantenimiento y desarrolladores se encuentran en [`docs/`](docs/).

## Recursos de marca

- `.wordpress-org/assets/icon-256x256.png` es el icono principal a todo color para la ficha de WordPress.org.
- `.wordpress-org/assets/icon.svg` es el recurso escalable complementario para la ficha de WordPress.org.
- `.wordpress-org/assets/screenshot-1.jpg` es la captura de pantalla principal del gestor de archivos utilizada por la ficha de WordPress.org y por este README.
- `assets/logo-teamvault.svg` es el logotipo de administración interno del plugin utilizado dentro de la interfaz de TeamVault.

Estos recursos sirven a superficies diferentes y deben mantenerse alineados con la misma marca sin forzar que la interfaz del plugin en ejecución coincida con las restricciones de empaquetado de WordPress.org.

## Mapa de documentación

- [`docs/developer/hooks.md`](docs/developer/hooks.md) - hooks y filtros para desarrolladores
- [`docs/maintainer/local-development.md`](docs/maintainer/local-development.md) - flujo de trabajo de desarrollo local
- [`docs/maintainer/release.md`](docs/maintainer/release.md) - proceso de publicación en WordPress.org

## Licencia

GPL v2 o posterior. Consulta [LICENSE](LICENSE).
