<?php
/**
 * Settings > LLMs Helper page.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Controllers;

use PinkCrab\LLMs_Helper\Cron\Scheduler;
use PinkCrab\LLMs_Helper\Hooks\Hookable;
use PinkCrab\LLMs_Helper\Hooks\Hook_Loader;
use PinkCrab\LLMs_Helper\Interfaces\Url_Map;
use PinkCrab\LLMs_Helper\Content\Copy_Builder;
use PinkCrab\LLMs_Helper\Settings\Option_Settings;

/**
 * Edits the llms_helper_settings option and queues builds.
 */
class Settings_Page implements Hookable {

	/**
	 * Page slug and settings group.
	 *
	 * @var string
	 */
	public const PAGE = 'llms-helper';

	/**
	 * Action name for the build button.
	 *
	 * @var string
	 */
	public const BUILD_ACTION = 'llms_helper_build';

	/**
	 * Set the dependencies.
	 *
	 * @param Option_Settings $settings  Settings stored in the option this page edits.
	 * @param Scheduler       $scheduler Background jobs.
	 * @param Url_Map         $urls      URL mapping.
	 */
	public function __construct(
		protected Option_Settings $settings,
		protected Scheduler $scheduler,
		protected Url_Map $urls
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register( Hook_Loader $loader ): void {
		$loader->action( 'admin_menu', array( $this, 'add_page' ) );
		$loader->action( 'admin_init', array( $this, 'register_setting' ) );
		$loader->action( 'admin_post_' . self::BUILD_ACTION, array( $this, 'handle_build' ) );

		$loader->action( 'add_option_' . Option_Settings::OPTION, array( $this, 'settings_added' ), 10, 2 );
		$loader->action( 'update_option_' . Option_Settings::OPTION, array( $this, 'settings_changed' ), 10, 2 );
	}

	/**
	 * Treat the first save as a change from the defaults.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Saved value.
	 * @return void
	 */
	public function settings_added( string $option, $value ): void {
		$this->settings_changed( Option_Settings::defaults(), $value );
	}

	/**
	 * Queue only what the change needs: a build when the post types change, llms.txt when its text or redirect mode change.
	 *
	 * @param mixed $old_value Previous value.
	 * @param mixed $value     New value.
	 * @return void
	 */
	public function settings_changed( $old_value, $value ): void {
		$old = Option_Settings::parse( $old_value );
		$new = Option_Settings::parse( $value );

		if ( array() !== array_diff( $old['post_types'], $new['post_types'] ) || array() !== array_diff( $new['post_types'], $old['post_types'] ) ) {
			$this->scheduler->queue_build();
			return;
		}

		// missing_copy and index_llms_txt are read per request, so they need nothing built.
		unset( $old['missing_copy'], $new['missing_copy'], $old['index_llms_txt'], $new['index_llms_txt'] );
		if ( $old !== $new ) {
			$this->scheduler->queue_index();
		}
	}

	/**
	 * Add the page under Settings.
	 *
	 * @return void
	 */
	public function add_page(): void {
		add_options_page(
			__( 'LLMs Helper', 'llms-helper' ),
			__( 'LLMs Helper', 'llms-helper' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Register the option.
	 *
	 * @return void
	 */
	public function register_setting(): void {
		register_setting(
			self::PAGE,
			Option_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => Option_Settings::defaults(),
			)
		);
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * @param mixed $input Submitted value.
	 * @return array{post_types: list<string>, redirect: bool, summary: string, notes: string, missing_copy: string, index_llms_txt: bool}
	 */
	public function sanitize( $input ): array {
		$input   = is_array( $input ) ? $input : array();
		$types   = array_map( 'sanitize_key', array_map( 'strval', (array) ( $input['post_types'] ?? array() ) ) );
		$missing = sanitize_key( (string) ( $input['missing_copy'] ?? '' ) );

		return array(
			'post_types'     => array_values( array_intersect( $types, array_keys( $this->available_post_types() ) ) ),
			'redirect'       => ! empty( $input['redirect'] ),
			'summary'        => sanitize_text_field( (string) ( $input['summary'] ?? '' ) ),
			'notes'          => sanitize_textarea_field( (string) ( $input['notes'] ?? '' ) ),
			'missing_copy'   => in_array( $missing, Option_Settings::MISSING_COPY, true ) ? $missing : Option_Settings::defaults()['missing_copy'],
			'index_llms_txt' => ! empty( $input['index_llms_txt'] ),
		);
	}

	/**
	 * Public post types that can have copies, keyed by name.
	 *
	 * @return array<string, \WP_Post_Type>
	 */
	public function available_post_types(): array {
		$types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $types['attachment'] );

		return $types;
	}

	/**
	 * Queue a build from the button.
	 *
	 * @return void
	 */
	public function handle_build(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'llms-helper' ) );
		}
		check_admin_referer( self::BUILD_ACTION );

		$this->scheduler->queue_build();

		wp_safe_redirect( add_query_arg( 'llms_helper_build', '1', admin_url( 'options-general.php?page=' . self::PAGE ) ) );
		exit;
	}

