<?php
/*
Plugin Name: Mail Notice bbPress 1.0
Plugin URI: 
Description: Adds bbPress 2.0 to WordPress new post mail notice.
Version: 1.0
Author: Hideaki Oguchi
Author URI: http://www.bluff-lab.com
License: OpenSource under GPL2
*/

/*

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

*/

// Create our main bbPress Search object
global $bbpmailnotice;
$bbpmailnotice = new bbPressMailNotice();

// Define our main Content Filter class
class bbPressMailNotice {
    
    // Store the forum url for search results
    
    // Hook into the WordPres Shortcode API in our constructor
    function __construct(){
		add_action('bb_new_post', array(&$this, 'bb_new_post_mail_notice') );
		add_action('admin_menu', array(&$this, 'bb_new_post_mail_notice_add_admin_menu') );
		register_activation_hook(__FILE__, array(&$this, 'bb_new_post_mail_notice_init_options'));
    }

	function bb_new_post_mail_notice($post_id){

		// グローバル変数の$wp_queryを使う
		global $wp_query;

		global $bbdb, $bb_ksd_pre_post_status;

		if ( !empty( $bb_ksd_pre_post_status ) )
			return false;

		if ( !$post = bb_get_post( $post_id ) )
			return false;

		if ( !$topic = get_topic( $post->topic_id ) )
			return false;

		// パラメータのデフォルト値
		$defaults = array(
			'title' => get_option('mnbbp_title'),
			'body' => get_option('mnbbp_body'),
			'echo' => 1,
		);

		// パラメータを分解する
		$args = '';
		$r = wp_parse_args($args, $defaults);

		$post_id	= $post->post_id;
		$topic_id	= $topic->topic_id;

		if ( !$poster_name = get_post_author( $post_id ) )
			return false;

		if ( !$user_ids = $bbdb->get_col( $bbdb->prepare( "SELECT DISTINCT `$bbdb->posts`.`poster_id` FROM $bbdb->posts WHERE `$bbdb->posts`.`topic_id` = '%d'", $topic_id ) ) ){
			return false;
		}

		foreach ( (array) $user_ids as $user_id ) {

			// don't send notifications to the person who made the post
			if ( $user_id == $post->poster_id ){
				continue; 
			}

			$user = bb_get_user( $user_id );

			$t_title	= $r['title'];
			$t_body		= $r['body'];

			// For plugins
			if ( !$message = apply_filters( 'bb_subscription_mail_message', $t_body, $post_id, $topic_id ) ){
				continue; 
			}

			$per_page	= 15;
			$page		= intval( ceil( $post->post_position / $per_page ) );

			$permalink	=  bp_get_root_domain() . '/' . bp_get_groups_root_slug() . '/' . bp_current_item() . '/forum/topic/' . $topic->topic_slug . "/";
			$permalink	.= '?topic_page=' . $page . '#post-' . $post_id;

			bb_mail(
				$user->user_email,
				apply_filters( 'bb_subscription_mail_title', sprintf($t_title, bb_get_option( 'name' ), $topic->topic_title), $post_id, $topic_id ),
				sprintf( $message, $poster_name, strip_tags( $post->post_text ), $permalink, strip_tags( $topic->topic_title ) )
			);
		}
	}

	// 設定の初期値を保存
	function bb_new_post_mail_notice_init_options() {

		// 「fjscp_installed」の設定項目が保存されていないときだけ
		// 初期化の処理を行う
		if (!get_option('mnbbp_installed')) {
			update_option('mnbbp_title', "[%1\$s]%2\$s");
			update_option('mnbbp_body', "%1\$s wrote:\n\n%2\$s\n\nRead this post on the forums: %3\$s\n\nYou're getting this email because you subscribed to '%4\$s.'\nPlease click the link above, login, and click 'Unsubscribe' at the top of the page to stop receiving emails from this topic." );
			update_option('mnbbp_installed', 1);
		}
	}

	// 「プラグイン」メニューのサブメニューに「Same Category Postsの設定」を追加
	function bb_new_post_mail_notice_add_admin_menu() {
		add_submenu_page('plugins.php', 'BuddyPress メール設定', 'BuddyPress メール設定', 'administrator', __FILE__, array(&$this, 'bb_new_post_mail_notice_admin_page'));
	}

	// 設定画面の表示
	function bb_new_post_mail_notice_admin_page() {

		if ((!empty($_POST['posted'])) && ($_POST['posted'] == 'Y')) {
			update_option('mnbbp_title', stripslashes($_POST['title']));
			update_option('mnbbp_body', stripslashes($_POST['body']));
		}
?>
<?php if((!empty($_POST['posted'])) && ($_POST['posted'] == 'Y')) : ?><div class="updated"><p><strong>設定を保存しました</strong></p></div><?php endif; ?>
<div class="wrap">
	<h2>BuddyPress メール通知の設定の設定</h2>
	<form method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
		<input type="hidden" name="posted" value="Y">
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><label for="title">件名<label></th>
				<td>
					<input name="title" type="text" id="title" value="<?php echo esc_attr(get_option('mnbbp_title')); ?>" class="regular-text code" /><br />メールの件名を指定します。
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="body">本文<label></th>
				<td>
					<input name="body" type="text" id="body" value="<?php echo esc_attr(get_option('mnbbp_body')); ?>" class="regular-text code" /><br />メールの本文を指定します。
				</td>
			</tr>
		</table>

		<p class="submit">
			<input type="submit" name="Submit" class="button-primary" value="変更を保存" />
		</p>
	</form>
</div>
<?php
	}
}
?>
