<?php
/**
 * Plugin Name:       Make Integration for Forms
 * Plugin URI:        https://github.com/LucumaAgency/Elementor-Forms-Make-Integration
 * Description:       Integración de formularios Elementor y WPR Forms con webhooks de Make (Integromat). Captura y envía datos de formularios a Make con logging detallado.
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.2
 * Author:            Lucuma Agency
 * Author URI:        https://lucuma.agency
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       make-integration
 * Domain Path:       /languages
 */

// Evitar acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Definir constantes del plugin
define( 'MAKE_INTEGRATION_VERSION', '1.0.0' );
define( 'MAKE_INTEGRATION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MAKE_INTEGRATION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MAKE_INTEGRATION_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Cargar archivos del plugin solo si no existen ya las funciones
if ( ! function_exists( 'procesar_formulario_make_integration' ) ) {
    require_once MAKE_INTEGRATION_PLUGIN_DIR . 'includes/functions.php';
}

if ( ! function_exists( 'procesar_wpr_form_submission' ) ) {
    require_once MAKE_INTEGRATION_PLUGIN_DIR . 'includes/wpr-forms-integration.php';
}

// Clase principal del plugin
class Make_Integration {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Hooks de activación y desactivación
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
        
        // Hooks de administración
        add_action( 'admin_notices', [ $this, 'admin_notices' ] );
        add_action( 'admin_bar_menu', [ $this, 'admin_bar_menu' ], 100 );
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        
        // Agregar enlaces en la página de plugins
        add_filter( 'plugin_action_links_' . MAKE_INTEGRATION_PLUGIN_BASENAME, [ $this, 'plugin_action_links' ] );
    }
    
    /**
     * Activación del plugin
     */
    public function activate() {
        // Crear opciones por defecto si es necesario
        if ( ! get_option( 'make_integration_settings' ) ) {
            $default_settings = [
                'debug_mode' => true,
                'log_retention_days' => 7,
                'webhooks' => [
                    'propietarios' => 'https://hook.eu2.make.com/ka72ct9l6ojhoxoip26phqgubyu7aeoo',
                    'inmobiliaria' => 'https://hook.eu2.make.com/7lia5xpdbvjtmwi92yna4kl1bvqtst2o'
                ]
            ];
            update_option( 'make_integration_settings', $default_settings );
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Desactivación del plugin
     */
    public function deactivate() {
        // Limpiar scheduled hooks si hay alguno
        flush_rewrite_rules();
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Verificar tamaño del log
        $log_file = WP_CONTENT_DIR . '/debug.log';
        if ( file_exists( $log_file ) ) {
            $size = filesize( $log_file );
            if ( $size > 10485760 ) { // 10MB
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p>
                        <strong>Make Integration:</strong> 
                        El archivo de debug.log supera los 10MB. 
                        <a href="<?php echo admin_url( 'admin.php?page=make-integration-logs&clear_logs=1&_wpnonce=' . wp_create_nonce( 'clear_logs' ) ); ?>">
                            Limpiar ahora
                        </a>
                    </p>
                </div>
                <?php
            }
        }
        
        // Verificar si WP_DEBUG está activo
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <strong>Make Integration:</strong> 
                    Para ver los logs de debugging, activa WP_DEBUG en wp-config.php
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Admin bar menu
     */
    public function admin_bar_menu( $wp_admin_bar ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $args = [
            'id'    => 'make-integration',
            'title' => '<span class="ab-icon dashicons dashicons-admin-plugins"></span> Make Integration',
            'href'  => admin_url( 'admin.php?page=make-integration-logs' ),
            'meta'  => [
                'title' => 'Make Integration Status',
            ],
        ];
        $wp_admin_bar->add_menu( $args );
        
        // Submenu - Ver logs
        $wp_admin_bar->add_menu([
            'id'     => 'make-integration-logs',
            'parent' => 'make-integration',
            'title'  => 'Ver Logs',
            'href'   => admin_url( 'admin.php?page=make-integration-logs' ),
        ]);
        
        // Submenu - Configuración
        $wp_admin_bar->add_menu([
            'id'     => 'make-integration-settings',
            'parent' => 'make-integration',
            'title'  => 'Configuración',
            'href'   => admin_url( 'admin.php?page=make-integration-settings' ),
        ]);
    }
    
    /**
     * Admin menu
     */
    public function admin_menu() {
        // Página principal
        add_menu_page(
            'Make Integration',
            'Make Integration',
            'manage_options',
            'make-integration-logs',
            [ $this, 'render_logs_page' ],
            'dashicons-admin-plugins',
            80
        );
        
        // Submenú - Logs
        add_submenu_page(
            'make-integration-logs',
            'Logs - Make Integration',
            'Logs',
            'manage_options',
            'make-integration-logs',
            [ $this, 'render_logs_page' ]
        );
        
        // Submenú - Configuración
        add_submenu_page(
            'make-integration-logs',
            'Configuración - Make Integration',
            'Configuración',
            'manage_options',
            'make-integration-settings',
            [ $this, 'render_settings_page' ]
        );
    }
    
    /**
     * Plugin action links
     */
    public function plugin_action_links( $links ) {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=make-integration-settings' ) . '">Configuración</a>';
        $logs_link = '<a href="' . admin_url( 'admin.php?page=make-integration-logs' ) . '">Ver Logs</a>';
        
        array_unshift( $links, $logs_link );
        array_unshift( $links, $settings_link );
        
        return $links;
    }
    
    /**
     * Render logs page
     */
    public function render_logs_page() {
        // Manejar limpieza de logs
        if ( isset( $_GET['clear_logs'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'clear_logs' ) ) {
            $log_file = WP_CONTENT_DIR . '/debug.log';
            if ( file_exists( $log_file ) ) {
                file_put_contents( $log_file, '' );
                echo '<div class="notice notice-success"><p>Logs limpiados exitosamente.</p></div>';
            }
        }
        ?>
        <div class="wrap">
            <h1>Make Integration - Logs</h1>
            
            <?php
            $log_file = WP_CONTENT_DIR . '/debug.log';
            if ( file_exists( $log_file ) ) {
                $logs = file_get_contents( $log_file );
                
                // Filtrar solo logs de Make Integration
                $lines = explode( "\n", $logs );
                $make_logs = array_filter( $lines, function( $line ) {
                    return strpos( $line, 'FORMULARIO' ) !== false || 
                           strpos( $line, 'Make' ) !== false ||
                           strpos( $line, 'WPR' ) !== false ||
                           strpos( $line, 'webhook' ) !== false ||
                           strpos( $line, 'Campo' ) !== false;
                });
                
                // Obtener los últimos 200 logs
                $make_logs = array_slice( $make_logs, -200 );
                ?>
                
                <div class="card">
                    <h2>Últimos eventos (<?php echo count( $make_logs ); ?> de 200)</h2>
                    <div style="background: #23282d; color: #fff; padding: 15px; border-radius: 3px; overflow: auto; max-height: 500px; font-family: 'Courier New', monospace; font-size: 12px;">
                        <?php
                        foreach ( $make_logs as $log ) {
                            $log = esc_html( $log );
                            
                            // Aplicar colores según el tipo de log
                            if ( strpos( $log, '✅' ) !== false || strpos( $log, 'exitosamente' ) !== false ) {
                                echo '<div style="color: #46b450;">' . $log . '</div>';
                            } elseif ( strpos( $log, '❌' ) !== false || strpos( $log, 'ERROR' ) !== false ) {
                                echo '<div style="color: #dc3232;">' . $log . '</div>';
                            } elseif ( strpos( $log, '⚠️' ) !== false || strpos( $log, 'WARNING' ) !== false ) {
                                echo '<div style="color: #ffb900;">' . $log . '</div>';
                            } elseif ( strpos( $log, '=====' ) !== false ) {
                                echo '<div style="color: #00a0d2; font-weight: bold;">' . $log . '</div>';
                            } else {
                                echo '<div>' . $log . '</div>';
                            }
                        }
                        ?>
                    </div>
                    
                    <p style="margin-top: 20px;">
                        <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=make-integration-logs&clear_logs=1' ), 'clear_logs' ); ?>" 
                           class="button button-secondary"
                           onclick="return confirm('¿Estás seguro de que quieres limpiar todos los logs?');">
                            Limpiar Logs
                        </a>
                        <a href="<?php echo admin_url( 'admin.php?page=make-integration-logs' ); ?>" 
                           class="button button-primary">
                            Actualizar
                        </a>
                    </p>
                </div>
                <?php
            } else {
                ?>
                <div class="notice notice-info">
                    <p>No se encontró el archivo de logs. Asegúrate de que WP_DEBUG_LOG esté activo.</p>
                </div>
                <?php
            }
            ?>
            
            <div class="card" style="margin-top: 20px;">
                <h2>Estado del Sistema</h2>
                <table class="form-table">
                    <tr>
                        <th>WP_DEBUG</th>
                        <td><?php echo defined( 'WP_DEBUG' ) && WP_DEBUG ? '<span style="color: green;">✓ Activo</span>' : '<span style="color: red;">✗ Inactivo</span>'; ?></td>
                    </tr>
                    <tr>
                        <th>WP_DEBUG_LOG</th>
                        <td><?php echo defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ? '<span style="color: green;">✓ Activo</span>' : '<span style="color: red;">✗ Inactivo</span>'; ?></td>
                    </tr>
                    <tr>
                        <th>Archivo de Logs</th>
                        <td><?php echo file_exists( WP_CONTENT_DIR . '/debug.log' ) ? '<span style="color: green;">✓ Existe</span>' : '<span style="color: red;">✗ No existe</span>'; ?></td>
                    </tr>
                    <tr>
                        <th>Versión del Plugin</th>
                        <td><?php echo MAKE_INTEGRATION_VERSION; ?></td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Make Integration - Configuración</h1>
            
            <div class="card">
                <h2>Webhooks Configurados</h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Formulario</th>
                            <th>IDs Soportados</th>
                            <th>Webhook URL</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Propietarios</strong></td>
                            <td>
                                <code>make-integration-propietarios</code><br>
                                <code>form-propietarios-make-integration</code>
                            </td>
                            <td><code style="font-size: 11px;">https://hook.eu2.make.com/ka72ct9l6ojhoxoip26phqgubyu7aeoo</code></td>
                            <td><span style="color: green;">✓ Activo</span></td>
                        </tr>
                        <tr class="alternate">
                            <td><strong>Inmobiliaria</strong></td>
                            <td>
                                <code>make-integration-inmobiliaria</code><br>
                                <code>form-inmobiliaria-make-integration</code><br>
                                <code>23aef76</code> (Widget ID)
                            </td>
                            <td><code style="font-size: 11px;">https://hook.eu2.make.com/7lia5xpdbvjtmwi92yna4kl1bvqtst2o</code></td>
                            <td><span style="color: green;">✓ Activo</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2>Instrucciones de Configuración</h2>
                
                <h3>Para formularios WPR (Royal Addons):</h3>
                <ol>
                    <li>Edita el formulario en Elementor</li>
                    <li>En el div contenedor del widget, agrega el ID correspondiente:
                        <ul>
                            <li>Para Propietarios: <code>id="make-integration-propietarios"</code></li>
                            <li>Para Inmobiliaria: <code>id="make-integration-inmobiliaria"</code></li>
                        </ul>
                    </li>
                    <li>Guarda los cambios</li>
                </ol>
                
                <h3>Para formularios Elementor Pro:</h3>
                <ol>
                    <li>Edita el formulario en Elementor</li>
                    <li>En la configuración del formulario, establece el Form ID:
                        <ul>
                            <li>Para Propietarios: <code>form-propietarios-make-integration</code></li>
                            <li>Para Inmobiliaria: <code>form-inmobiliaria-make-integration</code></li>
                        </ul>
                    </li>
                    <li>Guarda los cambios</li>
                </ol>
                
                <h3>Verificar la integración:</h3>
                <ol>
                    <li>Envía un formulario de prueba</li>
                    <li>Revisa los <a href="<?php echo admin_url( 'admin.php?page=make-integration-logs' ); ?>">logs</a> para confirmar el envío</li>
                    <li>Verifica en Make que el webhook recibió los datos</li>
                </ol>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2>Datos Enviados a Make</h2>
                <p>Cada formulario envía los siguientes datos:</p>
                <ul>
                    <li>✓ Todos los campos del formulario (nombre, email, teléfono, etc.)</li>
                    <li>✓ <code>form_id</code> - ID del formulario</li>
                    <li>✓ <code>timestamp</code> - Fecha y hora de envío</li>
                    <li>✓ <code>ip</code> - Dirección IP del usuario</li>
                    <li>✓ <code>user_agent</code> - Navegador del usuario</li>
                    <li>✓ <code>page_id</code> - ID de la página donde está el formulario</li>
                    <li>✓ <code>page_title</code> - Título de la página</li>
                </ul>
            </div>
        </div>
        <?php
    }
}

// Inicializar el plugin
add_action( 'plugins_loaded', function() {
    Make_Integration::get_instance();
});

// Agregar estilos inline para el icono del admin bar
add_action( 'admin_head', function() {
    ?>
    <style>
        #wp-admin-bar-make-integration .ab-icon:before {
            content: "\f106";
            top: 2px;
        }
    </style>
    <?php
});

add_action( 'wp_head', function() {
    if ( is_admin_bar_showing() ) {
        ?>
        <style>
            #wp-admin-bar-make-integration .ab-icon:before {
                content: "\f106";
                font-family: dashicons;
                top: 2px;
            }
        </style>
        <?php
    }
});