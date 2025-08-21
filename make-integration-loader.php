<?php
/**
 * Plugin Name: Make Integration for Forms
 * Description: Integración de formularios Elementor y WPR Forms con webhooks de Make
 * Version: 1.0
 * Author: Tu nombre
 */

// Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Cargar la integración principal
require_once plugin_dir_path( __FILE__ ) . 'functions.php';

// Cargar la integración específica de WPR Forms
require_once plugin_dir_path( __FILE__ ) . 'wpr-forms-integration.php';

// Activar modo debug si es necesario
if ( ! defined( 'WP_DEBUG' ) ) {
    define( 'WP_DEBUG', true );
}

if ( ! defined( 'WP_DEBUG_LOG' ) ) {
    define( 'WP_DEBUG_LOG', true );
}

// Función para verificar el estado de la integración
add_action( 'admin_notices', 'make_integration_admin_notice' );

function make_integration_admin_notice() {
    if ( current_user_can( 'manage_options' ) ) {
        $log_file = WP_CONTENT_DIR . '/debug.log';
        if ( file_exists( $log_file ) ) {
            $size = filesize( $log_file );
            if ( $size > 5242880 ) { // 5MB
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p><strong>Make Integration:</strong> El archivo de log supera los 5MB. Considera limpiarlo.</p>';
                echo '</div>';
            }
        }
    }
}

// Agregar enlace al log en el menú de herramientas
add_action( 'admin_bar_menu', 'make_integration_admin_bar_menu', 100 );

function make_integration_admin_bar_menu( $wp_admin_bar ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    $args = array(
        'id'    => 'make-integration',
        'title' => '🔌 Make Integration',
        'href'  => '#',
        'meta'  => array(
            'title' => 'Estado de Make Integration',
        ),
    );
    $wp_admin_bar->add_menu( $args );
    
    // Sub-item para ver logs
    $args = array(
        'id'     => 'make-integration-logs',
        'parent' => 'make-integration',
        'title'  => 'Ver Logs',
        'href'   => admin_url( 'admin.php?page=make-integration-logs' ),
    );
    $wp_admin_bar->add_menu( $args );
}

// Página de administración para ver logs
add_action( 'admin_menu', 'make_integration_admin_menu' );

function make_integration_admin_menu() {
    add_submenu_page(
        null, // Página oculta
        'Make Integration Logs',
        'Make Integration Logs',
        'manage_options',
        'make-integration-logs',
        'make_integration_logs_page'
    );
}

function make_integration_logs_page() {
    ?>
    <div class="wrap">
        <h1>Make Integration - Logs</h1>
        <?php
        $log_file = WP_CONTENT_DIR . '/debug.log';
        if ( file_exists( $log_file ) ) {
            $logs = file_get_contents( $log_file );
            
            // Filtrar solo logs de Make Integration
            $lines = explode("\n", $logs);
            $make_logs = array_filter($lines, function($line) {
                return strpos($line, 'FORMULARIO') !== false || 
                       strpos($line, 'Make') !== false ||
                       strpos($line, 'WPR') !== false ||
                       strpos($line, 'webhook') !== false;
            });
            
            // Mostrar los últimos 100 logs
            $make_logs = array_slice($make_logs, -100);
            
            echo '<h2>Últimos 100 eventos de Make Integration</h2>';
            echo '<pre style="background: #f0f0f0; padding: 10px; overflow: auto; max-height: 500px;">';
            foreach ( $make_logs as $log ) {
                if ( strpos($log, '✅') !== false ) {
                    echo '<span style="color: green;">' . esc_html($log) . '</span>' . "\n";
                } elseif ( strpos($log, '❌') !== false ) {
                    echo '<span style="color: red;">' . esc_html($log) . '</span>' . "\n";
                } elseif ( strpos($log, '⚠️') !== false ) {
                    echo '<span style="color: orange;">' . esc_html($log) . '</span>' . "\n";
                } else {
                    echo esc_html($log) . "\n";
                }
            }
            echo '</pre>';
            
            // Botón para limpiar logs
            if ( isset($_POST['clear_logs']) ) {
                file_put_contents( $log_file, '' );
                echo '<div class="notice notice-success"><p>Logs limpiados exitosamente.</p></div>';
            }
            ?>
            <form method="post" style="margin-top: 20px;">
                <input type="submit" name="clear_logs" class="button button-secondary" value="Limpiar Logs" onclick="return confirm('¿Estás seguro de que quieres limpiar todos los logs?');">
            </form>
            <?php
        } else {
            echo '<p>No se encontró el archivo de logs.</p>';
        }
        ?>
        
        <h2>Configuración Actual</h2>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Formulario</th>
                    <th>ID/Selector</th>
                    <th>Webhook URL</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Propietarios</td>
                    <td>make-integration-propietarios</td>
                    <td><code>https://hook.eu2.make.com/ka72ct9l6ojhoxoip26phqgubyu7aeoo</code></td>
                </tr>
                <tr>
                    <td>Inmobiliaria</td>
                    <td>make-integration-inmobiliaria / 23aef76</td>
                    <td><code>https://hook.eu2.make.com/7lia5xpdbvjtmwi92yna4kl1bvqtst2o</code></td>
                </tr>
            </tbody>
        </table>
        
        <h2>Instrucciones de Uso</h2>
        <ol>
            <li>Asegúrate de que los IDs de los formularios coincidan con los configurados arriba</li>
            <li>Para formularios WPR, usa el ID del div contenedor: <code>id="make-integration-propietarios"</code></li>
            <li>Para formularios Elementor Pro, usa el ID del formulario en la configuración</li>
            <li>Los logs se guardan en <code>/wp-content/debug.log</code></li>
            <li>Verifica en Make que los webhooks estén activos y recibiendo datos</li>
        </ol>
    </div>
    <?php
}