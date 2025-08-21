<?php
/**
 * Integración de Formularios Elementor con Webhooks de Make
 * Con logging detallado para debugging
 */

// Hook principal para capturar envíos de formularios WPR (Royal Addons)
add_action( 'wpr_form_submit', 'procesar_formulario_make_integration_wpr', 10, 2 );

// Hook alternativo para Elementor Pro Forms
add_action( 'elementor_pro/forms/new_record', 'procesar_formulario_make_integration', 10, 2 );

// Hook adicional para capturar envío via AJAX de WPR
add_action( 'wp_ajax_wpr_form_builder_submit', 'capturar_wpr_form_ajax', 1 );
add_action( 'wp_ajax_nopriv_wpr_form_builder_submit', 'capturar_wpr_form_ajax', 1 );

function capturar_wpr_form_ajax() {
    error_log( '===== CAPTURA AJAX WPR FORM =====' );
    error_log( 'POST Data: ' . print_r($_POST, true) );
    
    // Buscar el ID del div contenedor en los datos del referrer o POST
    if ( isset($_POST['form_id']) ) {
        error_log( 'Form ID desde POST: ' . $_POST['form_id'] );
    }
    
    // No interferir con el proceso normal, solo log
}

function procesar_formulario_make_integration_wpr( $form_data, $form_id ) {
    error_log( '===== WPR FORM SUBMIT DETECTADO =====' );
    error_log( 'Form ID: ' . $form_id );
    error_log( 'Form Data: ' . print_r($form_data, true) );
    
    procesar_formulario_generico( $form_data, $form_id, 'wpr' );
}

function procesar_formulario_make_integration( $record, $handler ) {
    
    // Obtener el ID del formulario - múltiples métodos
    $form_id = $record->get_form_settings( 'id' );
    
    // Si no encontramos ID, intentar otros métodos
    if ( empty($form_id) ) {
        // Intentar obtener del widget ID
        $widget_id = $record->get_form_settings( 'widget_id' );
        error_log( 'Widget ID: ' . $widget_id );
        
        // Intentar obtener de la configuración del formulario
        $form_settings = $record->get_form_settings();
        error_log( 'Todas las configuraciones: ' . print_r($form_settings, true) );
    }
    
    // Logging inicial
    error_log( '===== INICIO PROCESAMIENTO FORMULARIO ELEMENTOR =====' );
    error_log( 'Form ID recibido: ' . $form_id );
    error_log( 'Timestamp: ' . date('Y-m-d H:i:s') );
    
    // Obtener campos
    $raw_fields = $record->get( 'fields' );
    
    procesar_formulario_generico( $raw_fields, $form_id, 'elementor' );
}