	/**
	 * Number of copies built.
	 *
	 * @return int
	 */
	public function copy_count(): int {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", Copy_Builder::META_FILE ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings  = $this->settings->all();
		$name      = Option_Settings::OPTION;
		$index_url = $this->urls->url_for_path( $this->urls->index_path() );
		$missing   = array(
			'page' => __( 'Show the page the URL belongs to', 'llms-helper' ),
			'404'  => __( 'Plain 404 Not Found', 'llms-helper' ),
			'410'  => __( 'Plain 410 Gone', 'llms-helper' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'LLMs Helper', 'llms-helper' ); ?></h1>

			<?php if ( isset( $_GET['llms_helper_build'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Build queued. It runs in the background.', 'llms-helper' ); ?></p></div>
			<?php endif; ?>

			<?php if ( $this->scheduler->overdue() ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php
						/* translators: %d: minutes. */
						echo esc_html( sprintf( __( 'Background jobs are more than %d minutes overdue, so WP-Cron is not running them and copies and llms.txt are not being built. If DISABLE_WP_CRON is set, run WP-Cron from a server cron job.', 'llms-helper' ), (int) ( Scheduler::OVERDUE_AFTER / 60 ) ) );
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php $failed_after = $this->scheduler->failed_after(); ?>
			<?php if ( null !== $failed_after ) : ?>
				<div class="notice notice-error">
					<p>
						<?php
						/* translators: 1: post ID, 2: number of runs. */
						echo esc_html( sprintf( __( 'The last build stopped after post %1$d, as the next batch did not finish in %2$d runs. Build copies starts it again.', 'llms-helper' ), $failed_after, Scheduler::MAX_ATTEMPTS ) );
						?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Post types', 'llms-helper' ); ?></th>
						<td>
							<?php foreach ( $this->available_post_types() as $type => $object ) : ?>
								<label style="display:block">
									<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[post_types][]" value="<?php echo esc_attr( $type ); ?>" <?php checked( in_array( $type, $settings['post_types'], true ) ); ?>>
									<?php echo esc_html( sprintf( '%s (%s)', (string) $object->labels->name, $type ) ); ?>
								</label>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'Published posts of these types get a Markdown copy and are listed in llms.txt. Single posts can be excluded from the editor.', 'llms-helper' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Redirect mode', 'llms-helper' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[redirect]" value="1" <?php checked( $settings['redirect'] ); ?>>
								<?php esc_html_e( 'Send a 301 from llms.txt and the copy URLs to the files where they are stored', 'llms-helper' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Use this where the site root cannot be written to. When off, copies are sent by PHP at their own URL and llms.txt is written to the site root.', 'llms-helper' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="llms-helper-missing-copy"><?php esc_html_e( 'Copy URL with no copy', 'llms-helper' ); ?></label></th>
						<td>
							<select id="llms-helper-missing-copy" name="<?php echo esc_attr( $name ); ?>[missing_copy]">
								<?php foreach ( $missing as $value => $label ) : ?>
									<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $settings['missing_copy'], (string) $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Search engines', 'llms-helper' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[index_llms_txt]" value="1" <?php checked( $settings['index_llms_txt'] ); ?>>
								<?php esc_html_e( 'Let search engines index llms.txt', 'llms-helper' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Lists llms.txt in the Yoast SEO or Rank Math sitemap. When off, llms.txt sent by PHP asks not to be indexed.', 'llms-helper' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="llms-helper-summary"><?php esc_html_e( 'llms.txt summary', 'llms-helper' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="llms-helper-summary" name="<?php echo esc_attr( $name ); ?>[summary]" value="<?php echo esc_attr( $settings['summary'] ); ?>" placeholder="<?php echo esc_attr( (string) get_bloginfo( 'description' ) ); ?>">
							<p class="description"><?php esc_html_e( 'The blockquote under the site name. Empty uses the home page description from Yoast or Rank Math, then the tagline.', 'llms-helper' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="llms-helper-notes"><?php esc_html_e( 'llms.txt notes', 'llms-helper' ); ?></label></th>
						<td>
							<textarea class="large-text" rows="6" id="llms-helper-notes" name="<?php echo esc_attr( $name ); ?>[notes]"><?php echo esc_textarea( $settings['notes'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Markdown placed after the summary. The spec does not allow headings here.', 'llms-helper' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Copies', 'llms-helper' ); ?></h2>
			<p>
				<?php
				/* translators: %d: number of Markdown copies. */
				echo esc_html( sprintf( __( 'Markdown copies built: %d', 'llms-helper' ), $this->copy_count() ) );
				?>
				<br>
				<a href="<?php echo esc_url( $index_url ); ?>" target="_blank"><?php echo esc_html( $index_url ); ?></a>
				<?php if ( $this->scheduler->build_running() ) : ?>
					<br><strong><?php esc_html_e( 'A build is queued.', 'llms-helper' ); ?></strong>
				<?php endif; ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::BUILD_ACTION ); ?>">
				<?php wp_nonce_field( self::BUILD_ACTION ); ?>
				<?php submit_button( __( 'Build copies', 'llms-helper' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}
}
