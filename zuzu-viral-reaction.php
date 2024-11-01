<?php
/*
Plugin Name: Zuzu Viral Reactions
Plugin URI: http://tiguandesign.com/
Description: Simple WordPress reactions plugin for viral stories.
Author: Tiguan
Author URI:  http://tiguandesign.com/
Licence: GPLv2
Version: 1.0
Stable Tag: 1.0
*/

class Zuzu_Viral_React {

	// $reactions = array( 'like','love', 'win', 'cute', 'lol', 'omg', 'wtf', 'fail' );


	function __construct() {
		$this->defaults = array(
			'like' => "Like",
			'love' => "LOVE",
			'win' => "Win",
			'cute' => "Cute",
			'lol' => "LOL",
			'omg' => "OMG",
			'wtf' => "WTF",
			'fail' => "Fail",
			'boxtitle' => "Your Reaction"

		);

		add_action('the_content', array($this,'addContent'));
		add_action('the_excerpt', array($this, 'zvrdisablePlugin'));
		add_action('admin_menu', array($this, 'addMenu'));
		add_action( 'admin_init', array($this, 'registerSettings'));

		add_action( 'wp_ajax_zvr_react', array($this,'react'));
		add_action( 'wp_ajax_nopriv_zvr_react', array($this,'react' ));
		add_action('wp_enqueue_scripts', array($this,'addStylesAndScripts'));
		add_action( 'load-post.php', array($this, 'initMetaBox'));
		add_action( 'load-post-new.php', array($this, 'initMetaBox'));
		add_shortcode( 'zvr_reactions', array($this, 'shortCode') );
		add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array($this, 'addSettingsLink' ));
	}



	function addSettingsLink ( $links ) {
		$link = array('<a href="' . admin_url( 'options-general.php?page=zvr_options' ) . '">Settings</a>');
		return array_merge( $links, $link );
	}
	function initMetaBox() {
		add_action( 'add_meta_boxes', array($this, 'addMetaBox'));
		add_action( 'save_post', array($this, 'savePostMeta'), 10, 2 );
	}

	function savePostMeta($post_id, $post) {
		if ( !isset( $_POST['zvr_enable_meta_nonce'] ) || !wp_verify_nonce( $_POST['zvr_enable_meta_nonce'], basename( __FILE__ ) ) )
		return $post_id;

		$post_type = get_post_type_object( $post->post_type );

		if ( !current_user_can( $post_type->cap->edit_post, $post_id ) )
		return $post_id;

		$meta_value = ( isset( $_POST['zvr_enable'] ) ? sanitize_html_class( $_POST['zvr_enable'] ) : '' );
		if (empty($meta_value)) {
			$meta_value = "off";
		}
		update_post_meta( $post_id, 'zvr_enable', $meta_value );
	}

	function addMetaBox() {
		add_meta_box('zvr-enable-on-post', 'Zuzu Viral Reactions', array($this, 'renderMetaBox'), 'post', 'normal', 'default');
	}

	function renderMetaBox() {
		$options = get_option( 'zvr_settings' );
		$enable = isset($options['zvr_auto_enable']) ? $options['zvr_auto_enable']: 'on';
		$post_id = get_the_ID();
		$meta_enable = get_post_meta( $post_id, 'zvr_enable', true );
		if (!empty($meta_enable)) {
			$enable = $meta_enable;
		}
		wp_nonce_field( basename( __FILE__ ), 'zvr_enable_meta_nonce' );
		?>

		<label><input type="checkbox" name="zvr_enable" id="zvr-enable" <?php checked($enable, 'on')?>>Enable reactions on this post</label>
		<?php
	}

	function addMenu() {
		add_options_page('Zuzu Reaction Settings', 'Zuzu Reaction', 'manage_options', 'zvr_options', array($this, 'renderOptionsPage'));
	}

	function registerSettings() {
		register_setting('zvr_options', 'zvr_settings');
	    add_settings_section( 'zvr_enable', '', array($this, 'renderEnableGuide'), 'zvr_options' );
	    add_settings_field(	'zvr_options-auto-enable-on',	'Show buttons on posts', array($this, 'renderRadio'), 'zvr_options', 'zvr_enable', array('value' => 'on'));
	    add_settings_field(	'zvr_options-auto-enable-off',	"Don't show buttons on posts", array($this, 'renderRadio'), 'zvr_options', 'zvr_enable', array('value' => 'off'));
	    add_settings_section( 'zvr_content', '', array($this, 'renderContent'), 'zvr_options' );
	    add_settings_section( 'zvr_share_translations', 'Reaction box title', array($this, 'renderReactionTranslations'), 'zvr_options');
	    add_settings_field(	'zvr_options-boxtitle',	'Your Reaction', array($this, 'renderField'), 'zvr_options', 'zvr_share_translations', array('label' => 'boxtitle'));
		add_settings_section( 'zvr_translations', 'Reactions Text', array($this, 'renderReactionTranslations'), 'zvr_options' );
	    add_settings_field(	'zvr_options-like',	'Like', array($this, 'renderField'), 'zvr_options', 'zvr_translations', array('label' => 'like'));
	    add_settings_field(	'zvr_options-love',	'Love', array($this, 'renderField'), 'zvr_options', 'zvr_translations', array('label' => 'love'));
	    add_settings_field(	'zvr_options-win',	'Win', array($this, 'renderField'), 'zvr_options', 'zvr_translations', array('label' => 'win'));
	    add_settings_field(	'zvr_options-cute',	'Cute', array($this, 'renderField'), 'zvr_options', 'zvr_translations', array('label' => 'cute'));
	    add_settings_field(	'zvr_options-lol',	'LOL', array($this, 'renderField'), 'zvr_options', 'zvr_translations', array('label' => 'lol'));
	    add_settings_field(	'zvr_options-omg',	'OMG', array($this, 'renderField'), 'zvr_options', 'zvr_translations', array('label' => 'omg'));
	    add_settings_field(	'zvr_options-wtf',	'WTF', array($this, 'renderField'), 'zvr_options', 'zvr_translations', array('label' => 'wtf'));
	    add_settings_field(	'zvr_options-fail',	'Fail', array($this, 'renderField'), 'zvr_options', 'zvr_translations', array('label' => 'fail'));



	}


	function shortCode() {
		$options = get_option('zvr_settings');
		return $this->renderPlugin($options);
	}

	function renderField($args) {
		$label = $args['label'];
		$options = get_option('zvr_settings');
		$value = isset($options['zvr_'.$label]) ? $options['zvr_'.$label]: $this->defaults[$label];
		echo "<input type='text' name='zvr_settings[zvr_$label]' value='".esc_attr($value)."'>";
	}

	function renderContent() {
		?>

			<div style="border-top: 1px solid #bbb; width: 100%; padding: 30px 0; margin: 30px 0; border-bottom: 1px solid #bbb;">

				<h3>Adding reactions manually (short code)</h3>
				<ol>
					<li>You can use shortcode <code>[zvr_reactions]</code> within post or page text.</li>
					<li>You can add <code>if (function_exists('zvr_reactions')) { zvr_reactions() }</code> into your templates.</li>
				</ol>

			</div>


		<?php
	}
	function zvrdisablePlugin($excerpt) {
		$pattern = '/zvr.*/i';
		return preg_replace($pattern, '', $excerpt);
	}


	function renderRadio($args) {
		$options = get_option( 'zvr_settings' );
		$value = $args['value'];
		$set_value = isset($options['zvr_auto_enable']) ? $options['zvr_auto_enable']: 'on';
		?>
		<input type='radio' name='zvr_settings[zvr_auto_enable]' <?php checked( $set_value, $value ); ?> value='<?php echo $value ?>'>
		<?php
	}

 	function renderReactionTranslations() {
 		echo "";
 	}

 	function renderEnableGuide() {
 		?>


 		<?php
 	}

	function renderOptionsPage() {
		?>

		<form action='options.php' method='post'>

			<p><h1>Zuzu Viral Reaction Settings</h1></p><br />

			<p>Select the default setting for Zuzu Viral Reaction visibility. You can override this setting for each post in the post editor.</p>

			<?php
			settings_fields( 'zvr_options' );

			do_settings_sections( 'zvr_options' );
			?>


			<?php submit_button(); ?>

		</form>

		<?php
	}


	function addContent($content) {
		$options = get_option('zvr_settings');
		$show_on_every_post = isset($options['zvr_auto_enable']) ? $options['zvr_auto_enable'] : 'on';
		$post_id = get_the_ID();
		$enabled = get_post_meta( $post_id, 'zvr_enable', true );
		if (!is_page() && ($enabled=="on" || (empty($enabled) && $show_on_every_post=='on'))) {
			$plugin = $this->renderPlugin($options);
			$content .= $plugin;
		}
		return $content;
	}

	function renderPlugin($options) {
		$post_id = get_the_ID();
		$post_url = get_permalink($post_id);
		$label_like =isset($options['zvr_like']) ? $options['zvr_like']: $this->defaults['like'];
		$label_love =isset($options['zvr_love']) ? $options['zvr_love']: $this->defaults['love'];
		$label_win =isset($options['zvr_win']) ? $options['zvr_win']: $this->defaults['win'];
		$label_cute =isset($options['zvr_cute']) ? $options['zvr_cute']: $this->defaults['cute'];
		$label_lol =isset($options['zvr_lol']) ? $options['zvr_lol']: $this->defaults['lol'];
		$label_omg =isset($options['zvr_omg']) ? $options['zvr_omg']: $this->defaults['omg'];
		$label_wtf =isset($options['zvr_wtf']) ? $options['zvr_wtf']: $this->defaults['wtf'];
		$label_fail =isset($options['zvr_fail']) ? $options['zvr_fail']: $this->defaults['fail'];
		$label_boxtitle =isset($options['zvr_boxtitle']) ? $options['zvr_boxtitle']: $this->defaults['boxtitle'];



		ob_start() ?>
			<div id="zuzu_viral_reactions">
				<span style="display:none">zvr</span>
				<div class="zvr-reaction-title"><?php echo $label_boxtitle ?></div>
			    <ul data-post-id="<?php echo $post_id ?>">
			      <li class="animated" data-reaction="like" <?php echo $this->getClass("like", $post_id) ?> ><a href="javascript:void(0)"><img class="animated" src="<?php echo trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/img/1f44d.svg' ?>" /><em><?php echo $label_like ?></em><span><?php echo $this->getAmount("like",$post_id) ?></span></a></li>
			      <li class="animated" data-reaction="love" <?php echo $this->getClass("love", $post_id) ?> ><a href="javascript:void(0)"><img class="animated" src="<?php echo trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/img/1f60d.svg' ?>" /><em><?php echo $label_love ?></em><span><?php echo $this->getAmount("love",$post_id) ?></span></a></li>
			      <li class="animated" data-reaction="win" <?php echo $this->getClass("win", $post_id) ?> ><a href="javascript:void(0)"><img class="animated" src="<?php echo trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/img/1f61c.svg' ?>" /><em><?php echo $label_win ?></em><span><?php echo $this->getAmount("win",$post_id) ?></span></a></li>
			      <li class="animated" data-reaction="cute" <?php echo $this->getClass("cute", $post_id) ?> ><a href="javascript:void(0)"><img class="animated" src="<?php echo trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/img/1f917.svg' ?>" /><em><?php echo $label_cute ?></em><span><?php echo $this->getAmount("cute",$post_id) ?></span></a></li>
			      <li class="animated" data-reaction="lol" <?php echo $this->getClass("lol", $post_id) ?> ><a href="javascript:void(0)"><img class="animated" src="<?php echo trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/img/1f632.svg' ?>" /><em><?php echo $label_lol ?></em><span><?php echo $this->getAmount("lol",$post_id) ?></span></a></li>
			      <li class="animated" data-reaction="omg" <?php echo $this->getClass("omg", $post_id) ?> ><a href="javascript:void(0)"><img class="animated" src="<?php echo trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/img/1f631.svg' ?>" /><em><?php echo $label_omg ?></em><span><?php echo $this->getAmount("omg",$post_id) ?></span></a></li>
	   		      <li class="animated" data-reaction="wtf" <?php echo $this->getClass("wtf", $post_id) ?> ><a href="javascript:void(0)"><img class="animated" src="<?php echo trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/img/1f914.svg' ?>" /><em><?php echo $label_wtf ?></em><span><?php echo $this->getAmount("wtf",$post_id) ?></span></a></li>
			      <li class="animated" data-reaction="fail" <?php echo $this->getClass("fail", $post_id) ?> ><a href="javascript:void(0)"><img class="animated" src="<?php echo trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/img/1f915.svg' ?>" /><em><?php echo $label_fail ?></em><span><?php echo $this->getAmount("fail",$post_id) ?></span></a></li>
			    </ul>

			    <div style="clear: both;"></div>
			 </div>

		<?php
		$plugin = ob_get_contents();
		ob_clean();
		return $plugin;
	}

	function getClass($reaction, $post_id) {

		$clicked = isset($_COOKIE["zvr_reacted_".$reaction."_".$post_id]);

		return ($clicked ? 'class="clicked"':'');
	}

	function getAmount($reaction, $post_id) {
		$meta_key = "zvr_reaction_".$reaction;
		$amount = get_post_meta($post_id, $meta_key, true) ? get_post_meta($post_id, $meta_key, true) : 0;
		return $amount;
	}

	function react() {
		if (isset($_POST["postid"])) {
			$post_id = $_POST["postid"];
			$reaction = $_POST["reaction"];
			$unreact = $_POST["unreact"];
		}
	 	$amount = $this->getAmount($reaction, $post_id);
		if (isset($unreact) && $unreact === "true") {
			unset($_COOKIE['zvr_reacted_'.$reaction.'_'.$post_id]);
	    	setcookie('zvr_reacted_'.$reaction.'_'.$post_id, '', time() - 3600, "/");
			$amount = (int) $amount - 1;
			if ($amount >=0) {
				echo "Amount: ".$amount." ";
				update_post_meta($post_id, "zvr_reaction_".$reaction, $amount);
			}
		}
		else {
			setcookie('zvr_reacted_'.$reaction.'_'.$post_id, $reaction, time() + (86400 * 30), "/");
			$amount = (int) $amount + 1;
			if ($amount >=0) {
				echo "Amount: ".$amount." ";
				update_post_meta($post_id, "zvr_reaction_".$reaction, $amount);
			}
		}
		return;
	}

	function addStylesAndScripts() {
		wp_enqueue_style( 'zvr-font', 'https://fonts.googleapis.com/css?family=Open+Sans' );
		wp_enqueue_style( 'zvr-style', trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/css/zvr-styles.css', array(), "1.0.3" );
		wp_enqueue_script( 'zvr-script', trailingslashit( plugin_dir_url( __FILE__ ) ) . 'assets/js/zvr-script.js', array( 'jquery' ), "1.0.3" );
		$localize = array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
		);

		wp_localize_script( 'zvr-script', 'zvr_data', $localize );
	}

}

function zvr_reactions() {
	// Call from templates
	// if (function_exists('zvr_reactions')) { zvr_reactions() }
	$zvr = new Zuzu_Viral_React();
	$options = get_option('zvr_settings');
	echo $zvr->renderPlugin($options);
}

new Zuzu_Viral_React();