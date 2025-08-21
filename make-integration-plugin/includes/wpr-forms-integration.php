<?php
/**
 * Integración específica para WPR Forms (Royal Addons)
 * Este archivo maneja la captura de formularios WPR que usan AJAX
 */

// Hook temprano para capturar datos POST antes del procesamiento
add_action( 'init', 'wpr_forms_capture_early' );

// Hook específico para Royal Addons Forms
add_action( 'wp_ajax_wpr_addons_form_builder', 'wpr_royal_form_handler', 1 );
add_action( 'wp_ajax_nopriv_wpr_addons_form_builder', 'wpr_royal_form_handler', 1 );

// Hook alternativo para el submit action
add_action( 'wp_ajax_wpr_form_submit_action', 'wpr_royal_form_handler', 1 );
add_action( 'wp_ajax_nopriv_wpr_form_submit_action', 'wpr_royal_form_handler', 1 );

function wpr_royal_form_handler() {
    error_log( '===== ROYAL ADDONS FORM DETECTADO =====' );
    error_log( 'Action: ' . ($_POST['action'] ?? 'no action') );
    error_log( 'Todos los datos POST:' );
    error_log( print_r( $_POST, true ) );
    
    procesar_wpr_form_submission();
}

function wpr_forms_capture_early() {
    // Log para debugging de cualquier petición POST con form_fields
    if ( ! empty( $_POST['form_fields'] ) ) {
        error_log( '===== FORM FIELDS DETECTADOS EN POST =====' );
        error_log( 'Action: ' . ($_POST['action'] ?? 'no action') );
        error_log( 'Form ID: ' . ($_POST['form_id'] ?? 'no form_id') );
        error_log( 'Form Fields: ' . print_r($_POST['form_fields'], true) );
    }
    
    // Solo procesar si es una petición AJAX de WPR Forms
    if ( ! empty( $_POST['action'] ) && 
         ( $_POST['action'] === 'wpr_form_builder_submit' || 
           $_POST['action'] === 'wpr_addons_form_builder' ||
           strpos($_POST['action'], 'wpr_') === 0 ) ) {
        error_log( '===== WPR FORM AJAX DETECTADO (EARLY) =====' );
        error_log( 'Todos los datos POST:' );
        error_log( print_r( $_POST, true ) );
        
        // Procesar el formulario
        procesar_wpr_form_submission();
    }
}

function procesar_wpr_form_submission() {
    
    // Obtener el form_id del POST
    $form_id = isset($_POST['form_id']) ? sanitize_text_field($_POST['form_id']) : '';
    
    // Obtener los campos del formulario
    $form_fields = isset($_POST['form_fields']) ? $_POST['form_fields'] : [];
    
    error_log( '===== PROCESAMIENTO WPR FORM =====' );
    error_log( 'Form ID detectado: ' . $form_id );
    error_log( 'Campos del formulario:' );
    
    // Preparar datos limpios
    $clean_data = [];
    foreach ( $form_fields as $field_name => $field_value ) {
        $clean_data[$field_name] = sanitize_text_field($field_value);
        error_log( "Campo: $field_name => Valor: $field_value" );
    }
    
    // Configuración de webhooks
    $webhook_config = [
        // Widget IDs de WPR Forms
        '0e120d0' => 'https://hook.eu2.make.com/7lia5xpdbvjtmwi92yna4kl1bvqtst2o', // inmobiliaria nuevo
        '23aef76' => 'https://hook.eu2.make.com/7lia5xpdbvjtmwi92yna4kl1bvqtst2o', // inmobiliaria viejo
        // Agregar aquí el ID del formulario de propietarios cuando lo obtengas
    ];
    
    // Verificar si tenemos configuración para este formulario
    if ( ! isset( $webhook_config[$form_id] ) ) {
        error_log( 'Formulario WPR no configurado: ' . $form_id );
        
        // Buscar en el referer o página
        if ( isset($_POST['post_id']) ) {
            $post_id = $_POST['post_id'];
            $post_title = get_the_title($post_id);
            error_log( 'Página del formulario: ' . $post_title . ' (ID: ' . $post_id . ')' );
            
            // Intentar determinar el webhook basado en la página
            if ( stripos($post_title, 'inmobiliaria') !== false ) {
                $webhook_url = 'https://hook.eu2.make.com/7lia5xpdbvjtmwi92yna4kl1bvqtst2o';
                error_log( 'Webhook asignado por título de página: Inmobiliaria' );
            } elseif ( stripos($post_title, 'propietario') !== false ) {
                $webhook_url = 'https://hook.eu2.make.com/ka72ct9l6ojhoxoip26phqgubyu7aeoo';
                error_log( 'Webhook asignado por título de página: Propietarios' );
            } else {
                error_log( 'No se pudo determinar el webhook para esta página' );
                return;
            }
        } else {
            return;
        }
    } else {
        $webhook_url = $webhook_config[$form_id];
    }
    
    // Agregar metadatos
    $clean_data['form_id'] = $form_id;
    $clean_data['timestamp'] = date('Y-m-d H:i:s');
    $clean_data['ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
    $clean_data['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $clean_data['page_id'] = $_POST['post_id'] ?? '';
    $clean_data['page_title'] = isset($_POST['post_id']) ? get_the_title($_POST['post_id']) : '';
    
    // Log del payload
    error_log( 'Enviando a Make webhook: ' . $webhook_url );
    error_log( 'Payload:' );
    error_log( json_encode($clean_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) );
    
    // Enviar a Make
    $response = wp_remote_post( $webhook_url, [
        'body' => json_encode( $clean_data ),
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'timeout' => 30,
    ]);
    
    if ( is_wp_error( $response ) ) {
        error_log( '❌ Error enviando a Make: ' . $response->get_error_message() );
    } else {
        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        
        error_log( 'Respuesta de Make:' );
        error_log( 'Código: ' . $response_code );
        error_log( 'Body: ' . $response_body );
        
        if ( $response_code === 200 || $response_code === 204 ) {
            error_log( '✅ Formulario WPR enviado exitosamente a Make' );
        } else {
            error_log( '⚠️ Make respondió con código: ' . $response_code );
        }
    }
    
    error_log( '===== FIN PROCESAMIENTO WPR FORM =====' );
}