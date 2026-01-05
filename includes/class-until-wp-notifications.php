<?php
/**
 * Classe per gestionar les notificacions d'administració
 *
 * @package Until_WP
 */

// Si aquest fitxer s'accedeix directament, avortar
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Until_WP_Notifications {
	
	/**
	 * Instància de la classe de base de dades
	 */
	private $database;
	
	/**
	 * Constructor
	 *
	 * @param Until_WP_Database $database Instància de la base de dades
	 */
	public function __construct( $database ) {
		$this->database = $database;
		$this->init_hooks();
	}
	
	/**
	 * Inicialitzar els hooks
	 */
	private function init_hooks() {
		// Mostrar les notificacions d'administració
		add_action( 'admin_notices', array( $this, 'display_notifications' ) );
		
		// AJAX per descartar notificacions
		add_action( 'wp_ajax_until_wp_dismiss_notification', array( $this, 'ajax_dismiss_notification' ) );
		add_action( 'wp_ajax_until_wp_dismiss_all_notifications', array( $this, 'ajax_dismiss_all_notifications' ) );
		
		// Afegir badge al menú
		add_action( 'admin_menu', array( $this, 'add_menu_badge' ), 999 );
	}
	
	/**
	 * Mostrar les notificacions d'administració
	 */
	public function display_notifications() {
		// Verificar permisos
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		
		// Obtenir les notificacions
		$notifications = $this->get_active_notifications();
		
		if ( empty( $notifications ) ) {
			return;
		}
		
		// Limitar a les últimes 5 notificacions
		$notifications = array_slice( $notifications, 0, 5 );
		
		foreach ( $notifications as $notification ) {
			$this->display_notification( $notification );
		}
		
		// Si hi ha més de 5 notificacions, mostrar un resum
		$total_notifications = count( $this->get_active_notifications() );
		if ( $total_notifications > 5 ) {
			$this->display_summary_notification( $total_notifications );
		}
	}
	
	/**
	 * Mostrar una notificació individual
	 *
	 * @param array $notification Dades de la notificació
	 */
	private function display_notification( $notification ) {
		$post = get_post( $notification['post_id'] );
		$post_title = $post ? $post->post_title : __( 'Post eliminat', 'until-wp' );
		
		// Generar el missatge
		$message = $this->get_notification_message( $notification, $post_title );
		
		?>
		<div class="notice notice-success is-dismissible until-wp-notification" data-notification-id="<?php echo esc_attr( $notification['id'] ); ?>">
			<p>
				<strong><?php _e( 'Until WP:', 'until-wp' ); ?></strong>
				<?php echo $message; ?>
				<?php if ( $post ) : ?>
					<a href="<?php echo esc_url( get_edit_post_link( $post->ID ) ); ?>" class="button button-small">
						<?php _e( 'Veure post', 'until-wp' ); ?>
					</a>
				<?php endif; ?>
			</p>
		</div>
		<script>
		jQuery(document).ready(function($) {
			$('.until-wp-notification[data-notification-id="<?php echo esc_js( $notification['id'] ); ?>"]').on('click', '.notice-dismiss', function() {
				$.post(ajaxurl, {
					action: 'until_wp_dismiss_notification',
					notification_id: '<?php echo esc_js( $notification['id'] ); ?>'
				});
			});
		});
		</script>
		<?php
	}
	
	/**
	 * Mostrar una notificació de resum
	 *
	 * @param int $total_notifications Total de notificacions
	 */
	private function display_summary_notification( $total_notifications ) {
		$remaining = $total_notifications - 5;
		
		?>
		<div class="notice notice-info is-dismissible until-wp-notification-summary">
			<p>
				<strong><?php _e( 'Until WP:', 'until-wp' ); ?></strong>
				<?php
				printf(
					/* translators: %d: Number of additional notifications */
					_n(
						'Hi ha %d notificació més.',
						'Hi ha %d notificacions més.',
						$remaining,
						'until-wp'
					),
					$remaining
				);
				?>
				<a href="<?php echo esc_url( admin_url( 'tools.php?page=until-wp-scheduled-changes&tab=history' ) ); ?>" class="button button-small">
					<?php _e( 'Veure historial', 'until-wp' ); ?>
				</a>
				<button type="button" class="button button-small until-wp-dismiss-all">
					<?php _e( 'Descartar tot', 'until-wp' ); ?>
				</button>
			</p>
		</div>
		<script>
		jQuery(document).ready(function($) {
			$('.until-wp-dismiss-all').on('click', function(e) {
				e.preventDefault();
				$.post(ajaxurl, {
					action: 'until_wp_dismiss_all_notifications'
				}, function() {
					$('.until-wp-notification, .until-wp-notification-summary').fadeOut();
				});
			});
			
			$('.until-wp-notification-summary').on('click', '.notice-dismiss', function() {
				$.post(ajaxurl, {
					action: 'until_wp_dismiss_all_notifications'
				});
			});
		});
		</script>
		<?php
	}
	
	/**
	 * Generar el missatge de notificació
	 *
	 * @param array $notification Dades de la notificació
	 * @param string $post_title Títol del post
	 * @return string Missatge HTML
	 */
	private function get_notification_message( $notification, $post_title ) {
		$change_type = $notification['change_type'];
		$old_value = $notification['old_value'];
		$new_value = $notification['new_value'];
		
		if ( $change_type === 'post_status' ) {
			$statuses = get_post_statuses();
			$old_status = isset( $statuses[ $old_value ] ) ? $statuses[ $old_value ] : $old_value;
			$new_status = isset( $statuses[ $new_value ] ) ? $statuses[ $new_value ] : $new_value;
			
			return sprintf(
				/* translators: 1: Post title, 2: Old status, 3: New status */
				__( 'S\'ha canviat l\'estat de "%1$s" de %2$s a %3$s.', 'until-wp' ),
				'<strong>' . esc_html( $post_title ) . '</strong>',
				'<em>' . esc_html( $old_status ) . '</em>',
				'<em>' . esc_html( $new_status ) . '</em>'
			);
		} elseif ( $change_type === 'sticky' ) {
			if ( $new_value === 'yes' ) {
				return sprintf(
					/* translators: %s: Post title */
					__( 'S\'ha fixat l\'entrada "%s".', 'until-wp' ),
					'<strong>' . esc_html( $post_title ) . '</strong>'
				);
			} else {
				return sprintf(
					/* translators: %s: Post title */
					__( 'S\'ha desfixat l\'entrada "%s".', 'until-wp' ),
					'<strong>' . esc_html( $post_title ) . '</strong>'
				);
			}
		}
		
		return __( 'S\'ha executat un canvi programat.', 'until-wp' );
	}
	
	/**
	 * Obtenir les notificacions actives
	 *
	 * @return array Array de notificacions
	 */
	private function get_active_notifications() {
		$notifications = get_option( 'until_wp_notifications', array() );
		
		// Filtrar només les no descartades
		return array_filter( $notifications, function( $notification ) {
			return ! $notification['dismissed'];
		} );
	}
	
	/**
	 * AJAX: Descartar una notificació
	 */
	public function ajax_dismiss_notification() {
		// Obtenir ID de la notificació
		$notification_id = isset( $_POST['notification_id'] ) ? sanitize_text_field( $_POST['notification_id'] ) : '';
		
		if ( ! $notification_id ) {
			wp_send_json_error();
		}
		
		// Obtenir totes les notificacions
		$notifications = get_option( 'until_wp_notifications', array() );
		
		// Marcar com a descartada
		foreach ( $notifications as &$notification ) {
			if ( $notification['id'] === $notification_id ) {
				$notification['dismissed'] = true;
				break;
			}
		}
		
		// Guardar
		update_option( 'until_wp_notifications', $notifications );
		
		wp_send_json_success();
	}
	
	/**
	 * AJAX: Descartar totes les notificacions
	 */
	public function ajax_dismiss_all_notifications() {
		// Obtenir totes les notificacions
		$notifications = get_option( 'until_wp_notifications', array() );
		
		// Marcar totes com a descartades
		foreach ( $notifications as &$notification ) {
			$notification['dismissed'] = true;
		}
		
		// Guardar
		update_option( 'until_wp_notifications', $notifications );
		
		wp_send_json_success();
	}
	
	/**
	 * Afegir badge al menú amb el comptador de notificacions
	 */
	public function add_menu_badge() {
		global $menu;
		
		// Comptar les notificacions actives
		$count = count( $this->get_active_notifications() );
		
		if ( $count === 0 ) {
			return;
		}
		
		// Buscar el menú d'Eines
		foreach ( $menu as $key => $item ) {
			if ( $item[2] === 'tools.php' ) {
				// Afegir el badge al títol
				$menu[ $key ][0] .= sprintf(
					' <span class="awaiting-mod count-%d"><span class="pending-count">%d</span></span>',
					$count,
					$count
				);
				break;
			}
		}
	}
	
	/**
	 * Netejar les notificacions antigues (més de 30 dies)
	 */
	public function cleanup_old_notifications() {
		$notifications = get_option( 'until_wp_notifications', array() );
		
		$thirty_days_ago = strtotime( '-30 days' );
		
		// Filtrar les notificacions
		$notifications = array_filter( $notifications, function( $notification ) use ( $thirty_days_ago ) {
			$executed_time = strtotime( $notification['executed_time'] );
			return $executed_time > $thirty_days_ago;
		} );
		
		// Guardar
		update_option( 'until_wp_notifications', array_values( $notifications ) );
	}
}

