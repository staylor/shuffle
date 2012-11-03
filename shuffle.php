<?php
/*
Plugin Name: Shuffle
Description: Re-Order your attachments
Author: Scott Taylor
Version: 0.4.1
Author URI: http://scotty-t.com
*/

define( 'SHUFFLE_PLUGIN_URL', WP_PLUGIN_URL . '/shuffle' );

class Shuffle {
	var $slug;
	var $post_id;
	var $post;
	var $child_count;
	
	function init() {
		$this->slug = 'shuffle-media';

		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_filter( 'post_row_actions', array( $this, 'add_media_link' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'add_media_link' ), 10, 2 );
		add_filter( 'media_row_actions', array( $this, 'add_media_link' ), 10, 2 );
		add_filter( 'manage_media_columns', array( $this, 'columns' ) );
		add_action( 'manage_media_custom_column', array( $this, 'media_columns' ), 10, 2 );
		add_action( 'manage_upload_sortable_columns', array( $this, 'sortable_columns' ) );

		add_action( 'admin_action_shuffle_detach', array( $this, 'detach' ) );

		add_action( 'wp_ajax_shuffle_add_attachment_type', array( $this, 'add_attachment_type_callback' ) ); 
	}
	
	function menu() {
		add_action( 'admin_print_styles', 	array( $this, 'styles' ) );
		add_action( 'admin_print_scripts', 	array( $this, 'scripts' ) );

		$shuffle_hook = add_submenu_page(
			'upload.php', 
			__( 'Shuffle', 'shuffle' ), 
			__( 'Shuffle', 'shuffle' ), 
			'edit_posts', 
			$this->slug, 
			array( $this, 'page' )
		);

		add_action( "load-$shuffle_hook", array( $this, 'load' ) );
	}
		
	function styles() {
		wp_enqueue_style( 'shuffle-css', SHUFFLE_PLUGIN_URL . '/shuffle.css' );
	}
	
	function scripts() {
		wp_enqueue_script( 'shuffle-js', SHUFFLE_PLUGIN_URL . '/shuffle.js', array( 'json2', 'jquery-ui-sortable' ) );
	}
	
