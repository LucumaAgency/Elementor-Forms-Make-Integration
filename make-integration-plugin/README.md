# Make Integration for Forms

Plugin de WordPress para integrar formularios de Elementor y WPR Forms (Royal Addons) con webhooks de Make (Integromat).

## 🚀 Instalación

### Opción 1: Instalación como Plugin

1. Descarga todos los archivos de la carpeta `make-integration-plugin`
2. Crea una carpeta llamada `make-integration` en `/wp-content/plugins/`
3. Sube todos los archivos a esa carpeta
4. Ve a **WordPress Admin > Plugins**
5. Busca "Make Integration for Forms" y actívalo

### Opción 2: Instalación Manual

Si prefieres no usar un plugin, puedes copiar el contenido de `functions.php` directamente en el archivo `functions.php` de tu tema activo.

## ⚙️ Configuración

### 1. Activar el modo debug (temporal, para verificar que funciona)

En `wp-config.php`, agrega:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

### 2. Configurar IDs de formularios

#### Para formularios WPR (Royal Addons):

En el div contenedor del formulario, agrega el ID correspondiente:

- **Formulario Propietarios**: `id="make-integration-propietarios"`
- **Formulario Inmobiliaria**: `id="make-integration-inmobiliaria"`

Ejemplo en el HTML:
```html
<div id="make-integration-inmobiliaria" class="elementor-element...">
    <form>...</form>
</div>
```

#### Para formularios Elementor Pro:

Usa estos IDs en la configuración del formulario:
- **Propietarios**: `form-propietarios-make-integration`
- **Inmobiliaria**: `form-inmobiliaria-make-integration`

### 3. Webhooks de Make

Los webhooks ya están configurados en el plugin:

- **Propietarios**: `https://hook.eu2.make.com/ka72ct9l6ojhoxoip26phqgubyu7aeoo`
- **Inmobiliaria**: `https://hook.eu2.make.com/7lia5xpdbvjtmwi92yna4kl1bvqtst2o`

## 📊 Monitoreo

### Ver logs en tiempo real

Los logs se guardan en `/wp-content/debug.log`. Puedes ver:

- ✅ Envíos exitosos
- ❌ Errores de envío
- ⚠️ Advertencias de validación
- Todos los campos capturados
- Respuestas de Make

### Panel de administración

Si usas el plugin completo, tendrás acceso a:
- Un panel en el admin bar: "🔌 Make Integration"
- Vista de logs filtrados
- Botón para limpiar logs

## 🔍 Debugging

### Verificar que el formulario se está capturando

1. Envía un formulario de prueba
2. Revisa `/wp-content/debug.log`
3. Busca líneas que contengan:
   - `===== INICIO PROCESAMIENTO FORMULARIO =====`
   - `Form ID recibido:`
   - `Campos disponibles:`

### Campos enviados a Make

El plugin envía estos datos:
- Todos los campos del formulario (nombre, email, teléfono, etc.)
- `form_id`: ID del formulario
- `timestamp`: Fecha y hora de envío
- `ip`: IP del usuario
- `user_agent`: Navegador del usuario
- `page_id`: ID de la página
- `page_title`: Título de la página

## 🛠️ Solución de problemas

### El formulario no se envía a Make

1. Verifica que el ID del formulario esté configurado correctamente
2. Revisa los logs para ver si se detecta el formulario
3. Confirma que el webhook de Make esté activo

### No aparecen logs

1. Asegúrate de que `WP_DEBUG` y `WP_DEBUG_LOG` estén en `true`
2. Verifica permisos de escritura en `/wp-content/`
3. Revisa que el plugin esté activado

### Campos vacíos en Make

1. Revisa los logs para ver qué campos se están capturando
2. Verifica los nombres de los campos en el formulario
3. Asegúrate de que los campos tengan el atributo `name` correcto

## 📝 Personalización

Para agregar más formularios, edita el array `$webhook_config` en `functions.php`:

```php
$webhook_config = [
    'tu-form-id' => 'https://hook.eu2.make.com/tu-webhook-url',
    // Agrega más formularios aquí
];
```

## 🤝 Soporte

Si encuentras algún problema, revisa primero los logs en `/wp-content/debug.log` para obtener información detallada sobre qué está ocurriendo.

## 📄 Licencia

Este plugin es de código abierto y está disponible bajo la licencia GPL v2.