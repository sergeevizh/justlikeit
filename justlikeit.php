<?php
/*
Plugin Name: Just Like It
Plugin URI: http://casepress.org/
Description: Just Like It - plugin adds the ability to evaluate the posts and comments to them.
Version: 0.1
Author: Rasko
Author URI: http://casepress.org/
*/

// Stop direct call
if (!empty($_SERVER['SCRIPT_FILENAME']) && basename(__file__) == basename($_SERVER['SCRIPT_FILENAME']))
	die ('Please do not load this page directly. Thanks!');

if (!class_exists('JustLikeIt')) {
	// Подключаем файл с классом настроек
	require_once dirname(__FILE__) . '/settings.php';

	class JustLikeIt {
		// Конструктор объекта
		function JustLikeIt()
		{
			$this->pluginPath = dirname(__FILE__);
			$this->pluginUrl = WP_PLUGIN_URL . '/justlikeit';
			
			load_plugin_textdomain('just-like-it', false, basename($this->pluginPath).'/languages' );
			
			$this->actions();
			
			new JustLikeItSettings();
		}
		
		function actions() 
		{
			add_action('wp_head', array(&$this, 'add_ajax_library'));
			add_action('wp_print_scripts', array(&$this, 'register_plugin_scripts'));
			add_action('wp_print_styles', array(&$this, 'register_plugin_styles'));
			add_action('delete_post', array(&$this, 'delete_post'));
			add_action('delete_comment', array(&$this, 'delete_comment'));
			add_filter('comments_array', array(&$this, 'list_comments'));
			if (get_option('just_like_posts'))
			{
				add_action('wp_ajax_like_post', array(&$this, 'like_post'));
				add_filter('the_content', array(&$this, 'link_like_post'));
			}
			if (get_option('just_like_comments'))
			{
				add_action('wp_ajax_like_comment', array(&$this, 'like_comment'));
				add_filter('comment_text', array(&$this, 'link_like_comment'));
			}
		}
		
		function register_plugin_scripts()
		{
			wp_enqueue_script('justlikeit', $this->pluginUrl . '/js/jquery.justlikeit.js' , array('jquery'));
			wp_enqueue_script('arcticmodal', $this->pluginUrl . '/js/jquery.arcticmodal-0.3.min.js' , array('jquery'));
		}
		
		function register_plugin_styles()
		{
			wp_enqueue_style('justlikeit', $this->pluginUrl . '/css/style.css');
		}
		
		function list_comments($comments='')
		{
			foreach($comments as $key => $comment){  
				if ($comment->{'comment_type'} == 'like') 
					{
						unset($comments[$key]);
					}
			}  
			return $comments;
		}
		
		function link_like_post($content='') 
		{
			$check = $logged_in = 0;
			$id = get_the_ID();
			$type = get_post_type($id);
			$accepted = preg_replace('/\s/', '', get_option('just_like_posts_like_accepted'));
			if ($accepted)	{
				$accepted = explode(",", $accepted);
				if(!in_array($type, $accepted)) return $content;
			}						
			if (is_user_logged_in()) {
				$logged_in = 1;

				$check = get_comments(array(
					'post_id' => $id,
					'type' => 'like',
					'user_id' => wp_get_current_user()->ID ,
					'count' => true
				));
			}
			
			$count = get_comments(array(
					'post_id' => $id,
					'type' => 'like',
					'count' => true
			));
			return $this->generate_button($logged_in, 'post', $content, $check, $id, $count);
		}
		
		function link_like_comment($content='') 
		{
			$check = $logged_in = 0;
			$id = get_comment_ID();

			if (is_user_logged_in()) {
				$logged_in = 1;

				$check = get_comments(array(
					'parent' => $id,
					'type' => 'like',
					'user_id' => wp_get_current_user()->ID,
					'count' => true
				));
			}
			$count = get_comments(array(
					'parent' => $id,
					'type' => 'like',
					'count' => true
				));
			return $this->generate_button($logged_in, 'comment', $content, $check, $id, $count);
		}
		
		function generate_button($logged_in, $type, $content, $check, $id, $count)
		{
			$do = '" onclick="jQuery(\'#registerModal\').arcticmodal()';
			$text = str_replace('$img', $this->pluginUrl . '/img/', get_option('just_like_like_label'));
			if ($logged_in==1) {
				$invite = '';
				if ($check == 0) {			
					$do = 'doLike';
				}
				else {
					$text = str_replace('$img', $this->pluginUrl . '/img/', get_option('just_like_unlike_label'));
					$do = 'doUnlike';
				}	
				
			}
			$siteurl = get_site_url();
			$content = $content.'<div class="just-like-'.$type.'-frame"><span id="just-like-'.$type.'-count-'.$id.'" class="just-like-'.$type.'-count">'.$this->generate_count_label($count).'</span><a href="#" rel="'.$type.'_'.$id.'" class="just-like-'.$type.'-link '.$do.'">'.$text.'</a></div>';
			if ($logged_in==0) $content .= '<div class="g-hidden">
						<div class="box-modal" id="registerModal">
							<div class="box-modal_close arcticmodal-close">закрыть</div>'.
							str_replace('$siteurl', $siteurl, get_option('just_like_no_auth'))
							.'</div>
					</div>';
			return $content;
		}

		function generate_count_label($count)
		{
			//if(!get_option('just_like_count_tags')){
				//if ($count == 1) return __('Liked by ', 'just-like-it').$count.__(' user', 'just-like-it').'.';
				//if ($count != 1) return __('Liked by ', 'just-like-it').$count.__(' users', 'just-like-it').'.';
				//nplurals=3; plural=(n%10==1 &amp;&amp; n%100!=11) ? 0 : ((n%10&gt;=2 &amp;&amp; n%10&lt;=4 &amp;&amp; (n%100&lt;10 || n%100&gt;=20)) ? 1 : 2);
				//$start = $count;
				//$count = abs($count) % 100;
				////$count1 = $count % 10;
				///if ($count > 10 && $count <= 20) return __('Liked by ', 'just-like-it').$start.__(' users', 'just-like-it').'.';
				//if ($count1 > 1 && $count1 < 5) return __('Liked by ', 'just-like-it').$start.__(' users', 'just-like-it').'.';
				//if ($count1 == 1) return __('Liked by ', 'just-like-it').$start.__(' users', 'just-like-it').'.';
				//return __('Одобрили ', 'just-like-it').$start.__(' пользователей', 'just-like-it').'.';
			//}
			//else{
				$return = get_option('just_like_count_tags');
				return str_replace('$count', $count, $return);
			//}
		}
		
		function add_ajax_library() 
		{
			$html = '<script type="text/javascript">';
			$html .= 'var ajaxurl = "' . admin_url('admin-ajax.php') . '";';
			$html .= 'var likeLabel = "'.str_replace('$img', $this->pluginUrl . '/img/', get_option('just_like_like_label')).'";';
			$html .= 'var unLikeLabel = "'.str_replace('$img', $this->pluginUrl . '/img/', get_option('just_like_unlike_label')).'";';
		    $html .= '</script>';

			echo $html;
		}
	   
		function like_post()
		{
			// проверяем залогинен ли пользователь
			if (is_user_logged_in()) {
				// Нужно убедиться что айди передано и является числом
				if (isset($_POST['post_id']) && is_numeric($_POST['post_id'])) {
					// Проверка существования поста вообще
					$check = get_post($_POST['post_id']);
					if (!$check) die();						
					// Если установлен ЭКШН...
					if(isset($_POST['actionLike'])){
						$check = get_comments(array(
								'user_id' => wp_get_current_user()->ID,
								'post_id' => $_POST['post_id'],
								'type' => 'like',
								'count' => true
						));			
						if (($_POST['actionLike']) == "doLike") {
							if ($check) die(); // чтобы выполнить данный экшн нужно убедиться что лайка нет.

							$res = wp_insert_comment(array(  
								'comment_post_ID' => $_POST['post_id'],  
								'comment_author' => '',  
								'comment_author_email' => '',  
								'comment_author_url' => '',  
								'comment_content' => '',  
								'comment_type' => 'like',  
								'comment_parent' => 0,  
								'user_id' => wp_get_current_user()->ID ,  
								'comment_author_IP' => '',  
								'comment_agent' => '',  
								'comment_date' => '',  
								'comment_approved' => 1  
							));						
						}
						if (($_POST['actionLike']) == "doUnlike") {
							if (!$check) die(); // чтобы выполнить данный экшн нужно убедиться что лайк есть.
							//запрос на удаление лайка к посту 
							$com = get_comments(array(
								'user_id' => wp_get_current_user()->ID,
								'post_id' => $_POST['post_id'],
								'type' => 'like',
								'count' => false
							));	
							$res = wp_delete_comment($com[0]->comment_ID, true);
						}
						
						if($res){
							$response = array('success' => true); 
							$count = get_comments(array(
								'post_id' => $_POST['post_id'],
								'type' => 'like',
								'count' => true
							));
							$response['counttext'] = $this->generate_count_label($count);
							$response['ID'] = $com[0]->comment_ID;
						}else{
							$response = array('success' => false);
						}
						header("Content-Type: application/json");
						$response = json_encode($response);
						echo $response;
					}
					die();
				}
				die();
			}		
			die();
		}
		
		function like_comment()
		{
			// проверяем залогинен ли пользователь
			if (is_user_logged_in()) {
				// Нужно убедиться что айди передано и является числом
				if (isset($_POST['comment_id']) && is_numeric($_POST['comment_id'])) {
					// Проверка существования коммента вообще				
					$check = get_comments(array(
								'ID' => $_POST['comment_id'],
								'count' => true
							));	
					if (!$check) die();								
					// Если установлен ЭКШН...
					if(isset($_POST['actionLike'])){
						// Проверяем существование лайка к данному посту от данного пользователя с полученным айди тут
						$check = get_comments(array(
								'user_id' => wp_get_current_user()->ID,
								'parent' => $_POST['comment_id'],
								'type' => 'like',
								'count' => true
						));			
						if (($_POST['actionLike']) == "doLike") {
							if ($check) die(); // чтобы выполнить данный экшн нужно убедиться что лайка нет.

							$res = wp_insert_comment(array(  
								'comment_post_ID' => 0,  
								'comment_author' => '',  
								'comment_author_email' => '',  
								'comment_author_url' => '',  
								'comment_content' => '',  
								'comment_type' => 'like',  
								'comment_parent' => $_POST['comment_id'],  
								'user_id' => wp_get_current_user()->ID ,  
								'comment_author_IP' => '',  
								'comment_agent' => '',  
								'comment_date' => '',  
								'comment_approved' => 1  
							));									 
						}
						if (($_POST['actionLike']) == "doUnlike") {
							if (!$check) die(); // чтобы выполнить данный экшн нужно убедиться что лайк есть.
							//запрос на удаление лайка к посту 
							// .....
							$com = get_comments(array(
								'user_id' => wp_get_current_user()->ID,
								'parent' => $_POST['comment_id'],
								'type' => 'like',
								'count' => false
							));	
							$res = wp_delete_comment($com[0]->comment_ID, true); 
						}
						
						if($res){
							$response = array('success' => true);
							$count = get_comments(array(
								'parent' => $_POST['comment_id'],
								'type' => 'like',
								'count' => true
							));							
							$response['counttext'] = $this->generate_count_label($count);
							$response['ID'] = $com[0]->comment_ID;
						}else{
							$response = array('success' => false);
						}
						header("Content-Type: application/json");
						$response = json_encode($response);
						echo $response;
					}
					die();
				}
				die();
			}			
			die();
		}
		
		function delete_post($id) 
		{
			$comments = get_comments(array(
				'post_ID' => $id,
				'type' => 'like',
				'count' => false
			));
			
			foreach($comments as $comment){  
				$res = wp_delete_comment($comment->comment_ID, true);
			}  			
		}
		
		function delete_comment($id) 
		{
			$comments = get_comments(array(
				'parent' => $id,
				'type' => 'like',
				'count' => false
			));	
			
			foreach($comments as $comment){  
				$res = wp_delete_comment($comment->comment_ID, true);
			}  
		}
		
	}
}

$justlikeit = new JustLikeIt();

function justlikeit()
{
	global $justlikeit;
	if (get_the_ID())
		$justlikeit->link_like_post;
	else
		$justlikeit->link_like_comment;
}
?>