function procesar_formulario_generico( $form_data, $form_id, $source = 'unknown' ) {
    
    error_log( '===== PROCESAMIENTO GENÉRICO =====' );
    error_log( 'Source: ' . $source );
    error_log( 'Form ID: ' . $form_id );
    
    // Configuración de webhooks por formulario
    // Ahora también buscamos por widget ID o div ID
    $webhook_config = [
        // IDs de div contenedor
        'make-integration-propietarios' => 'https://hook.eu2.make.com/ka72ct9l6ojhoxoip26phqgubyu7aeoo',
        'make-integration-inmobiliaria' => 'https://hook.eu2.make.com/7lia5xpdbvjtmwi92yna4kl1bvqtst2o',
        // IDs antiguos por si acaso
        'form-propietarios-make-integration' => 'https://hook.eu2.make.com/ka72ct9l6ojhoxoip26phqgubyu7aeoo',
        'form-inmobiliaria-make-integration' => 'https://hook.eu2.make.com/7lia5xpdbvjtmwi92yna4kl1bvqtst2o',
        // Widget IDs (los data-id del div)
        '23aef76' => 'https://hook.eu2.make.com/7lia5xpdbvjtmwi92yna4kl1bvqtst2o', // inmobiliaria
    ];
    
    // Verificar si el formulario está en nuestra configuración
    $webhook_url = null;
    foreach ( $webhook_config as $config_id => $url ) {
        if ( $form_id === $config_id || strpos($form_id, $config_id) !== false ) {
            $webhook_url = $url;
            error_log( 'Formulario reconocido con ID: ' . $config_id );
            break;
        }
    }
    
    if ( ! $webhook_url ) {
        error_log( 'Formulario no configurado para integración Make: ' . $form_id );
        error_log( 'IDs configurados: ' . print_r(array_keys($webhook_config), true) );
        error_log( '===== FIN PROCESAMIENTO (NO CONFIGURADO) =====' );
        return;
    }
    
    error_log( 'Formulario reconocido, procesando...' );
    error_log( 'Webhook URL: ' . $webhook_url );
    
    // Preparar los datos para enviar
    $data = [];
    
    // Mapear todos los campos disponibles con logging detallado
    error_log( '--- Inicio mapeo de campos ---' );
    
    // Manejar diferentes formatos de datos según el source
    if ( isset($form_data) && is_array($form_data) ) {
        foreach ( $form_data as $field_id => $field ) {
            if ( is_array($field) && isset($field['value']) ) {
                $data[ $field_id ] = $field['value'];
                error_log( sprintf(
                    'Campo: %s | Valor: %s | Tipo: %s',
                    $field_id,
                    $field['value'],
                    $field['type'] ?? 'no especificado'
                ));
            } else {
                $data[ $field_id ] = $field;
                error_log( sprintf('Campo: %s | Valor: %s', $field_id, $field) );
            }
        }
    } elseif ( isset($_POST['form_fields']) ) {
        // Para WPR Forms que envían datos via POST
        error_log( 'Detectando campos desde POST (WPR Forms)' );
        foreach ( $_POST['form_fields'] as $field_name => $field_value ) {
            $data[ $field_name ] = $field_value;
            error_log( sprintf('Campo POST: %s | Valor: %s', $field_name, $field_value) );
        }
    }
    error_log( '--- Fin mapeo de campos ---' );
    
    // Agregar metadatos adicionales
    $data['form_id'] = $form_id;
    $data['fecha_envio'] = date('Y-m-d H:i:s');
    $data['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'IP no disponible';
    $data['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'User Agent no disponible';
    $data['referer'] = $_SERVER['HTTP_REFERER'] ?? 'Referer no disponible';
    
    // Validación de campos requeridos
    $campos_requeridos = validar_campos_requeridos( $form_id, $data );
    
    if ( ! $campos_requeridos['valido'] ) {
        error_log( '❌ ERROR DE VALIDACIÓN: ' . implode(', ', $campos_requeridos['errores'] ) );
        // Opcionalmente, puedes decidir no enviar el formulario si falta información crítica
        // return;
    } else {
        error_log( '✅ Validación exitosa: todos los campos requeridos presentes' );
    }
    
    // Log del payload completo que se enviará
    error_log( 'Payload JSON a enviar:' );
    error_log( json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
    
    // Enviar datos al webhook de Make
    error_log( 'Iniciando envío a Make...' );
    $response = wp_remote_post( $webhook_url, [
        'body' => json_encode( $data ),
        'headers' => [
            'Content-Type' => 'application/json',
        ],
        'timeout' => 30,
    ]);
    
    // Procesar respuesta
    if ( is_wp_error( $response ) ) {
        error_log( '❌ ERROR al enviar a Make: ' . $response->get_error_message() );
        error_log( 'Código de error: ' . $response->get_error_code() );
    } else {
        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        
        error_log( 'Respuesta de Make:' );
        error_log( 'Código HTTP: ' . $response_code );
        error_log( 'Body: ' . $response_body );
        
        if ( $response_code === 200 || $response_code === 204 ) {
            error_log( '✅ Formulario enviado exitosamente a Make' );
        } else {
            error_log( '⚠️ Make respondió con código inesperado: ' . $response_code );
        }
    }
    
    error_log( '===== FIN PROCESAMIENTO FORMULARIO =====' );
    error_log( '----------------------------------------' );
}

/**
 * Función para validar campos requeridos según el tipo de formulario
 */
function validar_campos_requeridos( $form_id, $fields_data ) {
    $resultado = [
        'valido' => true,
        'errores' => []
    ];
    
    // Definir campos requeridos - simplificado para cualquier formulario
    $campos_requeridos = ['name', 'email'];
    
    error_log( 'Validando campos requeridos: ' . implode(', ', $campos_requeridos ) );
    error_log( 'Campos disponibles: ' . print_r(array_keys($fields_data), true) );
    
    foreach ( $campos_requeridos as $campo_requerido ) {
        $campo_encontrado = false;
        $valor_campo = '';
        
        // Buscar el campo con diferentes posibles IDs
        $posibles_ids = [
            $campo_requerido,
            'field_' . $campo_requerido,
            'form_fields[' . $campo_requerido . ']',
        ];
        
        // También buscar directamente en los datos
        if ( isset( $fields_data[ $campo_requerido ] ) ) {
            $campo_encontrado = true;
            $valor_campo = $fields_data[ $campo_requerido ];
        }
        
        if ( ! $campo_encontrado ) {
            $resultado['valido'] = false;
            $resultado['errores'][] = "Campo requerido no encontrado: $campo_requerido";
            error_log( "⚠️ Campo requerido no encontrado: $campo_requerido" );
        } elseif ( empty( $valor_campo ) ) {
            $resultado['valido'] = false;
            $resultado['errores'][] = "Campo requerido vacío: $campo_requerido";
            error_log( "⚠️ Campo requerido vacío: $campo_requerido" );
        } else {
            error_log( "✓ Campo '$campo_requerido' presente con valor: $valor_campo" );
        }
    }
    
    // Validaciones adicionales específicas
    if ( isset( $fields_data['email'] ) && ! empty( $fields_data['email'] ) ) {
        if ( ! filter_var( $fields_data['email'], FILTER_VALIDATE_EMAIL ) ) {
            $resultado['valido'] = false;
            $resultado['errores'][] = "Email no válido: " . $fields_data['email'];
            error_log( "⚠️ Email no válido: " . $fields_data['email'] );
        }
    }
    
    return $resultado;
}

/**
 * Función auxiliar para debugging detallado (activar solo cuando sea necesario)
 */
function debug_formulario_detallado( $record ) {
    error_log( '===== DEBUG DETALLADO DEL FORMULARIO =====' );
    
    // Información del formulario
    $form_settings = $record->get_form_settings();
    error_log( 'Configuración completa del formulario:' );
    error_log( print_r( $form_settings, true ) );
    
    // Campos con toda su información
    $fields = $record->get( 'fields' );
    error_log( 'Información completa de campos:' );
    foreach ( $fields as $field_id => $field_data ) {
        error_log( "Campo ID: $field_id" );
        error_log( print_r( $field_data, true ) );
    }
    
    // Meta información
    $meta = $record->get( 'meta' );
    error_log( 'Meta información:' );
    error_log( print_r( $meta, true ) );
    
    error_log( '===== FIN DEBUG DETALLADO =====' );
}

// Hook opcional para debugging extremo (descomentar si es necesario)
// add_action( 'elementor_pro/forms/new_record', function( $record ) {
//     $form_id = $record->get_form_settings( 'id' );
//     if ( in_array( $form_id, ['form-propietarios-make-integration', 'form-inmobiliaria-make-integration'] ) ) {
//         debug_formulario_detallado( $record );
//     }
// }, 5 );

/**
 * Función para limpiar logs antiguos (opcional)
 */
function limpiar_logs_antiguos() {
    $log_file = WP_CONTENT_DIR . '/debug.log';
    if ( file_exists( $log_file ) ) {
        $size = filesize( $log_file );
        // Si el archivo es mayor a 10MB, limpiarlo
        if ( $size > 10485760 ) {
            file_put_contents( $log_file, '' );
            error_log( 'Log limpiado automáticamente - ' . date('Y-m-d H:i:s') );
        }
    }
}

// Ejecutar limpieza de logs diariamente
add_action( 'wp_scheduled_delete', 'limpiar_logs_antiguos' );