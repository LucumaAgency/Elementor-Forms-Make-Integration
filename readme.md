
# Integración de Formularios Elementor con Webhooks de Make

## Configuración de Formularios

### Formulario 1: Propietarios
- **ID del formulario:** `form-propietarios-make-integration`
- **Webhook URL:** `https://hook.eu2.make.com/ka72ct9l6ojhoxoip26phqgubyu7aeoo`
- **Campos:** nombre, celular, email

### Formulario 2: Inmobiliaria
- **ID del formulario:** `form-inmobiliaria-make-integration`
- **Webhook URL:** `https://hook.eu2.make.com/7lia5xpdbvjtmwi92yna4kl1bvqtst2o`
- **Campos:** nombre, celular, email

## Script de Integración

### Opción 1: Script Unificado (Recomendado)

```php
// En functions.php del tema o en un plugin personalizado

add_action( 'elementor_pro/forms/new_record', function( $record, $handler ) {
    
    // Obtener el ID del formulario
    $form_id = $record->get_form_settings( 'id' );
    
    // Configuración de webhooks por formulario
    $webhook_config = [
        'form-propietarios-make-integration' => 'https://hook.eu2.make.com/ka72ct9l6ojhoxoip26phqgubyu7aeoo',
        'form-inmobiliaria-make-integration' => 'https://hook.eu2.make.com/7lia5xpdbvjtmwi92yna4kl1bvqtst2o'
    ];
    
    // Verificar si el formulario está en nuestra configuración
    if ( ! isset( $webhook_config[ $form_id ] ) ) {
        return; // No es ninguno de nuestros formularios
    }
    
    // Obtener la URL del webhook correspondiente
    $webhook_url = $webhook_config[ $form_id ];
    
    // Obtener todos los campos del formulario
    $raw_fields = $record->get( 'fields' );
    
    // Preparar los datos para enviar
    $data = [];
    
    // Mapear todos los campos disponibles
    foreach ( $raw_fields as $field_id => $field ) {
        $data[ $field_id ] = $field['value'];
    }
    
    // Agregar metadatos adicionales
    $data['form_id'] = $form_id;
    $data['fecha_envio'] = date('Y-m-d H:i:s');
    $data['ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Enviar datos al webhook de Make
    $response = wp_remote_post( $webhook_url, [
        'body' => json_encode( $data ),
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'timeout' => 30,
    ]);
    
    // Log de errores si algo falla
    if ( is_wp_error( $response ) ) {
        error_log( 'Error enviando formulario ' . $form_id . ' a Make: ' . $response->get_error_message() );
    } else {
        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            error_log( 'Make webhook para ' . $form_id . ' respondió con código: ' . $response_code );
        }
    }
    
}, 10, 2 );
```

### Opción 2: Script con Mapeo Específico de Campos

```php
add_action( 'elementor_pro/forms/new_record', function( $record, $handler ) {
    
    $form_id = $record->get_form_settings( 'id' );
    $raw_fields = $record->get( 'fields' );
    
    // Procesar formulario de propietarios
    if ( 'form-propietarios-make-integration' === $form_id ) {
        
        $webhook_url = 'https://hook.eu2.make.com/ka72ct9l6ojhoxoip26phqgubyu7aeoo';
        
        $data = [
            'nombre' => $raw_fields['name']['value'] ?? $raw_fields['nombre']['value'] ?? '',
            'celular' => $raw_fields['phone']['value'] ?? $raw_fields['celular']['value'] ?? '',
            'email' => $raw_fields['email']['value'] ?? '',
            'tipo_formulario' => 'propietarios',
            'fecha' => date('Y-m-d H:i:s')
        ];
        
        wp_remote_post( $webhook_url, [
            'body' => json_encode( $data ),
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        
    // Procesar formulario de inmobiliaria
    } elseif ( 'form-inmobiliaria-make-integration' === $form_id ) {
        
        $webhook_url = 'https://hook.eu2.make.com/7lia5xpdbvjtmwi92yna4kl1bvqtst2o';
        
        $data = [
            'nombre' => $raw_fields['name']['value'] ?? $raw_fields['nombre']['value'] ?? '',
            'celular' => $raw_fields['phone']['value'] ?? $raw_fields['celular']['value'] ?? '',
            'email' => $raw_fields['email']['value'] ?? '',
            'tipo_formulario' => 'inmobiliaria',
            'fecha' => date('Y-m-d H:i:s')
        ];
        
        wp_remote_post( $webhook_url, [
            'body' => json_encode( $data ),
            'headers' => ['Content-Type' => 'application/json'],
        ]);
    }
    
}, 10, 2 );
```

## Script de Debugging

Para identificar los IDs exactos de los campos del formulario:

```php
// Script temporal para debugging - eliminar después de obtener los IDs
add_action( 'elementor_pro/forms/new_record', function( $record, $handler ) {
    
    $form_id = $record->get_form_settings( 'id' );
    $raw_fields = $record->get( 'fields' );
    
    // Solo debug para nuestros formularios
    if ( in_array( $form_id, ['form-propietarios-make-integration', 'form-inmobiliaria-make-integration'] ) ) {
        error_log( 'Form ID: ' . $form_id );
        error_log( 'Campos disponibles: ' . print_r( array_keys($raw_fields), true ) );
        
        foreach ( $raw_fields as $field_id => $field ) {
            error_log( $field_id . ' => ' . $field['value'] );
        }
    }
    
}, 10, 2 );
```

## Instalación

1. **Agregar el código:**
   - Opción A: Pegar en `functions.php` del tema activo
   - Opción B: Crear un plugin personalizado
   - Opción C: Usar un plugin como Code Snippets

2. **Verificar los IDs de campos:**
   - En Elementor, editar cada formulario
   - Revisar el ID de cada campo (Field ID)
   - Asegurarse de que coincidan con el script

3. **Configurar Make:**
   - Crear escenarios en Make
   - Configurar los webhooks para recibir datos
   - Procesar los datos según necesidades (Google Sheets, CRM, email, etc.)

## Pruebas

1. Activar el modo debug de WordPress:
   ```php
   // En wp-config.php
   define( 'WP_DEBUG', true );
   define( 'WP_DEBUG_LOG', true );
   define( 'WP_DEBUG_DISPLAY', false );
   ```

2. Enviar un formulario de prueba

3. Revisar los logs en `/wp-content/debug.log`

4. Verificar en Make que lleguen los datos

## Consideraciones de Seguridad

- Los webhooks están expuestos públicamente
- Considerar agregar autenticación adicional si es necesario
- Implementar rate limiting para prevenir spam
- Validar y sanitizar datos antes de enviar

## Solución de Problemas

### El webhook no recibe datos:
- Verificar que el ID del formulario sea correcto
- Confirmar que la URL del webhook esté activa
- Revisar los logs de WordPress

### Campos vacíos en Make:
- Usar el script de debugging para ver los IDs exactos
- Verificar que los nombres de campos coincidan

### Error de timeout:
- Aumentar el timeout en `wp_remote_post`
- Verificar la conexión a internet del servidor
