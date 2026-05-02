<?php
/**
 * Admin-side: meta box on the post editor that renders a copyable iframe
 * snippet with size presets.
 *
 * @package TikSwipe_Embed
 */

defined( 'ABSPATH' ) || exit;

class TSE_Admin {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_box' ) );
	}

	public static function register_meta_box() {
		add_meta_box(
			'tse_embed_code',
			__( 'TikSwipe Embed', 'tikswipe-embed' ),
			array( __CLASS__, 'render_meta_box' ),
			'post',
			'side',
			'default'
		);
	}

	public static function render_meta_box( $post ) {
		if ( ! tse_post_supports_embed( $post ) ) {
			echo '<p>' . esc_html__( 'Set the post format to Video or Image to enable embed.', 'tikswipe-embed' ) . '</p>';
			return;
		}
		if ( 'publish' !== get_post_status( $post ) ) {
			echo '<p>' . esc_html__( 'Publish the post to get a working embed code.', 'tikswipe-embed' ) . '</p>';
			return;
		}

		$embed_url = tse_get_embed_url( $post );
		?>
		<div class="tse-meta-box">
			<p>
				<label for="tse-size"><strong><?php esc_html_e( 'Size', 'tikswipe-embed' ); ?></strong></label>
				<select id="tse-size" style="width:100%;">
					<option value="360x640">360 &times; 640 (vertical 9:16)</option>
					<option value="320x568">320 &times; 568 (vertical small)</option>
					<option value="405x720">405 &times; 720 (vertical large)</option>
					<option value="400x400">400 &times; 400 (square 1:1)</option>
					<option value="560x315">560 &times; 315 (horizontal 16:9)</option>
				</select>
			</p>

			<p><label for="tse-embed-code"><strong><?php esc_html_e( 'Embed code', 'tikswipe-embed' ); ?></strong></label></p>
			<textarea
				id="tse-embed-code"
				rows="5"
				readonly
				style="width:100%;font-family:Menlo,Monaco,Consolas,monospace;font-size:11px;"
				data-embed-url="<?php echo esc_attr( $embed_url ); ?>"
				data-title="<?php echo esc_attr( get_the_title( $post ) ); ?>"
			></textarea>

			<p>
				<button type="button" class="button button-secondary" id="tse-copy-btn">
					<?php esc_html_e( 'Copy code', 'tikswipe-embed' ); ?>
				</button>
				<a href="<?php echo esc_url( $embed_url ); ?>" target="_blank" rel="noopener" class="button">
					<?php esc_html_e( 'Preview', 'tikswipe-embed' ); ?>
				</a>
			</p>

			<p class="description" style="margin-top:8px;">
				<?php esc_html_e( 'Auto-embed (oEmbed) is also supported — pasting the post URL on Discord, Notion, Slack or WordPress will render the same card automatically.', 'tikswipe-embed' ); ?>
			</p>
		</div>

		<script>
		(function () {
			var sel = document.getElementById( 'tse-size' );
			var ta  = document.getElementById( 'tse-embed-code' );
			var btn = document.getElementById( 'tse-copy-btn' );
			if ( ! sel || ! ta || ! btn ) { return; }

			var url   = ta.dataset.embedUrl;
			var title = ( ta.dataset.title || '' ).replace( /"/g, '&quot;' );

			function update() {
				var parts = sel.value.split( 'x' );
				var w = parts[0] || 360;
				var h = parts[1] || 640;
				ta.value = '<iframe src="' + url + '" width="' + w + '" height="' + h + '" frameborder="0" scrolling="no" allowtransparency="true" allowfullscreen="true" title="' + title + '"></iframe>';
			}

			var copyLabel  = btn.textContent;
			var copiedText = '<?php echo esc_js( __( 'Copied!', 'tikswipe-embed' ) ); ?>';

			btn.addEventListener( 'click', function () {
				ta.select();
				ta.setSelectionRange( 0, 99999 );
				var done = function () {
					btn.textContent = copiedText;
					setTimeout( function () { btn.textContent = copyLabel; }, 2000 );
				};
				if ( navigator.clipboard && navigator.clipboard.writeText ) {
					navigator.clipboard.writeText( ta.value ).then( done, function () {
						try { document.execCommand( 'copy' ); done(); } catch ( e ) {}
					} );
				} else {
					try { document.execCommand( 'copy' ); done(); } catch ( e ) {}
				}
			} );

			sel.addEventListener( 'change', update );
			update();
		})();
		</script>
		<?php
	}
}
