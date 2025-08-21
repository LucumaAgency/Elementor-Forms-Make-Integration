<?php
/**
 * Integración específica para Royal Elementor Addons Forms
 * Detecta y procesa formularios de Royal Addons
 */

// Método 1: Hook directo en el procesamiento del formulario
add_action( 'wp_loaded', function() {
    // Detectar si es una petición de formulario Royal Addons
    if ( isset($_POST['form_fields']) && isset($_POST['form_id']) ) {
        error_log( '===== ROYAL ADDONS FORM - PETICIÓN DETECTADA =====' );
        error_log( 'Form ID: ' . $_POST['form_id'] );
        error_log( 'Page ID: ' . ($_POST['post_id'] ?? 'unknown') );
        error_log( 'Referer: ' . ($_POST['referer_title'] ?? 'unknown') );
        
        // Procesar según el form_id
        royal_addons_process_form();
    }
}, 5 );

// Método 2: JavaScript injection para capturar el envío
add_action( 'wp_footer', function() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Detectar formularios Royal Addons
        $('.wpr-form').on('submit', function(e) {
            console.log('Royal Addons Form Submit Detected');
            
            var formData = $(this).serializeArray();
            var formId = $(this).find('input[name="form_id"]').val();
            
            console.log('Form ID:', formId);
            console.log('Form Data:', formData);
            
            // El formulario continuará su proceso normal
        });
        
        // Log para debugging
        if ($('.wpr-form').length > 0) {
            console.log('Royal Addons Forms encontrados:', $('.wpr-form').length);
            $('.wpr-form').each(function() {
                var id = $(this).find('input[name="form_id"]').val();
                console.log('Form ID encontrado:', id);
            });
        }
    });
    </script>
    <?php
}, 100 );

// Función principal de procesamiento
function royal_addons_process_form() {
    $form_id = sanitize_text_field($_POST['form_id'] ?? '');
    $form_fields = $_POST['form_fields'] ?? [];
    
    error_log( '===== PROCESANDO ROYAL ADDONS FORM =====' );
    error_log( 'Form ID: ' . $form_id );
    
    // Configuración de webhooks
    $webhook_config = [
        '0e120d0' => 'https://hook.eu2.make.com/7lia5xpdbvjtmwi92yna4kl1bvqtst2o', // inmobiliaria
        // Agregar más IDs según necesites
    ];
    
    // Buscar webhook correspondiente
    $webhook_url = null;
    
    // Primero buscar por form_id directo
    if ( isset($webhook_config[$form_id]) ) {
        $webhook_url = $webhook_config[$form_id];
        error_log( 'Webhook encontrado por form_id: ' . $form_id );
    }
    
    // Si no se encuentra, buscar por página
    if ( !$webhook_url && isset($_POST['post_id']) ) {
        $post_id = $_POST['post_id'];
        $post_title = get_the_title($post_id);
        
        error_log( 'Página: ' . $post_title . ' (ID: ' . $post_id . ')' );
        
        if ( stripos($post_title, 'inmobiliaria') !== false ) {
            $webhook_url = 'https://hook.eu2.make.com/7lia5xpdbvjtmwi92yna4kl1bvqtst2o';
            error_log( 'Webhook asignado por página: Inmobiliaria' );
        } elseif ( stripos($post_title, 'propietario') !== false ) {
            $webhook_url = 'https://hook.eu2.make.com/ka72ct9l6ojhoxoip26phqgubyu7aeoo';
            error_log( 'Webhook asignado por página: Propietarios' );
        }
    }
    
    if ( !$webhook_url ) {
        error_log( 'No se encontró webhook para form_id: ' . $form_id );
        return;
    }
    
    // Preparar datos
    $data = [];
    foreach ( $form_fields as $field_name => $field_value ) {
        $data[$field_name] = sanitize_text_field($field_value);
        error_log( "Campo: $field_name => $field_value" );
    }
    
    // Agregar metadatos
    $data['form_id'] = $form_id;
    $data['timestamp'] = date('Y-m-d H:i:s');
    $data['ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
    $data['page_url'] = $_SERVER['HTTP_REFERER'] ?? '';
    
    // Log del payload
    error_log( 'Enviando a webhook: ' . $webhook_url );
    error_log( 'Payload: ' . json_encode($data, JSON_PRETTY_PRINT) );
    
    // Enviar a Make
    $response = wp_remote_post( $webhook_url, [
        'body' => json_encode( $data ),
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'timeout' => 30,
    ]);
    
    if ( is_wp_error( $response ) ) {
        error_log( '❌ Error enviando a Make: ' . $response->get_error_message() );
    } else {
        $response_code = wp_remote_retrieve_response_code( $response );
        error_log( 'Respuesta de Make - Código: ' . $response_code );
        
        if ( $response_code === 200 || $response_code === 204 ) {
            error_log( '✅ Formulario Royal Addons enviado exitosamente' );
        }
    }
    
    error_log( '===== FIN PROCESAMIENTO ROYAL ADDONS =====' );
}

// Hook adicional para debugging
add_action( 'admin_init', function() {
    if ( isset($_GET['test_royal_forms']) ) {
        error_log( '===== TEST ROYAL FORMS ACTIVO =====' );
        error_log( 'El plugin Royal Addons Integration está cargado' );
    }
});