	function load() {		
		if ( !empty( $_REQUEST['post_id'] ) ) {
			$this->post_id = (int) $_REQUEST['post_id'];
			$this->post = get_post( $this->post_id );
		}

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && !empty( $this->post_id ) ) {
			$this->save_items( $_POST['image_data'] ); 
			$this->save_items( $_POST['audio_data'] );
			$this->save_items( $_POST['video_data'] );	

			$url = add_query_arg( 
			    'post_id', 
			    $this->post_id, 
			    add_query_arg( 'saved', 1, menu_page_url( $this->slug, false ) )
			);

			wp_redirect( $url );	
			exit();	
		}
	}
	
	function detach() {
		global $wpdb;

		if ( !empty( $_GET['post_id'] ) ) {
			$wpdb->update( $wpdb->posts, 
				array(
					'post_parent' 	=> 0,
					'menu_order' 	=> 0
				),
				array( 'ID' => (int) $_GET['post_id'] )	
			);
		}
		
		clean_post_cache( $_GET['post_id'] );

		$sendback = preg_replace( '|[^a-z0-9-~+_.?#=&;,/:]|i', '', wp_get_referer() );
		wp_redirect( $sendback );
		exit();	
	}
	
	function save_items( &$postdata ) {
		global $wpdb;

		if ( empty( $postdata ) )
			return;
		
		$items = json_decode( stripslashes( $postdata ) );

		if ( !empty( $items ) )
			foreach ( $items as $i ) {
				$wpdb->update( $wpdb->posts, 
					array( 'menu_order'	=> $i->order ),
					array( 'ID'			=> $i->id )
				);
			}
	}
	
	function add_media_link( $actions, $post ) {
		$parent = get_children( array( 'post_parent' => $post->ID, 'fields' => 'ID' ) );

		if ( !empty( $parent ) )
		    $actions['shuffle_media'] = $this->item_link( $post->ID, __( 'Shuffle Media', 'shuffle' ) );

		return $actions;
	}
    
	function columns( $columns ) {
		$before = array();
		$after = array();

		$is_before = true;
		foreach ( $columns as $column => $value ) {
		    if ( 'parent' === $column ) {
		        $is_before = false;
		    } elseif ( $is_before ) {
		        $before[$column] = $value;
		    } else {
		        $after[$column] = $value;
		    }
		}

		$new = array( 'shuffle_parent' => _x( 'Attached to', 'column name' ) );

		$columns = array_merge( $before, $new, $after );

		return $columns;
	}
    
	function sortable_columns( $columns ) {
	    $columns['shuffle_parent'] = 'parent';
	    return $columns;
	}
    
	function media_columns( $column_name, $id ) {
	    if ( 'shuffle_parent' === $column_name ) {            
	        $post = get_post( $id );
        
	        if ( $post->post_parent > 0 ) {
	            $parent = get_post( $post->post_parent );
	            if ( $parent ) {
	                $title =_draft_or_post_title( $post->post_parent );
	            }

	            if ( 'attachment' === $parent->post_type ) {
	                $meta = wp_get_attachment_image_src( $parent->ID, array( 60, 60 ), true );
	                if ( !empty( $meta ) )
	                    printf( '<img src="%s" width="%d" height="%d" class="shuffle-parent"/>', $meta[0], $meta[1], $meta[2] );  
	            }
	        ?>
	            <strong>
	            <?php if ( current_user_can( 'edit_post', $post->post_parent ) ) { ?>
	                <a href="<?php echo get_edit_post_link( $post->post_parent ); ?>"><?php echo $title ?></a><?php
	            } else {
	                echo $title;
	            } ?></strong><?php 
	            if ( 'attachment' !== $parent->post_type ) {
	                echo ', ', get_the_time( __( 'Y/m/d' ), $parent->ID );
	            } else {
	                echo '<br/>';
	            }
            
	            if ( preg_match( '/^.*?\.(\w+)$/', get_attached_file( $parent->ID ), $matches ) )
	                echo esc_html( strtoupper( $matches[1] ) );
	            else
	                echo strtoupper( str_replace( 'image/', '', $parent->post_mime_type ) );
            
	            printf(
	                '<br/><a href="%s">%s</a>', 
	                admin_url( sprintf( 'admin.php?action=shuffle_detach&post_id=%d', $post->ID ) ),
	                __( 'Detach', 'shuffle' ) 
	            ); 

	        } else {
            
	            _e( '(Unattached)' ); ?><br />
	            <a class="hide-if-no-js"
	                onclick="findPosts.open( 'media[]','<?php echo $post->ID ?>' ); return false;"
	                href="#the-list"><?php _e( 'Attach' ); ?></a><?php 
	        }
	    }
	}
	
	function item_link( $id, $label ) {
		return sprintf( '<a href="upload.php?page=%s&post_id=%d">%s</a>', $this->slug, $id, $label );
	}
	
	function back_link() {
		$back = '';

		$parents = get_post_ancestors( $this->post_id );

		if ( !empty( $parents ) ) {		
			$post = get_post( array_shift( $parents ) );
			$back = $this->item_link( $post->ID, sprintf( __( 'Back to &#8220;%s&#8221;', 'shuffle' ), $post->post_title ) );
		} else if ( 'attachment' === $this->post->post_type ) {
			$back = sprintf(
				'<a href="%s">%s</a>', 
				admin_url( 'upload.php' ),    
				__( 'Back to Media list', 'shuffle' ) 
			);
		} else {
			$obj = get_post_type_object( get_post_type( $this->post_id ) );
			$back = sprintf(
				'<a href="%s">%s</a>', 
				admin_url( sprintf( 'edit.php?post_type=%s', empty( $this->post ) ? 'post' : $this->post->post_type ) ),    
				sprintf( __( 'Back to %s list', 'shuffle' ), $obj->labels->singular_name )  
			);
		}

		if ( !empty( $back ) )
		 	printf( '<p>%s</p>', $back );
	}	

	function parent_image() {
		if ( empty( $this->post ) )
			return;

		if ( strstr( get_post_mime_type( $this->post ), 'image' ) ): 
			$thumb = wp_get_attachment_image( $this->post_id, 'medium', true ); 

			if ( !empty( $thumb ) )
				echo $thumb;
		endif;
	}
	
	function children() {
		?>
		<p><?php _e( "Drag your media to re-order. When you're done dragging, hit the blue button!", 'shuffle' ) ?></p>
		<?php	
		$imgs = $this->images();
		$auds = $this->audio();
		$vids = $this->video();

		$this->child_count = $imgs + $auds + $vids;
	}

	function list_item( &$obj, $title = '' ) {
		return sprintf( 
			'<li data-id="%d" data-orig-order="%d">%s</li>',
			$obj->ID,
			$obj->menu_order,
			$this->item_link( $obj->ID, empty( $title ) ? apply_filters( 'the_title', $obj->post_title ) : $title )
		);
	}	

	function by_mime_type( $type = 'image', $params = '', $include_featured = false ) {
		if ( empty( $type ) )
			return;
		
		$parent_id = '';
		if ( !empty( $this->post_id ) )
			$parent_id = $this->post_id;
		
		if ( empty( $parent_id ) )
			$parent_id = get_the_ID();		
		
		if ( is_array( $params ) && !empty( $params['post_parent'] ) ) {
			$parent_id = $params['post_parent'];
			unset( $params['post_parent'] );	
		} elseif ( is_int( $params ) ) {
			$parent_id = $params;
		}
			
		if ( empty( $parent_id ) )
			return;

		$exclude_posts = array();			
			
		if ( is_array( $params ) ) {
			if ( !empty( $params['post_mime_type'] ) )
				unset( $params['post_mime_type'] );

			if ( !empty( $params['post__not_in'] ) ) 
				$exclude_posts = (array) $params['post__not_in'];

			if ( isset( $params['and_featured'] ) ) {
				$include_featured = $params['and_featured'];
				unset( $params['and_featured'] );
			}
		} else {
			$params = array();
		}

		if ( !$include_featured ) {
			$featured_id = get_post_meta( $parent_id, '_thumbnail_id', true );
			if ( !empty( $featured_id ) )
				$exclude_posts[] = $featured_id;

			$params['post__not_in'] = $exclude_posts;	
		}

		$args = array(
			'order'       	=> 'ASC',
			'orderby'     	=> 'menu_order',
			'post_type'   	=> 'attachment',
			'post_status' 	=> 'inherit',
			'numberposts' 	=> -1,
			'post_parent'   => $parent_id,
			'post_mime_type'=> $type
		);

		$vars = wp_parse_args( $params, $args );
        
		return get_posts( $vars );
	}
	
	function images() {
		if ( empty( $this->post_id ) )
			return 0;
			
		$images = $this->by_mime_type( 'image' );	

		if ( !empty( $images ) ): ?>
			<h3><?php _e( 'Images', 'shuffle' ) ?></h3>
			<ul id="shuffle-images">
			<?php
			foreach ( $images as $image ) {
				$thumb = wp_get_attachment_image( $image->ID, array( 75, 75 ), true );
				echo $this->list_item( $image, $thumb );					
			}
			?>
			</ul>
		<?php	
		endif;

		return count( $images );
	}

	function audio() {
		if ( empty( $this->post_id ) )
			return 0;
			
		$audios = $this->by_mime_type( 'audio' );	

		if ( !empty( $audios ) ): ?>
			<h3><?php _e( 'Audio', 'shuffle' ) ?></h3>
			<ul id="shuffle-audio">
			<?php
			foreach ( $audios as $audio ) 
				echo $this->list_item( $audio ); 
			?>
			</ul>
		<?php	
		endif;

		return count( $audio );
	}

	function video() {
		if ( empty( $this->post_id ) )
			return 0;
		
		$videos = $this->by_mime_type( 'video' );	

		if ( !empty( $videos ) ): ?>
			<h3><?php _e( 'Video', 'shuffle' ) ?></h3>
			<ul id="shuffle-video">
			<?php
			foreach ( $videos as $video ) 
				echo $this->list_item( $video );
			?>
			</ul>
		<?php	
		endif;

		return count( $videos );
	}
	
	function page() {
		?>
		<div class="wrap" id="shuffle-wrap">	
			<div id="icon-upload" class="icon32"><br /></div>
			<h2><?php 
				echo !empty( $this->post_id ) ? sprintf( 
					__( 'Shuffle Media for "%s"', 'shuffle' ),
					get_the_title( $this->post_id )
				) : __( 'Shuffle Media', 'shuffle' )
			?></h2>
			<?php	
		
			if ( isset( $_GET['saved'] ) ): ?>
			<div class="updated fade" id="shuffle-notice">
				<p><?php _e( 'Your media has been shuffled!' ) ?></p>
			</div>
			<?php	
			endif;			

			$this->back_link();

			if ( !empty( $this->post_id ) ) {
				$this->parent_image();
				$this->children();                
			}

            if ( !empty( $this->post_id ) ):	
                if ( !empty( $this->child_count ) ): ?>	
                <form id="shuffle-form" method="post" action="<?php menu_page_url( $this->slug ) ?>">
                    <fieldset>	
                        <input type="hidden" name="post_id" value="<?php echo $this->post_id ?>" />
                        <input type="hidden" id="image-data" name="image_data" />
                        <input type="hidden" id="audio-data" name="audio_data" />
                        <input type="hidden" id="video-data" name="video_data" />
                        <input class="button-primary" type="submit" name="save" value="<?php _e( 'Click to Re-Order', 'shuffle' ) ?>" />
                    </fieldset>
                </form>	
                <?php else:	?>
                <p><strong><?php _e( 'This item has no attachments.' ) ?></strong></p>
                <?php endif;

            else: ?>
                <p>
                    <strong><?php 
                        _e( "Please select an item (Post, Page, Custom Post Type) to shuffle. After you have selected the post, you can reorder its attachments!", 'shuffle' ) 
                    ?></strong>
                </p>
                <p><?php _e( 'Shuffle modifies/improves your Media Library in a number of ways. Shuffle lets you:', 'shuffle' ) ?></p>
                <ol>
                    <li><?php _e( 'Attach an item (Image, Audio, Video) to anything (Post, Page, Custom Post Type, another Attachment)!', 'shuffle' ) ?></li>
                    <li><?php _e( "Reorder an item's attachments using a simple Drag and Drop UI (more than just the Gallery)", 'shuffle' ) ?></li>
                    <li><?php _e( 'Detach an attachment from an item without deleting the attachment', 'shuffle' ) ?></li>
                    <li><?php _e( 'View all attachments attached to an item', 'shuffle' ) ?></li>
                </ol>
            <?php endif; 
		?></div>
		<?php
	}
	
	function add_attachment_type_callback() {
		global $wpdb;

		if ( empty( $_POST['ps'] ) )
			exit;

		if ( !empty( $_POST['post_type'] ) && in_array( $_POST['post_type'], get_post_types() ) )
			$what = $_POST['post_type'];
		else
			$what = 'post';

		$s = stripslashes( $_POST['ps'] );
		preg_match_all( '/".*?("|$)|( (?<=[\\s",+])|^)[^\\s",+]+/', $s, $matches );
		$search_terms = array_map( '_search_terms_tidy', $matches[0] );

		$searchand = $search = '';
		foreach ( (array) $search_terms as $term ) {
			$term = addslashes_gpc( $term );
			$search .= "{$searchand}( ( $wpdb->posts.post_title LIKE '%{$term}%' ) OR ( $wpdb->posts.post_content LIKE '%{$term}%' ) )";
			$searchand = ' AND ';
		}
		$term = $wpdb->escape( $s );
		if ( count( $search_terms ) > 1 && $search_terms[0] != $s )
			$search .= " OR ( $wpdb->posts.post_title LIKE '%{$term}%' ) OR ( $wpdb->posts.post_content LIKE '%{$term}%' )";

		$extra_type = '';
		if ( 'attachment' === $what )
			$extra_type = ", 'inherit'";

		/**
		 * We also need to keep a reference to the original unattached item so that we can't
		 * attach it to itself
		 *
		 */	
		$not_me = '';
		if ( isset( $_POST['item_id'] ) ) {
			if ( strstr( $_POST['item_id'], ',' ) ) {
				$id_list = $_POST['item_id'];
				$not_me = "AND ID NOT IN ( $id_list)";
			} else {
				$not_me = 'AND ID != ' . $_POST['item_id'];		
			}
		} 

		/**
		 * Modified SQL
		 *
		 */	 
		$posts = $wpdb->get_results( "SELECT ID, post_title, post_status, post_date FROM $wpdb->posts WHERE post_type = '$what' AND post_status IN ( 'draft', 'publish'$extra_type) AND ( $search) $not_me ORDER BY post_date_gmt DESC LIMIT 50" );

		if ( ! $posts ) {
			$posttype = get_post_type_object( $what );
			exit( $posttype->labels->not_found );
		}

		$html = '<table class="widefat" cellspacing="0"><thead><tr><th class="found-radio"><br /></th><th>'.__( 'Title' ).'</th><th>'.__( 'Date' ).'</th><th>'.__( 'Status' ).'</th></tr></thead><tbody>';
		foreach ( $posts as $post ) {

			switch ( $post->post_status ) {
				case 'publish' :
				case 'private' :
					$stat = __( 'Published' );
					break;
				case 'future' :
					$stat = __( 'Scheduled' );
					break;
				case 'pending' :
					$stat = __( 'Pending Review' );
					break;
				case 'draft' :
					$stat = __( 'Draft' );
					break;
			}

			if ( '0000-00-00 00:00:00' == $post->post_date ) {
				$time = '';
			} else {
				/* translators: date format in table columns, see http://php.net/date */
				$time = mysql2date( __( 'Y/m/d' ), $post->post_date );
			}

			$html .= '<tr class="found-posts"><td class="found-radio"><input type="radio" id="found-' . $post->ID . '" name="found_post_id" value="' . esc_attr( $post->ID) . '"></td>';
			$html .= '<td><label for="found-' . $post->ID . '">' . esc_html( $post->post_title ) . '</label></td><td>' . esc_html( $time ) . '</td><td>' . esc_html( $stat ) . '</td></tr>' . "\n\n";
		}
		$html .= '</tbody></table>';

		$x = new WP_Ajax_Response();
		$x->add(array(
			'what' => $what,
			'data' => $html
		) );
		$x->send();
	}
}

global $_shuffle_plugin;
$_shuffle_plugin = new Shuffle();
$_shuffle_plugin->init(); 	

/**
 * THEME FUNCTIONS
 *
 *
 * all of these function will also take an array() as the only argument (same params as get_posts)
 *
 * add 'and_featured' => true 
 * to include the post's featured image / post thumbnail in the result, it is excluded by default
 *
 * if shuffle_by_mime_type() is called directly, you can pass true / false as the 3rd argument to 
 * return the post's featured image / post thumbnail
 *
 */ 	 
function shuffle_by_mime_type( $type = '', $id = '', $include_featured = false ) {
    global $_shuffle_plugin;
    return $_shuffle_plugin->by_mime_type( $type, $id, $include_featured );
}
 	
if ( !function_exists( 'get_images' ) ) {
    function get_images( $id = '' ) {
        return shuffle_by_mime_type( 'image', $id );
    }	    
}

if ( !function_exists( 'get_audio' ) ) {
    function get_audio( $id = '' ) {
        return shuffle_by_mime_type( 'audio', $id );
    }	    
}

if ( !function_exists( 'get_video' ) ) {
    function get_video( $id = '' ) {
        return shuffle_by_mime_type( 'video', $id );
    }	    
}