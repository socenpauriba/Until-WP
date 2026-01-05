<?php
/**
 * Classe per gestionar la pàgina d'administració
 *
 * @package Until_WP
 */

// Si aquest fitxer s'accedeix directament, avortar
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Until_WP_Admin {
	
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
		// Afegir la pàgina al menú
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		
		// Processar accions en massa
		add_action( 'admin_init', array( $this, 'process_bulk_actions' ) );
		add_action( 'admin_init', array( $this, 'process_admin_actions' ) );
		
		// AJAX per cancel·lar canvis
		add_action( 'wp_ajax_until_wp_admin_cancel_change', array( $this, 'ajax_cancel_change' ) );
		
		// Carregar scripts i estils
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}
	
	/**
	 * Afegir la pàgina al menú d'administració
	 */
	public function add_admin_menu() {
		add_management_page(
			__( 'Canvis Programats', 'until-wp' ),
			__( 'Canvis Programats', 'until-wp' ),
			'edit_posts',
			'until-wp-scheduled-changes',
			array( $this, 'render_admin_page' )
		);
	}
	
	/**
	 * Renderitzar la pàgina d'administració
	 */
	public function render_admin_page() {
		// Verificar permisos
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'No tens permisos per accedir a aquesta pàgina.', 'until-wp' ) );
		}
		
		// Verificar si les taules existeixen
		global $wpdb;
		$scheduled_table = $wpdb->prefix . 'until_wp_scheduled';
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$scheduled_table}'" );
		
		// Obtenir la pestanya activa
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'scheduled';
		
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<?php if ( ! $table_exists && current_user_can( 'manage_options' ) ) : ?>
				<div class="notice notice-error">
					<p>
						<strong><?php _e( 'Error:', 'until-wp' ); ?></strong>
						<?php _e( 'Les taules de la base de dades no existeixen.', 'until-wp' ); ?>
						<a href="<?php echo wp_nonce_url( admin_url( 'tools.php?page=until-wp-scheduled-changes&action=recreate_tables' ), 'until_wp_recreate_tables' ); ?>" class="button button-primary">
							<?php _e( 'Crear Taules', 'until-wp' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>
			
			<!-- Tabs -->
			<h2 class="nav-tab-wrapper">
				<a href="?page=until-wp-scheduled-changes&tab=scheduled" class="nav-tab <?php echo $active_tab === 'scheduled' ? 'nav-tab-active' : ''; ?>">
					<?php _e( 'Programats', 'until-wp' ); ?>
					<?php
					$pending_count = $this->database->count_scheduled_changes( array( 'status' => 'pending' ) );
					if ( $pending_count > 0 ) {
						echo ' <span class="count">(' . $pending_count . ')</span>';
					}
					?>
				</a>
				<a href="?page=until-wp-scheduled-changes&tab=history" class="nav-tab <?php echo $active_tab === 'history' ? 'nav-tab-active' : ''; ?>">
					<?php _e( 'Historial', 'until-wp' ); ?>
				</a>
			</h2>
			
			<?php
			// Mostrar missatges
			$this->display_messages();
			
			// Mostrar el contingut de la pestanya activa
			if ( $active_tab === 'scheduled' ) {
				$this->render_scheduled_tab();
			} elseif ( $active_tab === 'history' ) {
				$this->render_history_tab();
			}
			?>
		</div>
		<?php
	}
	
	/**
	 * Renderitzar la pestanya de canvis programats
	 */
	private function render_scheduled_tab() {
		// Obtenir filtres
		$filter_post = isset( $_GET['filter_post'] ) ? absint( $_GET['filter_post'] ) : 0;
		$filter_type = isset( $_GET['filter_type'] ) ? sanitize_text_field( $_GET['filter_type'] ) : '';
		
		// Paginació
		$per_page = 20;
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$offset = ( $current_page - 1 ) * $per_page;
		
		// Preparar arguments
		$args = array(
			'status' => 'pending',
			'limit' => $per_page,
			'offset' => $offset
		);
		
		if ( $filter_post ) {
			$args['post_id'] = $filter_post;
		}
		
		if ( $filter_type ) {
			$args['change_type'] = $filter_type;
		}
		
		// Obtenir canvis
		$changes = $this->database->get_scheduled_changes( $args );
		$total_changes = $this->database->count_scheduled_changes( array(
			'status' => 'pending',
			'post_id' => $filter_post ? $filter_post : null,
			'change_type' => $filter_type ? $filter_type : null
		) );
		
		?>
		<!-- Filtres -->
		<div class="tablenav top">
			<div class="alignleft actions">
				<form method="get">
					<input type="hidden" name="page" value="until-wp-scheduled-changes">
					<input type="hidden" name="tab" value="scheduled">
					
					<select name="filter_type">
						<option value=""><?php _e( 'Tots els tipus', 'until-wp' ); ?></option>
						<option value="post_status" <?php selected( $filter_type, 'post_status' ); ?>>
							<?php _e( 'Canvi d\'estat', 'until-wp' ); ?>
						</option>
						<option value="sticky" <?php selected( $filter_type, 'sticky' ); ?>>
							<?php _e( 'Fixar/Desfixar', 'until-wp' ); ?>
						</option>
					</select>
					
					<input type="submit" class="button" value="<?php esc_attr_e( 'Filtrar', 'until-wp' ); ?>">
					
					<?php if ( $filter_type || $filter_post ) : ?>
						<a href="?page=until-wp-scheduled-changes&tab=scheduled" class="button">
							<?php _e( 'Esborrar filtres', 'until-wp' ); ?>
						</a>
					<?php endif; ?>
				</form>
			</div>
		</div>
		
		<!-- Taula -->
		<?php if ( ! empty( $changes ) ) : ?>
			<form method="post">
				<?php wp_nonce_field( 'until_wp_bulk_action', 'until_wp_bulk_nonce' ); ?>
				<input type="hidden" name="action" value="until_wp_bulk_cancel">
				
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<td class="check-column">
								<input type="checkbox" id="cb-select-all">
							</td>
							<th><?php _e( 'Post', 'until-wp' ); ?></th>
							<th><?php _e( 'Tipus de canvi', 'until-wp' ); ?></th>
							<th><?php _e( 'Nou valor', 'until-wp' ); ?></th>
							<th><?php _e( 'Programat per', 'until-wp' ); ?></th>
							<th><?php _e( 'Quan', 'until-wp' ); ?></th>
							<th><?php _e( 'Accions', 'until-wp' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $changes as $change ) : ?>
							<tr>
								<th class="check-column">
									<input type="checkbox" name="change_ids[]" value="<?php echo esc_attr( $change->id ); ?>">
								</th>
								<td>
									<?php
									$post = get_post( $change->post_id );
									if ( $post ) {
										echo '<a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">';
										echo esc_html( $post->post_title );
										echo '</a>';
										echo '<br><small>' . esc_html( get_post_type_object( $post->post_type )->labels->singular_name ) . '</small>';
									} else {
										echo '<em>' . __( 'Post eliminat', 'until-wp' ) . '</em>';
									}
									?>
								</td>
								<td>
									<?php echo esc_html( $this->get_change_type_label( $change->change_type ) ); ?>
								</td>
								<td>
									<?php echo esc_html( $this->get_change_value_label( $change->change_type, $change->new_value ) ); ?>
								</td>
								<td>
									<?php
									$user = get_userdata( $change->created_by );
									echo $user ? esc_html( $user->display_name ) : __( 'Desconegut', 'until-wp' );
									?>
								</td>
								<td>
									<?php echo esc_html( $this->format_scheduled_time( $change->scheduled_time ) ); ?>
								</td>
								<td>
									<button type="button" class="button button-small until-wp-cancel-single" data-change-id="<?php echo esc_attr( $change->id ); ?>">
										<?php _e( 'Cancel·lar', 'until-wp' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr>
							<td class="check-column">
								<input type="checkbox">
							</td>
							<th><?php _e( 'Post', 'until-wp' ); ?></th>
							<th><?php _e( 'Tipus de canvi', 'until-wp' ); ?></th>
							<th><?php _e( 'Nou valor', 'until-wp' ); ?></th>
							<th><?php _e( 'Programat per', 'until-wp' ); ?></th>
							<th><?php _e( 'Quan', 'until-wp' ); ?></th>
							<th><?php _e( 'Accions', 'until-wp' ); ?></th>
						</tr>
					</tfoot>
				</table>
				
				<!-- Accions en massa -->
				<div class="tablenav bottom">
					<div class="alignleft actions">
						<button type="submit" class="button action">
							<?php _e( 'Cancel·lar seleccionats', 'until-wp' ); ?>
						</button>
					</div>
				</div>
			</form>
			
			<!-- Paginació -->
			<?php
			$total_pages = ceil( $total_changes / $per_page );
			if ( $total_pages > 1 ) {
				$page_links = paginate_links( array(
					'base' => add_query_arg( 'paged', '%#%' ),
					'format' => '',
					'prev_text' => __( '&laquo;', 'until-wp' ),
					'next_text' => __( '&raquo;', 'until-wp' ),
					'total' => $total_pages,
					'current' => $current_page
				) );
				
				if ( $page_links ) {
					echo '<div class="tablenav"><div class="tablenav-pages">' . $page_links . '</div></div>';
				}
			}
			?>
		<?php else : ?>
			<p><?php _e( 'No hi ha canvis programats.', 'until-wp' ); ?></p>
		<?php endif; ?>
		<?php
	}
	
	/**
	 * Renderitzar la pestanya d'historial
	 */
	private function render_history_tab() {
		// Obtenir filtres
		$filter_post = isset( $_GET['filter_post'] ) ? absint( $_GET['filter_post'] ) : 0;
		$filter_type = isset( $_GET['filter_type'] ) ? sanitize_text_field( $_GET['filter_type'] ) : '';
		
		// Paginació
		$per_page = 20;
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$offset = ( $current_page - 1 ) * $per_page;
		
		// Preparar arguments
		$args = array(
			'limit' => $per_page,
			'offset' => $offset
		);
		
		if ( $filter_post ) {
			$args['post_id'] = $filter_post;
		}
		
		if ( $filter_type ) {
			$args['change_type'] = $filter_type;
		}
		
		// Obtenir historial
		$history = $this->database->get_history( $args );
		$total_history = $this->database->count_history( array(
			'post_id' => $filter_post ? $filter_post : null,
			'change_type' => $filter_type ? $filter_type : null
		) );
		
		?>
		<!-- Filtres -->
		<div class="tablenav top">
			<div class="alignleft actions">
				<form method="get">
					<input type="hidden" name="page" value="until-wp-scheduled-changes">
					<input type="hidden" name="tab" value="history">
					
					<select name="filter_type">
						<option value=""><?php _e( 'Tots els tipus', 'until-wp' ); ?></option>
						<option value="post_status" <?php selected( $filter_type, 'post_status' ); ?>>
							<?php _e( 'Canvi d\'estat', 'until-wp' ); ?>
						</option>
						<option value="sticky" <?php selected( $filter_type, 'sticky' ); ?>>
							<?php _e( 'Fixar/Desfixar', 'until-wp' ); ?>
						</option>
					</select>
					
					<input type="submit" class="button" value="<?php esc_attr_e( 'Filtrar', 'until-wp' ); ?>">
					
					<?php if ( $filter_type || $filter_post ) : ?>
						<a href="?page=until-wp-scheduled-changes&tab=history" class="button">
							<?php _e( 'Esborrar filtres', 'until-wp' ); ?>
						</a>
					<?php endif; ?>
				</form>
			</div>
		</div>
		
		<!-- Taula -->
		<?php if ( ! empty( $history ) ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php _e( 'Post', 'until-wp' ); ?></th>
						<th><?php _e( 'Canvi aplicat', 'until-wp' ); ?></th>
						<th><?php _e( 'Valor antic', 'until-wp' ); ?></th>
						<th><?php _e( 'Valor nou', 'until-wp' ); ?></th>
						<th><?php _e( 'Programat', 'until-wp' ); ?></th>
						<th><?php _e( 'Executat', 'until-wp' ); ?></th>
						<th><?php _e( 'Per qui', 'until-wp' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $history as $item ) : ?>
						<tr>
							<td>
								<?php
								$post = get_post( $item->post_id );
								if ( $post ) {
									echo '<a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">';
									echo esc_html( $post->post_title );
									echo '</a>';
									echo '<br><small>' . esc_html( get_post_type_object( $post->post_type )->labels->singular_name ) . '</small>';
								} else {
									echo '<em>' . __( 'Post eliminat', 'until-wp' ) . '</em>';
								}
								?>
							</td>
							<td>
								<?php echo esc_html( $this->get_change_type_label( $item->change_type ) ); ?>
							</td>
							<td>
								<?php echo esc_html( $this->get_change_value_label( $item->change_type, $item->old_value ) ); ?>
							</td>
							<td>
								<?php echo esc_html( $this->get_change_value_label( $item->change_type, $item->new_value ) ); ?>
							</td>
							<td>
								<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item->scheduled_time ) ) ); ?>
							</td>
							<td>
								<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item->executed_time ) ) ); ?>
							</td>
							<td>
								<?php
								$user = get_userdata( $item->created_by );
								echo $user ? esc_html( $user->display_name ) : __( 'Desconegut', 'until-wp' );
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				<tfoot>
					<tr>
						<th><?php _e( 'Post', 'until-wp' ); ?></th>
						<th><?php _e( 'Canvi aplicat', 'until-wp' ); ?></th>
						<th><?php _e( 'Valor antic', 'until-wp' ); ?></th>
						<th><?php _e( 'Valor nou', 'until-wp' ); ?></th>
						<th><?php _e( 'Programat', 'until-wp' ); ?></th>
						<th><?php _e( 'Executat', 'until-wp' ); ?></th>
						<th><?php _e( 'Per qui', 'until-wp' ); ?></th>
					</tr>
				</tfoot>
			</table>
			
			<!-- Paginació -->
			<?php
			$total_pages = ceil( $total_history / $per_page );
			if ( $total_pages > 1 ) {
				$page_links = paginate_links( array(
					'base' => add_query_arg( 'paged', '%#%' ),
					'format' => '',
					'prev_text' => __( '&laquo;', 'until-wp' ),
					'next_text' => __( '&raquo;', 'until-wp' ),
					'total' => $total_pages,
					'current' => $current_page
				) );
				
				if ( $page_links ) {
					echo '<div class="tablenav"><div class="tablenav-pages">' . $page_links . '</div></div>';
				}
			}
			?>
		<?php else : ?>
			<p><?php _e( "No hi ha historial de canvis.", 'until-wp' ); ?></p>
		<?php endif; ?>
		<?php
	}
	
	/**
	 * Obtenir l'etiqueta d'un tipus de canvi
	 *
	 * @param string $change_type Tipus de canvi
	 * @return string Etiqueta
	 */
	private function get_change_type_label( $change_type ) {
		$labels = array(
			'post_status' => __( 'Canvi d\'estat', 'until-wp' ),
			'sticky' => __( 'Fixar/Desfixar', 'until-wp' )
		);
		
		return isset( $labels[ $change_type ] ) ? $labels[ $change_type ] : $change_type;
	}
	
	/**
	 * Obtenir l'etiqueta d'un valor de canvi
	 *
	 * @param string $change_type Tipus de canvi
	 * @param string $value Valor
	 * @return string Etiqueta
	 */
	private function get_change_value_label( $change_type, $value ) {
		if ( $change_type === 'post_status' ) {
			$statuses = get_post_statuses();
			return isset( $statuses[ $value ] ) ? $statuses[ $value ] : $value;
		} elseif ( $change_type === 'sticky' ) {
			return ( $value === 'yes' ) ? __( 'Fixat', 'until-wp' ) : __( 'No fixat', 'until-wp' );
		}
		
		return $value;
	}
	
	/**
	 * Formatar el temps programat
	 *
	 * @param string $scheduled_time Temps programat (MySQL datetime)
	 * @return string Temps formatat
	 */
	private function format_scheduled_time( $scheduled_time ) {
		$timestamp = strtotime( $scheduled_time );
		$now = time();
		$diff = $timestamp - $now;
		
		if ( $diff < 0 ) {
			// Ja ha passat el temps
			return sprintf(
				/* translators: %s: Relative time */
				__( 'Fa %s', 'until-wp' ),
				human_time_diff( $timestamp, $now )
			) . '<br><small>' . date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) . '</small>';
		} else {
			// Encara no ha arribat
			return sprintf(
				/* translators: %s: Relative time */
				__( "D'aquí a %s", 'until-wp' ),
				human_time_diff( $now, $timestamp )
			) . '<br><small>' . date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) . '</small>';
		}
	}
	
	/**
	 * Processar accions d'administració
	 */
	public function process_admin_actions() {
		// Verificar si estem a la pàgina del plugin
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'until-wp-scheduled-changes' ) {
			return;
		}
		
		// Recrear taules
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'recreate_tables' ) {
			// Verificar nonce
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'until_wp_recreate_tables' ) ) {
				return;
			}
			
			// Verificar permisos
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			
			// Recrear les taules
			$this->database->create_tables();
			
			// Missatge de confirmació
			add_settings_error(
				'until_wp_messages',
				'until_wp_message',
				__( 'Les taules de la base de dades s\'han recreat correctament.', 'until-wp' ),
				'success'
			);
			
			// Redirigir
			wp_safe_redirect( admin_url( 'tools.php?page=until-wp-scheduled-changes' ) );
			exit;
		}
	}
	
	/**
	 * Processar accions en massa
	 */
	public function process_bulk_actions() {
		// Verificar que estem a la pàgina correcta
		if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'until_wp_bulk_cancel' ) {
			return;
		}
		
		// Verificar nonce
		if ( ! isset( $_POST['until_wp_bulk_nonce'] ) || ! wp_verify_nonce( $_POST['until_wp_bulk_nonce'], 'until_wp_bulk_action' ) ) {
			return;
		}
		
		// Verificar permisos
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		
		// Obtenir IDs
		$change_ids = isset( $_POST['change_ids'] ) ? array_map( 'absint', $_POST['change_ids'] ) : array();
		
		if ( empty( $change_ids ) ) {
			add_settings_error( 'until_wp_messages', 'until_wp_message', __( 'No s\'ha seleccionat cap canvi.', 'until-wp' ), 'error' );
			return;
		}
		
		// Cancel·lar els canvis
		$cancelled = 0;
		foreach ( $change_ids as $change_id ) {
			if ( $this->database->cancel_scheduled_change( $change_id ) ) {
				$cancelled++;
			}
		}
		
		// Missatge de confirmació
		if ( $cancelled > 0 ) {
			add_settings_error(
				'until_wp_messages',
				'until_wp_message',
				sprintf(
					/* translators: %d: Number of changes cancelled */
					_n( '%d canvi cancel·lat.', '%d canvis cancel·lats.', $cancelled, 'until-wp' ),
					$cancelled
				),
				'success'
			);
		}
		
		// Redirigir per evitar resubmissió
		wp_safe_redirect( add_query_arg( array(
			'page' => 'until-wp-scheduled-changes',
			'tab' => 'scheduled'
		), admin_url( 'tools.php' ) ) );
		exit;
	}
	
	/**
	 * AJAX: Cancel·lar un canvi
	 */
	public function ajax_cancel_change() {
		// Verificar permisos
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tens permisos per fer això.', 'until-wp' ) ) );
		}
		
		// Obtenir ID del canvi
		$change_id = isset( $_POST['change_id'] ) ? absint( $_POST['change_id'] ) : 0;
		
		if ( ! $change_id ) {
			wp_send_json_error( array( 'message' => __( 'ID de canvi invàlid.', 'until-wp' ) ) );
		}
		
		// Cancel·lar el canvi
		$result = $this->database->cancel_scheduled_change( $change_id );
		
		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Canvi cancel·lat correctament!', 'until-wp' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Error al cancel·lar el canvi.', 'until-wp' ) ) );
		}
	}
	
	/**
	 * Mostrar missatges d'administració
	 */
	private function display_messages() {
		settings_errors( 'until_wp_messages' );
	}
	
	/**
	 * Carregar scripts i estils
	 *
	 * @param string $hook Hook de la pàgina actual
	 */
	public function enqueue_scripts( $hook ) {
		// Només a la pàgina del plugin
		if ( $hook !== 'tools_page_until-wp-scheduled-changes' ) {
			return;
		}
		
		// CSS
		wp_enqueue_style(
			'until-wp-admin',
			UNTIL_WP_PLUGIN_URL . 'assets/css/admin-styles.css',
			array(),
			UNTIL_WP_VERSION
		);
		
		// JavaScript
		wp_enqueue_script(
			'until-wp-admin',
			UNTIL_WP_PLUGIN_URL . 'assets/js/admin-scripts.js',
			array( 'jquery' ),
			UNTIL_WP_VERSION,
			true
		);
		
		// Localitzar script amb dades
		wp_localize_script(
			'until-wp-admin',
			'untilWP',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'until_wp_metabox' ),
				'i18n' => array(
					'confirm_cancel' => __( 'Estàs segur que vols cancel·lar aquest canvi programat?', 'until-wp' ),
					'error' => __( 'Error', 'until-wp' ),
					'success' => __( 'Èxit', 'until-wp' )
				)
			)
		);
	}
}

