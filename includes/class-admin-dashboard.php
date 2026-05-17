<?php
// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminDashboard {

	const LIST_PER_PAGE = 30;

	/**
	 * @param mixed $value Stored decimal or null.
	 * @return string Safe HTML fragment.
	 */
	private function format_customer_amount_cell( $value ) {
		if ( null === $value || '' === $value ) {
			return '<span aria-hidden="true">—</span>';
		}

		return esc_html( number_format( (float) $value, 2, '.', ',' ) );
	}

	/**
	 * Parse optional monetary field from POST (admin customer edit).
	 *
	 * @param string $key   POST key.
	 * @param string $label Human label for errors.
	 * @return array{ok:bool, value:?float, error?:string}
	 */
	private function parse_amount_post_field( $key, $label ) {
		if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return array( 'ok' => true, 'value' => null );
		}
		$raw = trim( (string) wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' === $raw ) {
			return array( 'ok' => true, 'value' => null );
		}
		$clean = preg_replace( '/[^0-9.\-]/', '', $raw );
		if ( '' === $clean || ! is_numeric( $clean ) ) {
			return array( 'ok' => false, 'error' => sprintf( 'Invalid amount for %s', $label ) );
		}

		return array( 'ok' => true, 'value' => round( (float) $clean, 2 ) );
	}

	/**
	 * @return bool
	 */
	private function admin_ajax_verify() {
		check_ajax_referer( 'agent_management_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'agent-management' ) ), 403 );
		}

		return true;
	}

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
		add_action( 'wp_ajax_update_agent_status', array( $this, 'update_agent_status' ) );
		add_action( 'wp_ajax_update_customer_status', array( $this, 'update_customer_status' ) );
		add_action( 'wp_ajax_agent_management_get_agent_customers', array( $this, 'ajax_get_agent_customers' ) );
		add_action( 'wp_ajax_agent_management_admin_get_agent', array( $this, 'ajax_admin_get_agent' ) );
		add_action( 'wp_ajax_agent_management_admin_save_agent', array( $this, 'ajax_admin_save_agent' ) );
		add_action( 'wp_ajax_agent_management_admin_delete_agent', array( $this, 'ajax_admin_delete_agent' ) );
		add_action( 'wp_ajax_agent_management_admin_get_customer', array( $this, 'ajax_admin_get_customer' ) );
		add_action( 'wp_ajax_agent_management_admin_save_customer', array( $this, 'ajax_admin_save_customer' ) );
		add_action( 'wp_ajax_agent_management_admin_delete_customer', array( $this, 'ajax_admin_delete_customer' ) );
	}

	/**
	 * Scripts and styles for Agent Management admin page only.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_agent-management' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'agent-management-admin',
			AGENT_MANAGEMENT_PLUGIN_URL . 'assets/admin-dashboard.css',
			array(),
			'1.6'
		);

		wp_enqueue_script(
			'agent-management-admin',
			AGENT_MANAGEMENT_PLUGIN_URL . 'assets/admin-dashboard.js',
			array( 'jquery' ),
			'1.6',
			true
		);

		wp_localize_script(
			'agent-management-admin',
			'amgAdmin',
			array(
				'ajaxurl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'agent_management_admin' ),
				'countries'=> AgentDashboard::get_countries_list(),
				'i18n'     => array(
					'confirmDeleteAgent'   => __( 'Delete this agent, all of their customers, and the WordPress user account? This cannot be undone.', 'agent-management' ),
					'confirmDeleteCustomer'=> __( 'Delete this customer and their stored images? This cannot be undone.', 'agent-management' ),
					'saved'                => __( 'Saved.', 'agent-management' ),
					'saveFailed'           => __( 'Could not save. Please try again.', 'agent-management' ),
					'loadFailed'           => __( 'Could not load data.', 'agent-management' ),
				),
			)
		);
	}

	/**
	 * Body class for scoped admin styling.
	 *
	 * @param string $classes Existing classes.
	 * @return string
	 */
	public function admin_body_class( $classes ) {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return $classes;
		}

		$screen = get_current_screen();
		if ( $screen && 'toplevel_page_agent-management' === $screen->id ) {
			$classes .= ' amg-agent-management-page';
		}

		return $classes;
	}

	public function add_admin_menu() {
		add_menu_page(
			'Agent Management',
			'Agent Management',
			'manage_options',
			'agent-management',
			array( $this, 'admin_dashboard_page' ),
			'dashicons-groups',
			30
		);
	}

	public function admin_dashboard_page() {
		?>
		<div class="wrap amg-shell">
			<div class="amg-admin-app">
				<h1 class="amg-page-title"><?php esc_html_e( 'Agent Management', 'agent-management' ); ?></h1>

				<div class="amg-tab-list" role="tablist">
					<a href="#agents" class="amg-tab is-active" role="tab" aria-selected="true"><?php esc_html_e( 'Agents', 'agent-management' ); ?></a>
					<a href="#customers" class="amg-tab" role="tab" aria-selected="false"><?php esc_html_e( 'Customers', 'agent-management' ); ?></a>
				</div>

				<div id="agents" class="amg-panel is-active" role="tabpanel">
					<h2 class="amg-panel-title"><?php esc_html_e( 'Registered agents', 'agent-management' ); ?></h2>
					<p class="agent-status-update amg-flash" style="display: none;" role="status"><?php esc_html_e( 'Updating agent status…', 'agent-management' ); ?></p>
					<?php $this->display_agents_table(); ?>
				</div>

				<div id="customers" class="amg-panel" role="tabpanel" hidden>
					<h2 class="amg-panel-title"><?php esc_html_e( 'All customers', 'agent-management' ); ?></h2>
					<p class="customer-status-update amg-flash" style="display: none;" role="status"><?php esc_html_e( 'Updating customer status…', 'agent-management' ); ?></p>
					<?php $this->display_customers_table(); ?>
				</div>
			</div>
		</div>

		<script>
			jQuery(document).ready(function($) {
				$('.amg-tab').on('click', function(e) {
					e.preventDefault();
					var target = $(this).attr('href');
					$('.amg-tab').removeClass('is-active').attr('aria-selected', 'false');
					$('.amg-panel').removeClass('is-active').attr('hidden', true);

					$(this).addClass('is-active').attr('aria-selected', 'true');
					$(target).addClass('is-active').removeAttr('hidden');
				});
			});
		</script>
		<?php
	}

	/**
	 * Fetch additional image URLs keyed by customer id.
	 *
	 * @param int[] $customer_ids Customer row ids.
	 * @return array<int, array<int, string>>
	 */
	private function get_additional_images_map( array $customer_ids ) {
		global $wpdb;
		$customer_ids = array_filter( array_map( 'intval', $customer_ids ) );
		if ( empty( $customer_ids ) ) {
			return array();
		}

		$table           = $wpdb->prefix . 'agent_customer_images';
		$placeholders    = implode( ',', array_fill( 0, count( $customer_ids ), '%d' ) );
		$sql             = "SELECT customer_id, image_url FROM {$table} WHERE customer_id IN ({$placeholders}) ORDER BY id ASC";
		$prepared        = $wpdb->prepare( $sql, ...$customer_ids );
		$rows            = $wpdb->get_results( $prepared, ARRAY_A );
		$map             = array();

		foreach ( $rows as $row ) {
			$cid = (int) $row['customer_id'];
			if ( ! isset( $map[ $cid ] ) ) {
				$map[ $cid ] = array();
			}
			$map[ $cid ][] = $row['image_url'];
		}

		return $map;
	}

	/**
	 * HTML for passport + extras as thumbnails opening the admin lightbox.
	 */
	private function render_customer_images_cell( $passport_image, array $extras ) {
		$urls = array();
		if ( ! empty( $passport_image ) ) {
			$urls[] = array(
				'url'   => $passport_image,
				'title' => __( 'Passport', 'agent-management' ),
			);
		}
		foreach ( $extras as $i => $u ) {
			if ( empty( $u ) ) {
				continue;
			}
			$urls[] = array(
				'url'   => $u,
				'title' => sprintf(
					/* translators: %d is image index */
					__( 'Additional image %d', 'agent-management' ),
					$i + 1
				),
			);
		}

		if ( empty( $urls ) ) {
			return '<span aria-hidden="true">—</span>';
		}

		$html = '<div class="amg-thumb-wrap">';
		foreach ( $urls as $item ) {
			$html .= sprintf(
				'<button type="button" class="amg-thumb amg-lightbox-trigger" data-full="%s" data-title="%s" title="%s">%s</button>',
				esc_attr( $item['url'] ),
				esc_attr( $item['title'] ),
				esc_attr( $item['title'] ),
				sprintf(
					'<img src="%s" alt="">',
					esc_url( $item['url'] )
				)
			);
		}
		$html .= '</div>';

		return $html;
	}

	/**
	 * Markup mirroring agent frontend “Customers by Country” (grouped pills + table).
	 *
	 * @param array $customers Customer rows from DB.
	 * @param array $img_map   Extra image URLs by customer id, keyed by customer id.
	 * @return string
	 */
	private function render_agent_modal_customers_frontend_layout( array $customers, array $img_map ) {
		ob_start();

		if ( empty( $customers ) ) {
			?>
			<div class="amg-fe-mirror tab-content active">
				<h3><?php esc_html_e( 'Customers by Country', 'agent-management' ); ?></h3>
				<p class="country-empty-state" style="padding: 24px; text-align: center; margin: 0;"><?php esc_html_e( 'No customers for this agent.', 'agent-management' ); ?></p>
			</div>
			<?php
			return ob_get_clean();
		}

		$by_country = array();
		foreach ( $customers as $cust ) {
			$label = isset( $cust->visa_country ) && '' !== trim( (string) $cust->visa_country )
				? $cust->visa_country
				: __( '(No country)', 'agent-management' );
			if ( ! isset( $by_country[ $label ] ) ) {
				$by_country[ $label ] = array();
			}
			$by_country[ $label ][] = $cust;
		}
		uksort( $by_country, 'strnatcasecmp' );

		$pill_keys = array_keys( $by_country );
		?>
		<div class="amg-fe-mirror tab-content active">
			<h3><?php esc_html_e( 'Customers by Country', 'agent-management' ); ?></h3>
			<div id="amg-admin-country-tab-content">
				<div class="country-filter-list">
					<?php foreach ( $pill_keys as $idx => $country_label ) : ?>
						<button type="button" class="country-pill<?php echo 0 === $idx ? ' active' : ''; ?>" data-country="<?php echo esc_attr( $country_label ); ?>">
							<?php echo esc_html( $country_label ); ?>
							<span class="pill-count"><?php echo (int) count( $by_country[ $country_label ] ); ?></span>
						</button>
					<?php endforeach; ?>
				</div>
				<div id="amg-admin-country-customers-wrap">
					<?php foreach ( $pill_keys as $idx => $country_label ) : ?>
						<?php
						$list     = $by_country[ $country_label ];
						$is_first = ( 0 === $idx );
						?>
						<div class="amg-country-panel tab-country-panel" data-country-panel="<?php echo esc_attr( $country_label ); ?>" <?php echo $is_first ? '' : 'hidden'; ?>>
							<div class="country-customers-section">
								<h4>
									<?php esc_html_e( 'Customers in', 'agent-management' ); ?>
									<span class="country-badge"><?php echo esc_html( $country_label ); ?></span>
									— <?php echo (int) count( $list ); ?> <?php esc_html_e( 'total', 'agent-management' ); ?>
								</h4>
								<div class="customer-table-container">
									<table class="customer-table">
										<thead>
											<tr>
												<th><?php esc_html_e( 'Name', 'agent-management' ); ?></th>
												<th><?php esc_html_e( 'Phone', 'agent-management' ); ?></th>
												<th><?php esc_html_e( 'Passport No.', 'agent-management' ); ?></th>
												<th><?php esc_html_e( 'Visa Type', 'agent-management' ); ?></th>
												<th><?php esc_html_e( 'Submission Date', 'agent-management' ); ?></th>
												<th><?php esc_html_e( 'Total amount', 'agent-management' ); ?></th>
												<th><?php esc_html_e( 'Deposit amount', 'agent-management' ); ?></th>
												<th><?php esc_html_e( 'Status', 'agent-management' ); ?></th>
												<th><?php esc_html_e( 'Images', 'agent-management' ); ?></th>
											</tr>
										</thead>
										<tbody>
											<?php foreach ( $list as $row ) : ?>
												<?php
												$extras = isset( $img_map[ (int) $row->id ] ) ? $img_map[ (int) $row->id ] : array();
												$status = strtolower( sanitize_text_field( (string) $row->status ) );
												if ( ! in_array( $status, array( 'pending', 'approved', 'rejected' ), true ) ) {
													$status = 'pending';
												}
												$badge_class = 'status-' . $status;
												?>
												<tr>
													<td><?php echo esc_html( $row->customer_name ); ?></td>
													<td><?php echo esc_html( $row->customer_phone ); ?></td>
													<td><?php echo esc_html( $row->passport_number ); ?></td>
													<td><?php echo esc_html( $row->visa_type ); ?></td>
													<td><?php echo esc_html( $row->submission_date ); ?></td>
													<td><?php echo $this->format_customer_amount_cell( isset( $row->total_amount ) ? $row->total_amount : null ); ?></td>
													<td><?php echo $this->format_customer_amount_cell( isset( $row->deposit_amount ) ? $row->deposit_amount : null ); ?></td>
													<td><span class="<?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span></td>
													<td>
														<div class="customer-images amg-fe-images">
															<?php echo $this->render_customer_images_cell( $row->passport_image, $extras ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
														</div>
													</td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Query args to keep when paginating (other tab’s search is dropped intentionally).
	 */
	private function agents_pagination_args() {
		$args = array();
		if ( isset( $_GET['agents_search'] ) && '' !== $_GET['agents_search'] ) {
			$args['agents_search'] = sanitize_text_field( wp_unslash( $_GET['agents_search'] ) );
		}
		return $args;
	}

	private function customers_pagination_args() {
		$args = array();
		if ( isset( $_GET['customers_search'] ) && '' !== $_GET['customers_search'] ) {
			$args['customers_search'] = sanitize_text_field( wp_unslash( $_GET['customers_search'] ) );
		}
		return $args;
	}

	private function display_agents_table() {
		global $wpdb;
		$agents_table = $wpdb->prefix . 'agents';
		$users_table  = $wpdb->prefix . 'users';

		$per_page     = self::LIST_PER_PAGE;
		$current_page = isset( $_GET['agents_page'] ) ? max( 1, intval( $_GET['agents_page'] ) ) : 1;
		$offset       = ( $current_page - 1 ) * $per_page;
		$search       = isset( $_GET['agents_search'] ) ? sanitize_text_field( wp_unslash( $_GET['agents_search'] ) ) : '';
		$search_like  = '%' . $wpdb->esc_like( $search ) . '%';

		$where_sql = '';
		if ( $search !== '' ) {
			$where_sql = $wpdb->prepare(
				' WHERE ( a.company_name LIKE %s OR a.phone LIKE %s OR a.license_number LIKE %s OR a.address LIKE %s OR a.username LIKE %s OR u.user_email LIKE %s OR u.user_login LIKE %s ) ',
				$search_like,
				$search_like,
				$search_like,
				$search_like,
				$search_like,
				$search_like,
				$search_like
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where_sql is built with prepare or empty.
		$total_agents = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$agents_table} a LEFT JOIN {$users_table} u ON a.user_id = u.ID {$where_sql}" );
		$total_pages  = max( 1, (int) ceil( $total_agents / $per_page ) );

		// LIMIT/OFFSET concatenated — $where_sql is either empty or already prepared (LIKE % would break nested prepare).
		$sql_agents = "SELECT a.*, u.user_email, u.user_login
			FROM {$agents_table} a
			LEFT JOIN {$users_table} u ON a.user_id = u.ID
			{$where_sql}
			ORDER BY a.created_at DESC
			LIMIT " . absint( $per_page ) . ' OFFSET ' . absint( $offset );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where_sql is empty or wpdb-prepared fragment; limits are absint.
		$agents = $wpdb->get_results( $sql_agents );

		$agents_form_action = admin_url( 'admin.php' );
		?>
		<div class="amg-toolbar">
			<form method="get" action="<?php echo esc_url( $agents_form_action ); ?>" class="amg-search-row">
				<input type="hidden" name="page" value="agent-management" />
				<label class="amg-sr-only" for="amg-agents-search"><?php esc_html_e( 'Search agents', 'agent-management' ); ?></label>
				<div class="amg-search-input-wrap">
					<input type="search" id="amg-agents-search" name="agents_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search agents: company, username, email, phone, license, address…', 'agent-management' ); ?>" autocomplete="off" />
				</div>
				<button type="submit" class="amg-btn amg-btn-primary"><?php esc_html_e( 'Search', 'agent-management' ); ?></button>
			</form>
		</div>

		<div class="amg-table-card">
		<table class="amg-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Company', 'agent-management' ); ?></th>
					<th><?php esc_html_e( 'Username', 'agent-management' ); ?></th>
					<th><?php esc_html_e( 'Password', 'agent-management' ); ?></th>
					<th><?php esc_html_e( 'Email', 'agent-management' ); ?></th>
					<th><?php esc_html_e( 'Phone', 'agent-management' ); ?></th>
					<th><?php esc_html_e( 'License No.', 'agent-management' ); ?></th>
					<th><?php esc_html_e( 'Status', 'agent-management' ); ?></th>
					<th><?php esc_html_e( 'Registration Date', 'agent-management' ); ?></th>
					<th><?php esc_html_e( 'Customers', 'agent-management' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'agent-management' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $agents ) ) : ?>
				<tr><td colspan="10" style="text-align: center;"><?php esc_html_e( 'No agents found.', 'agent-management' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $agents as $agent ) : ?>
					<tr>
						<td><?php echo esc_html( $agent->company_name ); ?></td>
						<td><?php echo esc_html( isset( $agent->username ) && '' !== trim( (string) $agent->username ) ? $agent->username : $agent->user_login ); ?></td>
						<td>
							<?php
							if ( $agent->password ) {
                                echo esc_html( $agent->password );
                            } else {
								echo '<span aria-hidden="true">—</span>';
							}
							?>
						</td>
						<td><?php echo esc_html( $agent->user_email ); ?></td>
						<td><?php echo esc_html( $agent->phone ); ?></td>
						<td><?php echo esc_html( $agent->license_number ); ?></td>
						<td><span class="status-<?php echo esc_attr( $agent->status ); ?>"><?php echo esc_html( $agent->status ); ?></span></td>
						<td><?php echo esc_html( $agent->created_at ); ?></td>
						<td>
							<button type="button" class="amg-btn amg-btn-soft amg-btn-customers" data-agent-id="<?php echo esc_attr( (string) $agent->id ); ?>" data-company="<?php echo esc_attr( $agent->company_name ); ?>">
								<?php esc_html_e( 'View customers', 'agent-management' ); ?>
							</button>
						</td>
						<td class="amg-row-actions">
							<div class="amg-row-actions-btns">
								<button type="button" class="amg-btn amg-btn-soft amg-edit-agent" data-agent-id="<?php echo (int) $agent->id; ?>">
									<?php esc_html_e( 'Edit', 'agent-management' ); ?>
								</button>
								<?php
								$agent_wp_user_id = isset( $agent->user_id ) ? (int) $agent->user_id : 0;
								$wp_user_edit_url = $agent_wp_user_id ? get_edit_user_link( $agent_wp_user_id ) : '';
								if ( $wp_user_edit_url ) :
									?>
									<a href="<?php echo esc_url( $wp_user_edit_url ); ?>" class="amg-btn amg-btn-soft">
										<?php esc_html_e( 'Edit WP user', 'agent-management' ); ?>
									</a>
								<?php endif; ?>
								<button type="button" class="amg-btn amg-btn-danger amg-delete-agent" data-agent-id="<?php echo (int) $agent->id; ?>" data-company="<?php echo esc_attr( $agent->company_name ); ?>">
									<?php esc_html_e( 'Delete', 'agent-management' ); ?>
								</button>
							</div>
							<select class="amg-select" onchange="updateAgentStatus(<?php echo (int) $agent->id; ?>, this.value)">
								<option value="pending" <?php selected( $agent->status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'agent-management' ); ?></option>
								<option value="approved" <?php selected( $agent->status, 'approved' ); ?>><?php esc_html_e( 'Approve', 'agent-management' ); ?></option>
								<option value="rejected" <?php selected( $agent->status, 'rejected' ); ?>><?php esc_html_e( 'Reject', 'agent-management' ); ?></option>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		</div>

		<?php
		$this->display_pagination( $current_page, $total_pages, 'agents_page', $this->agents_pagination_args() );
		?>
		<script>
			function updateAgentStatus(agentId, status) {
				jQuery('.agent-status-update').show();
				jQuery.post(ajaxurl, {
					action: 'update_agent_status',
					agent_id: agentId,
					status: status,
					nonce: '<?php echo esc_js( wp_create_nonce( 'update_agent_status' ) ); ?>'
				}, function(response) {
					if (response.success) {
						alert('Status updated successfully');
						location.reload();
						jQuery('.agent-status-update').hide();
					} else {
						alert('Error updating status');
						jQuery('.agent-status-update').hide();
					}
				});
			}
		</script>
		<?php
	}

	private function display_customers_table() {
		global $wpdb;
		$customers_table = $wpdb->prefix . 'agent_customers';
		$agents_table    = $wpdb->prefix . 'agents';
		$users_table     = $wpdb->prefix . 'users';

		$per_page     = self::LIST_PER_PAGE;
		$current_page = isset( $_GET['customers_page'] ) ? max( 1, intval( $_GET['customers_page'] ) ) : 1;
		$offset       = ( $current_page - 1 ) * $per_page;
		$search       = isset( $_GET['customers_search'] ) ? sanitize_text_field( wp_unslash( $_GET['customers_search'] ) ) : '';
		$search_like  = '%' . $wpdb->esc_like( $search ) . '%';

		$where_sql = '';
		if ( $search !== '' ) {
			$where_sql = $wpdb->prepare(
				' WHERE ( c.customer_name LIKE %s OR c.customer_phone LIKE %s OR c.passport_number LIKE %s OR c.visa_country LIKE %s OR c.visa_type LIKE %s OR CAST(IFNULL(c.total_amount,0) AS CHAR) LIKE %s OR CAST(IFNULL(c.deposit_amount,0) AS CHAR) LIKE %s ) ',
				$search_like,
				$search_like,
				$search_like,
				$search_like,
				$search_like,
				$search_like,
				$search_like
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where_sql prepared or empty.
		$total_customers = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$customers_table} c {$where_sql}" );
		$total_pages     = max( 1, (int) ceil( $total_customers / $per_page ) );

		$sql_customers = "SELECT c.*, a.company_name, u.user_email
			FROM {$customers_table} c
			LEFT JOIN {$agents_table} a ON c.agent_id = a.id
			LEFT JOIN {$users_table} u ON a.user_id = u.ID
			{$where_sql}
			ORDER BY c.created_at DESC
			LIMIT " . absint( $per_page ) . ' OFFSET ' . absint( $offset );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where_sql safe; limits absint.
		$customers = $wpdb->get_results( $sql_customers );

		$cids    = wp_list_pluck( $customers, 'id' );
		$img_map = $this->get_additional_images_map( $cids );

		$customers_form_action = admin_url( 'admin.php' );
		?>
		<div class="amg-toolbar">
			<form method="get" action="<?php echo esc_url( $customers_form_action ); ?>" class="amg-search-row">
				<input type="hidden" name="page" value="agent-management" />
				<label class="amg-sr-only" for="amg-customers-search"><?php esc_html_e( 'Search customers', 'agent-management' ); ?></label>
				<div class="amg-search-input-wrap">
					<input type="search" id="amg-customers-search" name="customers_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search customers: name, phone, passport, country, visa type, amounts…', 'agent-management' ); ?>" autocomplete="off" />
				</div>
				<button type="submit" class="amg-btn amg-btn-primary"><?php esc_html_e( 'Search', 'agent-management' ); ?></button>
			</form>
		</div>

		<div class="amg-table-card">
		<table class="amg-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Customer Name', 'agent-management' ); ?></th>
					<th><?php esc_html_e( 'Phone', 'agent-management' ); ?></th>
					<th><?php esc_html_e( 'Passport No.', 'agent-management' ); ?></th>
					<th><?php esc_html_e( 'Visa Country', 'agent-management' ); ?></th>
				<th><?php esc_html_e( 'Agent', 'agent-management' ); ?></th>
				<th><?php esc_html_e( 'Images', 'agent-management' ); ?></th>
				<th><?php esc_html_e( 'Submission Date', 'agent-management' ); ?></th>
				<th><?php esc_html_e( 'Total amount', 'agent-management' ); ?></th>
				<th><?php esc_html_e( 'Deposit amount', 'agent-management' ); ?></th>
				<th><?php esc_html_e( 'Status', 'agent-management' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'agent-management' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php if ( empty( $customers ) ) : ?>
				<tr><td colspan="11" style="text-align: center;"><?php esc_html_e( 'No customers found.', 'agent-management' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $customers as $customer ) : ?>
					<?php
					$extras = isset( $img_map[ (int) $customer->id ] ) ? $img_map[ (int) $customer->id ] : array();
					?>
					<tr>
						<td><?php echo esc_html( $customer->customer_name ); ?></td>
						<td><?php echo esc_html( $customer->customer_phone ); ?></td>
						<td><?php echo esc_html( $customer->passport_number ); ?></td>
						<td><?php echo esc_html( $customer->visa_country ); ?></td>
						<td><?php echo esc_html( $customer->company_name ); ?></td>
						<td><?php echo $this->render_customer_images_cell( $customer->passport_image, $extras ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td><?php echo esc_html( $customer->submission_date ); ?></td>
						<td><?php echo $this->format_customer_amount_cell( isset( $customer->total_amount ) ? $customer->total_amount : null ); ?></td>
						<td><?php echo $this->format_customer_amount_cell( isset( $customer->deposit_amount ) ? $customer->deposit_amount : null ); ?></td>
						<td><span class="status-<?php echo esc_attr( $customer->status ); ?>"><?php echo esc_html( $customer->status ); ?></span></td>
						<td class="amg-row-actions">
							<div class="amg-row-actions-btns">
								<button type="button" class="amg-btn amg-btn-soft amg-edit-customer" data-customer-id="<?php echo (int) $customer->id; ?>">
									<?php esc_html_e( 'Edit', 'agent-management' ); ?>
								</button>
								<button type="button" class="amg-btn amg-btn-danger amg-delete-customer" data-customer-id="<?php echo (int) $customer->id; ?>" data-customer-name="<?php echo esc_attr( $customer->customer_name ); ?>">
									<?php esc_html_e( 'Delete', 'agent-management' ); ?>
								</button>
							</div>
							<select class="amg-select" onchange="updateCustomerStatus(<?php echo (int) $customer->id; ?>, this.value)">
								<option value="pending" <?php selected( $customer->status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'agent-management' ); ?></option>
								<option value="approved" <?php selected( $customer->status, 'approved' ); ?>><?php esc_html_e( 'Approve', 'agent-management' ); ?></option>
								<option value="rejected" <?php selected( $customer->status, 'rejected' ); ?>><?php esc_html_e( 'Reject', 'agent-management' ); ?></option>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		</div>

		<?php
		$this->display_pagination( $current_page, $total_pages, 'customers_page', $this->customers_pagination_args() );
		?>
		<script>
			function updateCustomerStatus(customerId, status) {
				jQuery('.customer-status-update').show();
				jQuery.post(ajaxurl, {
					action: 'update_customer_status',
					customer_id: customerId,
					status: status,
					nonce: '<?php echo esc_js( wp_create_nonce( 'update_customer_status' ) ); ?>'
				}, function(response) {
					if (response.success) {
						alert('Status updated successfully');
						location.reload();
						jQuery('.customer-status-update').hide();
					} else {
						alert('Error updating status');
						jQuery('.customer-status-update').hide();
					}
				});
			}
		</script>
		<?php
	}

	/**
	 * Pagination links preserving admin page and optional search query args.
	 *
	 * @param int    $current_page Current page number.
	 * @param int    $total_pages  Total pages.
	 * @param string $page_param   Query key for this list’s page index.
	 * @param array  $extra_args   e.g. array( 'agents_search' => 'foo' ).
	 */
	private function display_pagination( $current_page, $total_pages, $page_param, array $extra_args = array() ) {
		if ( $total_pages <= 1 ) {
			return;
		}

		$base = array_merge(
			array( 'page' => 'agent-management' ),
			$extra_args
		);
		$pagination_base = admin_url( 'admin.php' );

		echo '<div class="amg-pagination">';

		if ( $current_page > 1 ) {
			echo '<a class="amg-pagination-link" href="' . esc_url( add_query_arg( array_merge( $base, array( $page_param => $current_page - 1 ) ), $pagination_base ) ) . '">&laquo; ' . esc_html__( 'Previous', 'agent-management' ) . '</a>';
		}

		for ( $i = 1; $i <= $total_pages; $i++ ) {
			if ( $i === $current_page ) {
				echo '<span class="amg-pagination-current">' . (int) $i . '</span>';
			} else {
				echo '<a class="amg-pagination-link" href="' . esc_url( add_query_arg( array_merge( $base, array( $page_param => $i ) ), $pagination_base ) ) . '">' . (int) $i . '</a>';
			}
		}

		if ( $current_page < $total_pages ) {
			echo '<a class="amg-pagination-link" href="' . esc_url( add_query_arg( array_merge( $base, array( $page_param => $current_page + 1 ) ), $pagination_base ) ) . '">' . esc_html__( 'Next', 'agent-management' ) . ' &raquo;</a>';
		}

		echo '</div>';
	}

	public function ajax_get_agent_customers() {
		check_ajax_referer( 'agent_management_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Access denied.', 'agent-management' ) ), 403 );
		}

		$agent_id = isset( $_POST['agent_id'] ) ? intval( $_POST['agent_id'] ) : 0;
		if ( $agent_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid agent.', 'agent-management' ) ) );
		}

		global $wpdb;
		$agents_table    = $wpdb->prefix . 'agents';
		$customers_table = $wpdb->prefix . 'agent_customers';

		$agent = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, company_name FROM {$agents_table} WHERE id = %d", $agent_id )
		);
		if ( ! $agent ) {
			wp_send_json_error( array( 'message' => __( 'Agent not found.', 'agent-management' ) ) );
		}

		$customers = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$customers_table} WHERE agent_id = %d ORDER BY created_at DESC",
				$agent_id
			)
		);

		$cids    = wp_list_pluck( $customers, 'id' );
		$img_map = $this->get_additional_images_map( $cids );

		$html = $this->render_agent_modal_customers_frontend_layout( $customers, $img_map );

		wp_send_json_success( array( 'html' => $html ) );
	}

	public function update_agent_status() {
		check_ajax_referer( 'update_agent_status', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Access denied' );
		}

		global $wpdb;
		$agents_table = $wpdb->prefix . 'agents';

		$wpdb->update(
			$agents_table,
			array( 'status' => sanitize_text_field( wp_unslash( $_POST['status'] ) ) ),
			array( 'id' => intval( $_POST['agent_id'] ) )
		);

		wp_send_json_success();
	}

	public function update_customer_status() {
		check_ajax_referer( 'update_customer_status', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Access denied' );
		}

		global $wpdb;
		$customers_table = $wpdb->prefix . 'agent_customers';

		$wpdb->update(
			$customers_table,
			array( 'status' => sanitize_text_field( wp_unslash( $_POST['status'] ) ) ),
			array( 'id' => intval( $_POST['customer_id'] ) )
		);

		wp_send_json_success();
	}

	/**
	 * AJAX: agent row for admin edit form.
	 */
	public function ajax_admin_get_agent() {
		$this->admin_ajax_verify();

		$agent_id = isset( $_POST['agent_id'] ) ? intval( $_POST['agent_id'] ) : 0;
		if ( $agent_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid agent.', 'agent-management' ) ) );
		}

		global $wpdb;
		$agents_table = $wpdb->prefix . 'agents';
		$users_table  = $wpdb->prefix . 'users';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT a.id, a.user_id, a.username, a.company_name, a.phone, a.address, a.license_number, a.status, a.created_at, u.user_email
				FROM {$agents_table} a
				LEFT JOIN {$users_table} u ON a.user_id = u.ID
				WHERE a.id = %d",
				$agent_id
			)
		);

		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'Agent not found.', 'agent-management' ) ) );
		}

		wp_send_json_success( $row );
	}

	/**
	 * AJAX: save agent profile fields + status from admin.
	 */
	public function ajax_admin_save_agent() {
		$this->admin_ajax_verify();

		$agent_id = isset( $_POST['agent_id'] ) ? intval( $_POST['agent_id'] ) : 0;
		if ( $agent_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid agent.', 'agent-management' ) ) );
		}

		$company = isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : '';
		$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$address = isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '';
		$license = isset( $_POST['license_number'] ) ? sanitize_text_field( wp_unslash( $_POST['license_number'] ) ) : '';
		$status  = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'pending';

		if ( '' === $company || '' === $phone || '' === $address || '' === $license ) {
			wp_send_json_error( array( 'message' => __( 'Please fill all required fields.', 'agent-management' ) ) );
		}

		$allowed_status = array( 'pending', 'approved', 'rejected' );
		if ( ! in_array( $status, $allowed_status, true ) ) {
			$status = 'pending';
		}

		global $wpdb;
		$agents_table = $wpdb->prefix . 'agents';

		$updated = $wpdb->update(
			$agents_table,
			array(
				'company_name'   => $company,
				'phone'          => $phone,
				'address'        => $address,
				'license_number' => $license,
				'status'         => $status,
			),
			array( 'id' => $agent_id )
		);

		if ( false === $updated ) {
			wp_send_json_error( array( 'message' => __( 'Could not save agent.', 'agent-management' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Agent saved.', 'agent-management' ) ) );
	}

	/**
	 * AJAX: delete agent, their customers, image rows, and WP user.
	 */
	public function ajax_admin_delete_agent() {
		$this->admin_ajax_verify();

		$agent_id = isset( $_POST['agent_id'] ) ? intval( $_POST['agent_id'] ) : 0;
		if ( $agent_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid agent.', 'agent-management' ) ) );
		}

		global $wpdb;
		$agents_table    = $wpdb->prefix . 'agents';
		$customers_table = $wpdb->prefix . 'agent_customers';
		$images_table    = $wpdb->prefix . 'agent_customer_images';

		$agent = $wpdb->get_row( $wpdb->prepare( "SELECT id, user_id FROM {$agents_table} WHERE id = %d", $agent_id ) );
		if ( ! $agent ) {
			wp_send_json_error( array( 'message' => __( 'Agent not found.', 'agent-management' ) ) );
		}

		$user_id = (int) $agent->user_id;
		if ( $user_id === get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'You cannot delete your own user account from here.', 'agent-management' ) ) );
		}

		$customer_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$customers_table} WHERE agent_id = %d", $agent_id ) );
		foreach ( $customer_ids as $cid ) {
			$wpdb->delete( $images_table, array( 'customer_id' => (int) $cid ), array( '%d' ) );
		}
		$wpdb->delete( $customers_table, array( 'agent_id' => $agent_id ), array( '%d' ) );
		$wpdb->delete( $agents_table, array( 'id' => $agent_id ), array( '%d' ) );

		if ( $user_id > 0 ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			if ( is_multisite() ) {
				require_once ABSPATH . 'wp-admin/includes/ms.php';
			}
			if ( is_multisite() && function_exists( 'wpmu_delete_user' ) ) {
				wpmu_delete_user( $user_id );
			} else {
				wp_delete_user( $user_id );
			}
		}

		wp_send_json_success( array( 'message' => __( 'Agent deleted.', 'agent-management' ) ) );
	}

	/**
	 * AJAX: customer row for admin edit (includes amounts).
	 */
	public function ajax_admin_get_customer() {
		$this->admin_ajax_verify();

		$customer_id = isset( $_POST['customer_id'] ) ? intval( $_POST['customer_id'] ) : 0;
		if ( $customer_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid customer.', 'agent-management' ) ) );
		}

		global $wpdb;
		$customers_table = $wpdb->prefix . 'agent_customers';
		$agents_table    = $wpdb->prefix . 'agents';
		$users_table     = $wpdb->prefix . 'users';
		$images_table    = $wpdb->prefix . 'agent_customer_images';

		$customer = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT c.*, a.company_name, u.user_email AS agent_email
				FROM {$customers_table} c
				LEFT JOIN {$agents_table} a ON c.agent_id = a.id
				LEFT JOIN {$users_table} u ON a.user_id = u.ID
				WHERE c.id = %d",
				$customer_id
			)
		);

		if ( ! $customer ) {
			wp_send_json_error( array( 'message' => __( 'Customer not found.', 'agent-management' ) ) );
		}

		$customer->additional_images = $wpdb->get_results(
			$wpdb->prepare( "SELECT id, image_url FROM {$images_table} WHERE customer_id = %d ORDER BY id ASC", $customer_id )
		);

		wp_send_json_success( $customer );
	}

	/**
	 * AJAX: save customer from admin (including total and deposit amounts).
	 */
	public function ajax_admin_save_customer() {
		$this->admin_ajax_verify();

		$customer_id = isset( $_POST['customer_id'] ) ? intval( $_POST['customer_id'] ) : 0;
		if ( $customer_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid customer.', 'agent-management' ) ) );
		}

		$required = array( 'customer_name', 'customer_phone', 'passport_number', 'visa_country', 'visa_type', 'submission_date' );
		foreach ( $required as $key ) {
			if ( empty( $_POST[ $key ] ) ) {
				wp_send_json_error( array( 'message' => __( 'Please fill all required fields.', 'agent-management' ) ) );
			}
		}

		$total_amt = $this->parse_amount_post_field( 'total_amount', 'total amount' );
		if ( ! $total_amt['ok'] ) {
			wp_send_json_error( array( 'message' => $total_amt['error'] ) );
		}
		$deposit_amt = $this->parse_amount_post_field( 'deposit_amount', 'deposit amount' );
		if ( ! $deposit_amt['ok'] ) {
			wp_send_json_error( array( 'message' => $deposit_amt['error'] ) );
		}

		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'pending';
		$allowed_status = array( 'pending', 'approved', 'rejected' );
		if ( ! in_array( $status, $allowed_status, true ) ) {
			$status = 'pending';
		}

		global $wpdb;
		$customers_table = $wpdb->prefix . 'agent_customers';

		$visa_type = sanitize_text_field( wp_unslash( $_POST['visa_type'] ) );
		$visa_allowed = array( 'tourist', 'student', 'work', 'business' );
		if ( ! in_array( $visa_type, $visa_allowed, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid visa type.', 'agent-management' ) ) );
		}

		$visa_country = sanitize_text_field( wp_unslash( $_POST['visa_country'] ) );
		$countries    = AgentDashboard::get_countries_list();
		$existing_row = $wpdb->get_row( $wpdb->prepare( "SELECT visa_country FROM {$customers_table} WHERE id = %d", $customer_id ) );
		$legacy_ok    = $existing_row && $visa_country === $existing_row->visa_country;
		if ( ! in_array( $visa_country, $countries, true ) && ! $legacy_ok ) {
			wp_send_json_error( array( 'message' => __( 'Invalid visa country.', 'agent-management' ) ) );
		}

		$data = array(
			'customer_name'    => sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ),
			'customer_phone'   => sanitize_text_field( wp_unslash( $_POST['customer_phone'] ) ),
			'passport_number'  => sanitize_text_field( wp_unslash( $_POST['passport_number'] ) ),
			'visa_country'     => $visa_country,
			'visa_type'        => $visa_type,
			'submission_date'  => sanitize_text_field( wp_unslash( $_POST['submission_date'] ) ),
			'total_amount'     => $total_amt['value'],
			'deposit_amount'   => $deposit_amt['value'],
			'status'           => $status,
		);

		$updated = $wpdb->update(
			$customers_table,
			$data,
			array( 'id' => $customer_id )
		);

		if ( false === $updated ) {
			wp_send_json_error( array( 'message' => __( 'Could not save customer.', 'agent-management' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Customer saved.', 'agent-management' ) ) );
	}

	/**
	 * AJAX: delete a single customer (admin).
	 */
	public function ajax_admin_delete_customer() {
		$this->admin_ajax_verify();

		$customer_id = isset( $_POST['customer_id'] ) ? intval( $_POST['customer_id'] ) : 0;
		if ( $customer_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid customer.', 'agent-management' ) ) );
		}

		global $wpdb;
		$customers_table = $wpdb->prefix . 'agent_customers';
		$images_table    = $wpdb->prefix . 'agent_customer_images';

		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$customers_table} WHERE id = %d", $customer_id ) );
		if ( ! $exists ) {
			wp_send_json_error( array( 'message' => __( 'Customer not found.', 'agent-management' ) ) );
		}

		$wpdb->delete( $images_table, array( 'customer_id' => $customer_id ), array( '%d' ) );
		$wpdb->delete( $customers_table, array( 'id' => $customer_id ), array( '%d' ) );

		wp_send_json_success( array( 'message' => __( 'Customer deleted.', 'agent-management' ) ) );
	}
}
