<?php
/**
 * Per-post meta box.
 *
 * @author Glynn Quelch <glynn.quelch@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0-or-later
 * @package PinkCrab\LLMs_Helper
 * @since 0.1.0
 */

declare(strict_types=1);

namespace PinkCrab\LLMs_Helper\Controllers;

use WP_Post;
use PinkCrab\LLMs_Helper\Hooks\Hookable;
use PinkCrab\LLMs_Helper\Hooks\Hook_Loader;
use PinkCrab\LLMs_Helper\Interfaces\Url_Map;
use PinkCrab\LLMs_Helper\Interfaces\Settings;
use PinkCrab\LLMs_Helper\Content\Copy_Builder;
use PinkCrab\LLMs_Helper\Content\Markdown_Formatter;
use PinkCrab\LLMs_Helper\Content\Default_Inclusion_Policy;

/**
 * Exclude checkbox and a link to the copy, on included post types.
 */
class Meta_Box implements Hookable {

	/**
	 * Nonce action and field.
	 *
	 * @var string
	 */
	public const NONCE = 'llms_helper_meta_nonce';

	/**
	 * Checkbox field name.
	 *
	 * @var string
	 */
	public const FIELD = 'llms_helper_exclude';

	/**
	 * Markdown override field name.
	 *
	 * @var string
	 */
	public const MARKDOWN_FIELD = 'llms_helper_markdown';

	/**
	 * The admin-post action for the build button.
	 *
	 * @var string
	 */
	public const BUILD_ACTION = 'llms_helper_build_copy';

	/**
	 * Set the dependencies.
	 *
	 * @param Settings     $settings Settings.
	 * @param Url_Map      $urls     URL mapping.
	 * @param Copy_Builder $copies   Copy builder.
	 */
	public function __construct(
		protected Settings $settings,
		protected Url_Map $urls,
		protected Copy_Builder $copies
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function register( Hook_Loader $loader ): void {
		$loader->action( 'add_meta_boxes', array( $this, 'add' ) );
		$loader->action( 'save_post', array( $this, 'save' ) );
		$loader->action( 'admin_post_' . self::BUILD_ACTION, array( $this, 'handle_build' ) );
	}

	/**
	 * Build the post's copy from the button, then go back to the editor.
	 *
	 * @return void
	 */
	public function handle_build(): void {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- Checked next.
		check_admin_referer( self::BUILD_ACTION . '_' . $post_id );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'llms-helper' ) );
		}

		$this->copies->build( $post_id );

		wp_safe_redirect( add_query_arg( 'llms_helper_built', '1', (string) get_edit_post_link( $post_id, 'url' ) ) );
		exit;
	}

	/**
	 * URL of the build button for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public function build_url( int $post_id ): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::BUILD_ACTION . '&post=' . $post_id ), self::BUILD_ACTION . '_' . $post_id );
	}

	/**
	 * Add the box to included post types.
	 *
	 * @param string $post_type Post type being edited.
	 * @return void
	 */
	public function add( string $post_type ): void {
		if ( ! $this->settings->includes_type( $post_type ) ) {
			return;
		}

		add_meta_box( 'llms-helper', __( 'Markdown copy', 'llms-helper' ), array( $this, 'render' ), $post_type, 'side', 'low' );
	}

	/**
	 * Render the box.
	 *
	 * @param WP_Post $post Post being edited.
	 * @return void
	 */
	public function render( WP_Post $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE );
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::FIELD ); ?>" value="1" <?php checked( (bool) get_post_meta( $post->ID, Default_Inclusion_Policy::META_EXCLUDE, true ) ); ?>>
			<?php esc_html_e( 'Exclude from Markdown copies and llms.txt', 'llms-helper' ); ?>
		</label>
		<p>
			<label for="llms-helper-markdown"><?php esc_html_e( 'Markdown override', 'llms-helper' ); ?></label>
			<textarea class="widefat" rows="6" id="llms-helper-markdown" name="<?php echo esc_attr( self::MARKDOWN_FIELD ); ?>"><?php echo esc_textarea( (string) get_post_meta( $post->ID, Markdown_Formatter::META_MARKDOWN, true ) ); ?></textarea>
			<span class="description"><?php esc_html_e( 'Used for the copy in place of the post content. Leave empty to use the content.', 'llms-helper' ); ?></span>
		</p>
		<?php if ( '' !== $this->copies->copy_path( $post->ID ) ) : ?>
			<p><a href="<?php echo esc_url( $this->urls->copy_url( (string) get_permalink( $post ) ) ); ?>" target="_blank"><?php esc_html_e( 'View copy', 'llms-helper' ); ?></a></p>
		<?php endif; ?>
		<?php if ( 'publish' === $post->post_status ) : ?>
			<p><a href="<?php echo esc_url( $this->build_url( $post->ID ) ); ?>"><?php esc_html_e( 'Build copy now', 'llms-helper' ); ?></a></p>
		<?php endif; ?>
		<?php if ( isset( $_GET['llms_helper_built'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification -- Display only. ?>
			<p><strong><?php esc_html_e( 'Copy built.', 'llms-helper' ); ?></strong></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Save the checkbox. The copy is built or removed afterwards on wp_after_insert_post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save( int $post_id ): void {
		$nonce = isset( $_POST[ self::NONCE ] ) ? sanitize_key( wp_unslash( (string) $_POST[ self::NONCE ] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( empty( $_POST[ self::FIELD ] ) ) {
			delete_post_meta( $post_id, Default_Inclusion_Policy::META_EXCLUDE );
		} else {
			update_post_meta( $post_id, Default_Inclusion_Policy::META_EXCLUDE, 1 );
		}

		// Kept as typed for users trusted with raw HTML, filtered for the rest.
		$markdown = isset( $_POST[ self::MARKDOWN_FIELD ] ) ? trim( (string) wp_unslash( $_POST[ self::MARKDOWN_FIELD ] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitised next.
		$markdown = current_user_can( 'unfiltered_html' ) ? $markdown : wp_kses_post( $markdown );
		if ( '' === $markdown ) {
			delete_post_meta( $post_id, Markdown_Formatter::META_MARKDOWN );
		} else {
			update_post_meta( $post_id, Markdown_Formatter::META_MARKDOWN, wp_slash( $markdown ) );
		}
	}
}
