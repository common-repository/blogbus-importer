<?php
/*
Plugin Name: blogbus Importer
Plugin URI: http://wordpress.org/extend/plugins/blogbus-importer/
Description: Import posts from blogbus xml file. 这个导入工具可以帮您从BlogBus 导出的XML文件中解析出日志并导入您的 blog。(带评论). 如果您现在不能从blogbus导出xml文件， 请访问b2w.xrmplatform.org ,输入您的博客大巴名，以及接受邮箱，提交后，查找您的邮箱，将会收到一封附件，附件即可直接通过wordpress导入工具导入到wordpress
Author: xrmplatform
Author URI: http://blog.xrmplatform.org
Version: 0.1
Stable tag: 0.1
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

if ( !defined('WP_LOAD_IMPORTERS') )
	return;

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( !class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) )
		require_once $class_wp_importer;
}


if(!class_exists('BLOGBUS_Import')){
class BLOGBUS_Import extends WP_Importer {

	var $posts = array ();
	var $file;

	function header() {
		echo '<div class="wrap">';
		echo '<h2>导入 BlogBus 的 XML 文件</h2>';
	}

	function footer() {
		echo '</div>';
	}

	function unhtmlentities($string) { // From php.net for < 4.3 compat
		$trans_tbl = get_html_translation_table(HTML_ENTITIES);
		$trans_tbl = array_flip($trans_tbl);
		return strtr($string, $trans_tbl);
	}

	function greet() {
		echo '<div class="narrow">';
		echo '<p>您好！这个导入工具可以帮您从BlogBus 导出的XML文件中解析出日志并导入您的 blog。(带评论)</p>';
		wp_import_upload_form("admin.php?import=blogbus&amp;step=1");
		echo '</div>';
	}
	function import() {
		global $wpdb;
		$file = wp_import_handle_upload();
		if ( isset($file['error']) ) {
			echo $file['error'];
			return;
		}

		$this->file = $file['file'];
		$datalines = file($this->file); // Read the file into an array
		$_importdata = implode('', $datalines); // squish it
		$logs = array();$now = 0;
		while(($logat = stripos($_importdata,'<log>',$logat)) !== false){
			$logat += 5;
			if(($logend = stripos($_importdata,'</log>',$logat)) !== false){
				$tmp = substr($_importdata,$logat,$logend-$logat);
				while(($i= strpos($tmp,'<',$i))!== false){
					$i+=1;
					if(substr($tmp,$i,2) == '!['){
						$i = strpos($tmp,']>',$i);
					}else if(($j = strpos($tmp,'>',$i))!== false){
						$name = substr($tmp,$i,$j-$i);
						if(substr($name,0,1) != '/'){
							if(($k = stripos($tmp,"</$name>",$j))!==false){
								$value = str_replace(array('<![CDATA[',']]>'),'',substr($tmp,$j+1,$k-$j-1));
								//'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_excerpt', 'post_status', 'post_name', 'comment_status', 'ping_status', 'post_modified', 'post_modified_gmt', 'guid', 'post_parent', 'menu_order', 'post_type'								
								if($name != 'Comments'){
									if($name == 'Title'){$name = 'post_title'; $logs[$now][$name] = $wpdb->escape($value);}
									elseif($name == 'LogDate'){$name = 'post_date'; $logs[$now][$name] = $wpdb->escape($value);}
									elseif($name == 'Excerpt'){$name = 'post_excerpt'; $logs[$now][$name] = $wpdb->escape($value);}
									elseif($name == 'Content'){$name = 'post_content'; $logs[$now][$name] = $wpdb->escape(str_ireplace(array('<pre>','</pre>'),'',$value));}
								}
								else{
									$values = array();$know = 0;
									$kys = split('<Comment>',$value);
									foreach($kys as $value){
										while(($ki= strpos($value,'<',$ki))!== false){
											$ki+=1;
											if(substr($value,$ki,2) == '!['){
												$ki = strpos($value,']>',$ki);
											}else if(($kj = strpos($value,'>',$ki))!== false){
												$kname = substr($value,$ki,$kj-$ki);
												if(substr($kname,0,1) != '/'){
													if(($kk = stripos($value,"</$kname>",$kj))!==false){
														$kvalue = str_replace(array('<![CDATA[',']]>'),'',substr($value,$kj+1,$kk-$kj-1));
														//'comment_post_ID', 'comment_author', 'comment_author_url', 'comment_author_email', 'comment_author_IP', 'comment_date', 'comment_date_gmt', 'comment_content', 'comment_approved', 'comment_type', 'comment_parent'
														if($kname == 'Email'){$kname = 'comment_author_email'; $values[$know][$kname] = $wpdb->escape($kvalue);}
														elseif($kname == 'NiceName'){$kname = 'comment_author'; $values[$know][$kname] = $wpdb->escape($kvalue);}
														elseif($kname == 'CommentText'){$kname = 'comment_content'; $values[$know][$kname] = $wpdb->escape($kvalue);}
														elseif($kname == 'CreateTime'){$kname = 'comment_date'; $values[$know][$kname] = $wpdb->escape($kvalue);}
													}
												}
											}
										}
										$know ++;
									}
									$logs[$now][$name] = $values;
								}
							}
						}
					}
				}
			}
			$now++;
		}
		$cc = 0;
		if(is_array($logs))foreach ($logs as $log)
		{
			$log['post_status'] = 'publish';
			$post_id = wp_insert_post($log);
			if($post_id > 0)echo "导入文章《{$log['post_title']}》";
			if(is_array($log['Comments'])){
			$c = 0;
			foreach ($log['Comments'] as $comment){
				$comment['comment_post_ID'] = $post_id;
				wp_insert_comment($comment);
				$c ++;
			}
			echo "( $c 个评论)";
			}
			echo "<br/>\n";
			$cc ++;
		}
		echo "&nbsp;<br/>总共导入了 $cc 个文章。";
		echo '<h3>';
		printf(__('All done. <a href="%s">Have fun!</a>'), get_option('home'));
		echo '</h3>';
	}

	function dispatch() {
		if (empty ($_GET['step']))
			$step = 0;
		else
			$step = (int) $_GET['step'];

		$this->header();

		switch ($step) {
			case 0 :
				$this->greet();
				break;
			case 1 :
				check_admin_referer('import-upload');
				$result = $this->import();
				if ( is_wp_error( $result ) )
					echo $result->get_error_message();
				break;
		}

		$this->footer();
	}

	function RSS_Import() {
		// Nothing.
	}
}
}
$rss_import = new BLOGBUS_Import();

register_importer('blogbus', __('BlogBus'), '导入从 BlogBus 导出的XML文件(非标准RSS格式 *BlogBus 用户登录管理后台 - 进入全部Blog - 在博客名字右边有导出链接)', array ($rss_import, 'dispatch'));
?>
