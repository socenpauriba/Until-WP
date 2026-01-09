<?php
/**
 * Classe per gestionar el meta box a l'editor de posts
 *
 * @package Until_WP
 */

// Si aquest fitxer s'accedeix directament, avortar
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Until_WP_Metabox {
	
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
		// Afegir el meta box
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		
		// Processar el formulari
		add_action( 'wp_ajax_until_wp_schedule_change', array( $this, 'ajax_schedule_change' ) );
		add_action( 'wp_ajax_until_wp_cancel_change', array( $this, 'ajax_cancel_change' ) );
		
		// Carregar els scripts i estils
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}
	
	/**
	 * Afegir el meta box
	 */
	public function add_meta_box() {
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'until-wp-schedule',
				__( 'Programar Canvis', 'until-wp' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'side',
				'default'
			);
		}
	}
	
	/**
	 * Renderitzar el meta box
	 *
	 * @param WP_Post $post Objecte del post
	 */
	public function render_meta_box( $post ) {
		// Verificar permisos
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		
		// Obtenir els canvis programats per aquest post
		$scheduled_changes = $this->database->get_scheduled_changes( array(
			'post_id' => $post->ID,
			'status' => 'pending',
			'orderby' => 'scheduled_time',
			'order' => 'ASC'
		) );
		
		// Nonce per seguretat
		wp_nonce_field( 'until_wp_metabox', 'until_wp_metabox_nonce' );
		
		?>
		<div class="until-wp-metabox">
			
			<!-- Formulari per afegir nous canvis -->
			<div class="until-wp-schedule-form">
				<h4><?php _e( 'Afegir Nou Canvi', 'until-wp' ); ?></h4>
				
				<!-- Tipus de canvi -->
				<p>
					<label for="until-wp-change-type">
						<strong><?php _e( 'Tipus de canvi:', 'until-wp' ); ?></strong>
					</label>
					<select id="until-wp-change-type" class="widefat">
						<option value=""><?php _e( 'Selecciona...', 'until-wp' ); ?></option>
						<optgroup label="<?php esc_attr_e( 'Estat', 'until-wp' ); ?>">
							<option value="post_status:publish"><?php _e( 'Canviar a Publicat', 'until-wp' ); ?></option>
							<option value="post_status:draft"><?php _e( 'Canviar a Esborrany', 'until-wp' ); ?></option>
							<option value="post_status:pending"><?php _e( 'Canviar a Pendent de revisió', 'until-wp' ); ?></option>
							<option value="post_status:private"><?php _e( 'Canviar a Privat', 'until-wp' ); ?></option>
						</optgroup>
						<optgroup label="<?php esc_attr_e( 'Destacat', 'until-wp' ); ?>">
							<option value="sticky:yes"><?php _e( 'Fixar entrada', 'until-wp' ); ?></option>
							<option value="sticky:no"><?php _e( 'Desfixar entrada', 'until-wp' ); ?></option>
						</optgroup>
						<optgroup label="<?php esc_attr_e( 'Avançat', 'until-wp' ); ?>">
							<option value="custom_function:custom"><?php _e( 'Executar funció personalitzada', 'until-wp' ); ?></option>
						</optgroup>
					</select>
				</p>
				
				<!-- Camp per funció personalitzada (ocult per defecte) -->
				<p id="until-wp-custom-function-field" style="display: none;">
					<label for="until-wp-custom-function-name">
						<strong><?php _e( 'Nom de la funció:', 'until-wp' ); ?></strong>
					</label>
					<input type="text" id="until-wp-custom-function-name" class="widefat" placeholder="nom_de_la_funcio">
					<small style="display: block; margin-top: 5px; color: #666;">
						<?php _e( 'Exemple: processar_post_automaticament', 'until-wp' ); ?><br>
						<?php _e( 'La funció rebrà el post_id com a paràmetre.', 'until-wp' ); ?>
					</small>
				</p>
				
				<!-- Tabs per temps relatiu/absolut -->
				<div class="until-wp-time-tabs">
					<div class="until-wp-tab-buttons">
						<button type="button" class="until-wp-tab-btn active" data-tab="relative">
							<?php _e( 'Relatiu', 'until-wp' ); ?>
						</button>
						<button type="button" class="until-wp-tab-btn" data-tab="absolute">
							<?php _e( 'Absolut', 'until-wp' ); ?>
						</button>
					</div>
					
					<!-- Tab de temps relatiu -->
					<div class="until-wp-tab-content active" id="until-wp-tab-relative">
						<p>
							<label>
								<strong><?php _e( "D'aquí a:", 'until-wp' ); ?></strong>
							</label>
							<input type="number" id="until-wp-relative-amount" min="1" value="1" class="small-text">
							<select id="until-wp-relative-unit">
								<option value="minutes"><?php _e( 'Minuts', 'until-wp' ); ?></option>
								<option value="hours" selected><?php _e( 'Hores', 'until-wp' ); ?></option>
								<option value="days"><?php _e( 'Dies', 'until-wp' ); ?></option>
								<option value="weeks"><?php _e( 'Setmanes', 'until-wp' ); ?></option>
							</select>
						</p>
					</div>
					
					<!-- Tab de temps absolut -->
					<div class="until-wp-tab-content" id="until-wp-tab-absolute">
						<p>
							<label>
								<strong><?php _e( 'Data i hora:', 'until-wp' ); ?></strong>
							</label>
							<input type="datetime-local" id="until-wp-absolute-datetime" class="widefat">
						</p>
					</div>
				</div>
				
				<!-- Botó per afegir -->
				<p>
					<button type="button" id="until-wp-add-change" class="button button-primary button-large widefat">
						<?php _e( 'Programar Canvi', 'until-wp' ); ?>
					</button>
				</p>
				
				<!-- Missatges -->
				<div id="until-wp-message" class="until-wp-message" style="display: none;"></div>
			</div>
			
			<!-- Llista de canvis programats -->
			<?php if ( ! empty( $scheduled_changes ) ) : ?>
				<div class="until-wp-scheduled-list">
					<h4><?php _e( 'Canvis Programats', 'until-wp' ); ?></h4>
					<ul>
						<?php foreach ( $scheduled_changes as $change ) : ?>
							<li data-change-id="<?php echo esc_attr( $change->id ); ?>">
								<div class="until-wp-change-item">
									<div class="until-wp-change-info">
										<strong><?php echo esc_html( $this->get_change_label( $change ) ); ?></strong>
										<br>
										<span class="until-wp-change-time">
											<?php echo esc_html( $this->format_scheduled_time( $change->scheduled_time ) ); ?>
										</span>
									</div>
									<div class="until-wp-change-actions">
										<button type="button" class="button button-small until-wp-cancel-change" data-change-id="<?php echo esc_attr( $change->id ); ?>">
											<?php _e( 'Cancel·lar', 'until-wp' ); ?>
										</button>
									</div>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php else : ?>
				<p class="until-wp-no-changes">
					<em><?php _e( 'No hi ha canvis programats per aquest post.', 'until-wp' ); ?></em>
				</p>
			<?php endif; ?>
			
			<!-- Spinner per càrrega -->
			<div class="until-wp-spinner" style="display: none;">
				<span class="spinner is-active"></span>
			</div>
			
		</div>
		
		<input type="hidden" id="until-wp-post-id" value="<?php echo esc_attr( $post->ID ); ?>">
		<?php
	}
	
	/**
	 * Obtenir l'etiqueta descriptiva d'un canvi
	 *
	 * @param object $change Objecte del canvi
	 * @return string Etiqueta descriptiva
	 */
	private function get_change_label( $change ) {
		switch ( $change->change_type ) {
			case 'post_status':
				$statuses = array(
					'publish' => __( 'Publicat', 'until-wp' ),
					'draft' => __( 'Esborrany', 'until-wp' ),
					'pending' => __( 'Pendent de revisió', 'until-wp' ),
					'private' => __( 'Privat', 'until-wp' )
				);
				$status_name = isset( $statuses[ $change->new_value ] ) ? $statuses[ $change->new_value ] : $change->new_value;
				return sprintf( __( 'Canviar a %s', 'until-wp' ), $status_name );
				
			case 'sticky':
				return ( $change->new_value === 'yes' ) ? __( 'Fixar entrada', 'until-wp' ) : __( 'Desfixar entrada', 'until-wp' );
				
			case 'custom_function':
				return sprintf( __( 'Executar %s()', 'until-wp' ), $change->new_value );
				
			default:
				return __( 'Canvi desconegut', 'until-wp' );
		}
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
			return sprintf(
				/* translators: %s: Formatted date and time */
				__( 'Hauria d\'haver estat executat: %s', 'until-wp' ),
				date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp )
			);
		}
		
		// Mostrar temps relatiu amb més precisió
		$relative = $this->format_time_diff( $diff );
		
		return sprintf(
			/* translators: 1: Relative time, 2: Formatted date and time */
			__( "D'aquí a %1\$s (%2\$s)", 'until-wp' ),
			$relative,
			date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp )
		);
	}
	
	/**
	 * Formatar la diferència de temps amb més precisió
	 *
	 * @param int $seconds Diferència en segons
	 * @return string Temps formatat
	 */
	private function format_time_diff( $seconds ) {
		if ( $seconds < 60 ) {
			return sprintf(
				_n( '%s segon', '%s segons', $seconds, 'until-wp' ),
				number_format_i18n( $seconds )
			);
		}
		
		if ( $seconds < 3600 ) {
			$minutes = floor( $seconds / 60 );
			return sprintf(
				_n( '%s minut', '%s minuts', $minutes, 'until-wp' ),
				number_format_i18n( $minutes )
			);
		}
		
		if ( $seconds < 86400 ) {
			$hours = floor( $seconds / 3600 );
			$minutes = floor( ( $seconds % 3600 ) / 60 );
			
			if ( $minutes > 0 ) {
				return sprintf(
					/* translators: 1: hours, 2: minutes */
					__( '%1$s h %2$s min', 'until-wp' ),
					number_format_i18n( $hours ),
					number_format_i18n( $minutes )
				);
			}
			
			return sprintf(
				_n( '%s hora', '%s hores', $hours, 'until-wp' ),
				number_format_i18n( $hours )
			);
		}
		
		if ( $seconds < 604800 ) {
			$days = floor( $seconds / 86400 );
			$hours = floor( ( $seconds % 86400 ) / 3600 );
			
			if ( $hours > 0 ) {
				return sprintf(
					/* translators: 1: days, 2: hours */
					__( '%1$s dies %2$s h', 'until-wp' ),
					number_format_i18n( $days ),
					number_format_i18n( $hours )
				);
			}
			
			return sprintf(
				_n( '%s dia', '%s dies', $days, 'until-wp' ),
				number_format_i18n( $days )
			);
		}
		
		// Per més d'una setmana, usar human_time_diff()
		return human_time_diff( time(), time() + $seconds );
	}
	
	/**
	 * AJAX: Programar un canvi
	 */
	public function ajax_schedule_change() {
		// Verificar nonce
		check_ajax_referer( 'until_wp_metabox', 'nonce' );
		
		// Obtenir dades
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$change_type_full = isset( $_POST['change_type'] ) ? sanitize_text_field( $_POST['change_type'] ) : '';
		$time_type = isset( $_POST['time_type'] ) ? sanitize_text_field( $_POST['time_type'] ) : '';
		
		// Debug logging (només si WP_DEBUG està activat)
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Until WP - Programant canvi: post_id=' . $post_id . ', change_type=' . $change_type_full . ', time_type=' . $time_type );
		}
		
		// Verificar permisos
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'No tens permisos per fer això.', 'until-wp' ) ) );
		}
		
		// Separar tipus i valor
		$parts = explode( ':', $change_type_full, 2 );
		if ( count( $parts ) !== 2 ) {
			wp_send_json_error( array( 'message' => __( 'Tipus de canvi invàlid.', 'until-wp' ) ) );
		}
		
		$change_type = $parts[0];
		$new_value = $parts[1];
		
		// Preparar dades del temps
		$time_data = array();
		
		if ( $time_type === 'relative' ) {
			$time_data = array(
				'amount' => isset( $_POST['relative_amount'] ) ? absint( $_POST['relative_amount'] ) : 0,
				'unit' => isset( $_POST['relative_unit'] ) ? sanitize_text_field( $_POST['relative_unit'] ) : ''
			);
		} elseif ( $time_type === 'absolute' ) {
			$datetime = isset( $_POST['absolute_datetime'] ) ? sanitize_text_field( $_POST['absolute_datetime'] ) : '';
			
			// Convertir datetime-local a MySQL datetime
			if ( $datetime ) {
				// El datetime-local ve en format: 2026-01-05T15:30
				// L'usuari introdueix la data en el seu timezone local
				// Hem de convertir-ho a MySQL datetime tenint en compte el timezone de WordPress
				
				// Reemplaçar T per espai per tenir format estàndard
				$datetime_formatted = str_replace( 'T', ' ', $datetime );
				
				// Crear DateTime object en el timezone de WordPress
				$wp_timezone = wp_timezone();
				$date_obj = date_create( $datetime_formatted, $wp_timezone );
				
				if ( $date_obj ) {
					// Convertir a MySQL datetime en el timezone de WordPress
					$time_data = array(
						'datetime' => $date_obj->format( 'Y-m-d H:i:s' )
					);
				} else {
					wp_send_json_error( array( 'message' => __( 'Format de data invàlid.', 'until-wp' ) ) );
				}
			}
		}
		
		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'Until WP - Dades finals: change_type=' . $change_type . ', new_value=' . $new_value . ', time_type=' . $time_type . ', time_data=' . print_r( $time_data, true ) );
		}
		
		// Programar el canvi
		$scheduler = until_wp()->scheduler;
		$result = $scheduler->schedule_change( $post_id, $change_type, $new_value, $time_type, $time_data );
		
		// Comprovar si hi ha error
		if ( is_array( $result ) && isset( $result['error'] ) ) {
			wp_send_json_error( array( 'message' => $result['error'] ) );
		}
		
		if ( $result && is_numeric( $result ) ) {
			// Obtenir el canvi programat per retornar-lo
			$change = $this->database->get_scheduled_change( $result );
			
			if ( $change ) {
				wp_send_json_success( array(
					'message' => __( 'Canvi programat correctament!', 'until-wp' ),
					'change' => array(
						'id' => $change->id,
						'label' => $this->get_change_label( $change ),
						'time' => $this->format_scheduled_time( $change->scheduled_time )
					)
				) );
			} else {
				wp_send_json_error( array( 'message' => __( 'Canvi creat però no es pot recuperar de la base de dades.', 'until-wp' ) ) );
			}
		} else {
			wp_send_json_error( array( 'message' => __( 'Error desconegut al programar el canvi.', 'until-wp' ) ) );
		}
	}
	
	/**
	 * AJAX: Cancel·lar un canvi
	 */
	public function ajax_cancel_change() {
		// Verificar nonce
		check_ajax_referer( 'until_wp_metabox', 'nonce' );
		
		// Obtenir ID del canvi
		$change_id = isset( $_POST['change_id'] ) ? absint( $_POST['change_id'] ) : 0;
		
		if ( ! $change_id ) {
			wp_send_json_error( array( 'message' => __( 'ID de canvi invàlid.', 'until-wp' ) ) );
		}
		
		// Cancel·lar el canvi
		$scheduler = until_wp()->scheduler;
		$result = $scheduler->cancel_change( $change_id );
		
		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Canvi cancel·lat correctament!', 'until-wp' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Error al cancel·lar el canvi.', 'until-wp' ) ) );
		}
	}
	
	/**
	 * Carregar scripts i estils
	 *
	 * @param string $hook Hook de la pàgina actual
	 */
	public function enqueue_scripts( $hook ) {
		// Només a les pàgines d'edició de posts
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ) ) ) {
			return;
		}
		
		// CSS
		wp_enqueue_style(
			'until-wp-metabox',
			UNTIL_WP_PLUGIN_URL . 'assets/css/admin-styles.css',
			array(),
			UNTIL_WP_VERSION
		);
		
		// JavaScript
		wp_enqueue_script(
			'until-wp-metabox',
			UNTIL_WP_PLUGIN_URL . 'assets/js/admin-scripts.js',
			array( 'jquery' ),
			UNTIL_WP_VERSION,
			true
		);
		
		// Localitzar script amb dades
		wp_localize_script(
			'until-wp-metabox',
			'untilWP',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'until_wp_metabox' ),
				'i18n' => array(
					'error' => __( 'Error', 'until-wp' ),
					'success' => __( 'Èxit', 'until-wp' ),
					'confirm_cancel' => __( 'Estàs segur que vols cancel·lar aquest canvi programat?', 'until-wp' ),
					'select_change_type' => __( 'Si us plau, selecciona un tipus de canvi.', 'until-wp' ),
					'invalid_amount' => __( 'La quantitat ha de ser un número positiu.', 'until-wp' ),
					'invalid_datetime' => __( 'Si us plau, selecciona una data i hora vàlides.', 'until-wp' ),
					'enter_function_name' => __( 'Si us plau, introdueix el nom de la funció.', 'until-wp' )
				)
			)
		);
	}
}

