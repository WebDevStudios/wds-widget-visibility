<?php
/**
* Plugin Name: WDS Widget Visibility
* Plugin URI:  http://webdevstudios.com
* Description: Fork of Jetpack's widget conditions module. Allows an admin full control over the visibility of widgets.
* Version:     1.0.0
* Author:      WebDevStudios
* Author URI:  http://webdevstudios.com
* Donate link: http://webdevstudios.com
* License:     GPLv2
* Text Domain: wds-widget-visibility
 * Domain Path: /languages
 */

/**
 * Copyright (c) 2015 WebDevStudios (email : contact@webdevstudios.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */


/**
 * Main initiation class
 */
class WDS_Widget_Visibility {
	static $passed_template_redirect = false;

	public static function init() {
		if ( is_admin() ) {
			add_action( 'sidebar_admin_setup', array( __CLASS__, 'widget_admin_setup' ) );
			add_filter( 'widget_update_callback', array( __CLASS__, 'widget_update' ), 10, 3 );
			add_action( 'in_widget_form', array( __CLASS__, 'widget_conditions_admin' ), 10, 3 );
			add_action( 'wp_ajax_widget_conditions_options', array( __CLASS__, 'widget_conditions_options' ) );
			add_action( 'load-widgets.php', array( __CLASS__, 'contextual_help' ) );
		}
		else {
			add_filter( 'widget_display_callback', array( __CLASS__, 'filter_widget' ) );
			add_filter( 'sidebars_widgets', array( __CLASS__, 'sidebars_widgets' ) );
			add_action( 'template_redirect', array( __CLASS__, 'template_redirect' ) );
		}

		// Load Textdomain
		load_plugin_textdomain( 'wds-widget-visibility', false, plugins_url( 'languages', __FILE__ ) );
	}

	public static function widget_admin_setup() {
		if( is_rtl() ) {
			wp_enqueue_style( 'widget-conditions', plugins_url( 'assets/rtl/widget-conditions-rtl.css', __FILE__ ) );
		} else {
			wp_enqueue_style( 'widget-conditions', plugins_url( 'assets/widget-conditions.css', __FILE__ ) );
		}
		wp_enqueue_style( 'widget-conditions', plugins_url( 'assets/widget-conditions.css', __FILE__ ) );
		wp_enqueue_script( 'widget-conditions', plugins_url( 'assets/widget-conditions.js', __FILE__ ), array( 'jquery', 'jquery-ui-core' ), 20140721, true );
	}

	/**
	 * Provided a second level of granularity for widget conditions.
	 */
	public static function widget_conditions_options_echo( $major = '', $minor = '' ) {
		switch ( $major ) {
			case 'category':
				?>
				<option value=""><?php _e( 'All category pages', 'wds-widget-visibility' ); ?></option>
				<?php

				$categories = get_categories( array( 'number' => 1000, 'orderby' => 'count', 'order' => 'DESC' ) );
				usort( $categories, array( __CLASS__, 'strcasecmp_name' ) );

				foreach ( $categories as $category ) {
					?>
					<option value="<?php echo esc_attr( $category->term_id ); ?>" <?php selected( $category->term_id, $minor ); ?>><?php echo esc_html( $category->name ); ?></option>
					<?php
				}
			break;
			case 'loggedin':
				?>
				<option value="loggedin" <?php selected( 'loggedin', $minor ); ?>><?php _e( 'Logged In', 'wds-widget-visibility' ); ?></option>
				<option value="loggedout" <?php selected( 'loggedout', $minor ); ?>><?php _e( 'Logged Out', 'wds-widget-visibility' ); ?></option>
				<?php
			break;
			case 'author':
				?>
				<option value=""><?php _e( 'All author pages', 'wds-widget-visibility' ); ?></option>
				<?php

				foreach ( get_users( array( 'orderby' => 'name', 'exclude_admin' => true ) ) as $author ) {
					?>
					<option value="<?php echo esc_attr( $author->ID ); ?>" <?php selected( $author->ID, $minor ); ?>><?php echo esc_html( $author->display_name ); ?></option>
					<?php
				}
			break;
			case 'role':
				global $wp_roles;

				foreach ( $wp_roles->roles as $role_key => $role ) {
					?>
					<option value="<?php echo esc_attr( $role_key ); ?>" <?php selected( $role_key, $minor ); ?> ><?php echo esc_html( $role['name'] ); ?></option>
					<?php
				}
			break;
			case 'tag':
				?>
				<option value=""><?php _e( 'All tag pages', 'wds-widget-visibility' ); ?></option>
				<?php

				$tags = get_tags( array( 'number' => 1000, 'orderby' => 'count', 'order' => 'DESC' ) );
				usort( $tags, array( __CLASS__, 'strcasecmp_name' ) );

				foreach ( $tags as $tag ) {
					?>
					<option value="<?php echo esc_attr($tag->term_id ); ?>" <?php selected( $tag->term_id, $minor ); ?>><?php echo esc_html( $tag->name ); ?></option>
					<?php
				}
			break;
			case 'date':
				?>
				<option value="" <?php selected( '', $minor ); ?>><?php _e( 'All date archives', 'wds-widget-visibility' ); ?></option>
				<option value="day"<?php selected( 'day', $minor ); ?>><?php _e( 'Daily archives', 'wds-widget-visibility' ); ?></option>
				<option value="month"<?php selected( 'month', $minor ); ?>><?php _e( 'Monthly archives', 'wds-widget-visibility' ); ?></option>
				<option value="year"<?php selected( 'year', $minor ); ?>><?php _e( 'Yearly archives', 'wds-widget-visibility' ); ?></option>
				<?php
			break;
			case 'page':
				// Previously hardcoded post type options.
				if ( ! $minor )
					$minor = 'post_type-page';
				else if ( 'post' == $minor )
					$minor = 'post_type-post';

				?>
				<option value="front" <?php selected( 'front', $minor ); ?>><?php _e( 'Front page', 'wds-widget-visibility' ); ?></option>
				<option value="posts" <?php selected( 'posts', $minor ); ?>><?php _e( 'Posts page', 'wds-widget-visibility' ); ?></option>
				<option value="archive" <?php selected( 'archive', $minor ); ?>><?php _e( 'Archive page', 'wds-widget-visibility' ); ?></option>
				<option value="404" <?php selected( '404', $minor ); ?>><?php _e( '404 error page', 'wds-widget-visibility' ); ?></option>
				<option value="search" <?php selected( 'search', $minor ); ?>><?php _e( 'Search results', 'wds-widget-visibility' ); ?></option>
				<optgroup label="<?php esc_attr_e( 'Post type:', 'wds-widget-visibility' ); ?>">
					<?php

					$post_types = get_post_types( array( 'public' => true ), 'objects' );

					foreach ( $post_types as $post_type ) {
						?>
						<option value="<?php echo esc_attr( 'post_type-' . $post_type->name ); ?>" <?php selected( 'post_type-' . $post_type->name, $minor ); ?>><?php echo esc_html( $post_type->labels->singular_name ); ?></option>
						<?php
					}

					?>
				</optgroup>
				<optgroup label="<?php esc_attr_e( 'Static page:', 'wds-widget-visibility' ); ?>">
					<?php

					echo str_replace( ' value="' . esc_attr( $minor ) . '"', ' value="' . esc_attr( $minor ) . '" selected="selected"', preg_replace( '/<\/?select[^>]*?>/i', '', wp_dropdown_pages( array( 'echo' => false ) ) ) );

					?>
				</optgroup>
				<?php
			break;
			case 'taxonomy':
				?>
				<option value=""><?php _e( 'All taxonomy pages', 'wds-widget-visibility' ); ?></option>
				<?php

				$taxonomies = get_taxonomies( array( '_builtin' => false ), 'objects' );
				usort( $taxonomies, array( __CLASS__, 'strcasecmp_name' ) );

				foreach ( $taxonomies as $taxonomy ) {
					?>
					<optgroup label="<?php esc_attr_e( $taxonomy->labels->name . ':', 'wds-widget-visibility' ); ?>">
						<option value="<?php echo esc_attr( $taxonomy->name ); ?>" <?php selected( $taxonomy->name, $minor ); ?>><?php echo 'All ' . esc_html( $taxonomy->name ) . ' pages'; ?></option>
					<?php

					$terms = get_terms( array( $taxonomy->name ), array( 'number' => 250, 'hide_empty' => false ) );
					foreach ( $terms as $term ) {
						?>
						<option value="<?php echo esc_attr( $taxonomy->name . '_tax_' . $term->term_id ); ?>" <?php selected( $taxonomy->name . '_tax_' . $term->term_id, $minor ); ?>><?php echo esc_html( $term->name ); ?></option>
						<?php
					}

					?>
				</optgroup>
				<?php
				}
			break;
		}
	}

	/**
	 * This is the AJAX endpoint for the second level of conditions.
	 */
	public static function widget_conditions_options() {
		self::widget_conditions_options_echo( $_REQUEST['major'], isset( $_REQUEST['minor'] ) ? $_REQUEST['minor'] : '' );
		die;
	}

	/**
	 * Add the widget conditions to each widget in the admin.
	 *
	 * @param $widget unused.
	 * @param $return unused.
	 * @param array $instance The widget settings.
	 */
	public static function widget_conditions_admin( $widget, $return, $instance ) {
		$conditions = array();

		if ( isset( $instance['conditions'] ) )
			$conditions = $instance['conditions'];

		if ( ! isset( $conditions['action'] ) )
			$conditions['action'] = 'show';

		if ( empty( $conditions['rules'] ) )
			$conditions['rules'][] = array( 'major' => '', 'minor' => '' );

		?>
		<div class="widget-conditional <?php if ( empty( $_POST['widget-conditions-visible'] ) || $_POST['widget-conditions-visible'] == '0' ) { ?>widget-conditional-hide<?php } ?>">
			<input type="hidden" name="widget-conditions-visible" value="<?php if ( isset( $_POST['widget-conditions-visible'] ) ) { echo esc_attr( $_POST['widget-conditions-visible'] ); } else { ?>0<?php } ?>" />
			<?php if ( ! isset( $_POST['widget-conditions-visible'] ) ) { ?><a href="#" class="button display-options"><?php _e( 'Visibility', 'wds-widget-visibility' ); ?></a><?php } ?>
			<div class="widget-conditional-inner">
				<div class="condition-top">
					<?php printf( _x( '%s if:', 'placeholder: dropdown menu to select widget visibility; hide if or show if', 'wds-widget-visibility' ), '<select name="conditions[action]"><option value="show" ' . selected( $conditions['action'], 'show', false ) . '>' . esc_html_x( 'Show', 'Used in the "%s if:" translation for the widget visibility dropdown', 'wds-widget-visibility' ) . '</option><option value="hide" ' . selected( $conditions['action'], 'hide', false ) . '>' . esc_html_x( 'Hide', 'Used in the "%s if:" translation for the widget visibility dropdown', 'wds-widget-visibility' ) . '</option></select>' ); ?>
				</div><!-- .condition-top -->

				<div class="conditions">
					<?php

					foreach ( $conditions['rules'] as $rule ) {
						?>
						<div class="condition">
							<div class="selection alignleft">
								<select class="conditions-rule-major" name="conditions[rules_major][]">
									<option value="" <?php selected( "", $rule['major'] ); ?>><?php echo esc_html_x( '-- Select --', 'Used as the default option in a dropdown list', 'wds-widget-visibility' ); ?></option>
									<option value="category" <?php selected( "category", $rule['major'] ); ?>><?php esc_html_e( 'Category', 'wds-widget-visibility' ); ?></option>
									<option value="author" <?php selected( "author", $rule['major'] ); ?>><?php echo esc_html_x( 'Author', 'Noun, as in: "The author of this post is..."', 'wds-widget-visibility' ); ?></option>
									<?php
									// this doesn't work on .com because of caching
									if( ! ( defined( 'IS_WPCOM' ) && IS_WPCOM ) ) {
									?>
									<option value="loggedin" <?php selected( "loggedin", $rule['major'] ); ?>><?php echo esc_html_x( 'User', 'Noun', 'wds-widget-visibility' ); ?></option>
									<option value="role" <?php selected( "role", $rule['major'] ); ?>><?php echo esc_html_x( 'Role', 'Noun, as in: "The user role of that can access this widget is..."', 'wds-widget-visibility' ); ?></option>
									<?php } ?>
									<option value="tag" <?php selected( "tag", $rule['major'] ); ?>><?php echo esc_html_x( 'Tag', 'Noun, as in: "This post has one tag."', 'wds-widget-visibility' ); ?></option>
									<option value="date" <?php selected( "date", $rule['major'] ); ?>><?php echo esc_html_x( 'Date', 'Noun, as in: "This page is a date archive."', 'wds-widget-visibility' ); ?></option>
									<option value="page" <?php selected( "page", $rule['major'] ); ?>><?php echo esc_html_x( 'Page', 'Example: The user is looking at a page, not a post.', 'wds-widget-visibility' ); ?></option>
									<?php if ( get_taxonomies( array( '_builtin' => false ) ) ) : ?>
									<option value="taxonomy" <?php selected( "taxonomy", $rule['major'] ); ?>><?php echo esc_html_x( 'Taxonomy', 'Noun, as in: "This post has one taxonomy."', 'wds-widget-visibility' ); ?></option>
									<?php endif; ?>
								</select>
								<?php _ex( 'is', 'Widget Visibility: {Rule Major [Page]} is {Rule Minor [Search results]}', 'wds-widget-visibility' ); ?>
								<select class="conditions-rule-minor" name="conditions[rules_minor][]" <?php if ( ! $rule['major'] ) { ?> disabled="disabled"<?php } ?> data-loading-text="<?php esc_attr_e( 'Loading...', 'wds-widget-visibility' ); ?>">
									<?php self::widget_conditions_options_echo( $rule['major'], $rule['minor'] ); ?>
								</select>

							</div>
							<div class="condition-control">
							 <span class="condition-conjunction"><?php echo esc_html_x( 'or', 'Shown between widget visibility conditions.', 'wds-widget-visibility' ); ?></span>
							 <div class="actions alignright">
								<a href="#" class="delete-condition"><?php esc_html_e( 'Delete', 'wds-widget-visibility' ); ?></a> | <a href="#" class="add-condition"><?php esc_html_e( 'Add', 'wds-widget-visibility' ); ?></a>
							 </div>
							</div>

						</div><!-- .condition -->
						<?php
					}

					?>
				</div><!-- .conditions -->
			</div><!-- .widget-conditional-inner -->
		</div><!-- .widget-conditional -->
		<?php
	}

	/**
	 * On an AJAX update of the widget settings, process the display conditions.
	 *
	 * @param array $new_instance New settings for this instance as input by the user.
	 * @param array $old_instance Old settings for this instance.
	 * @return array Modified settings.
	 */
	public static function widget_update( $instance, $new_instance, $old_instance ) {
		$conditions = array();
		$conditions['action'] = $_POST['conditions']['action'];
		$conditions['rules'] = array();

		foreach ( $_POST['conditions']['rules_major'] as $index => $major_rule ) {
			if ( ! $major_rule )
				continue;

			$conditions['rules'][] = array(
				'major' => $major_rule,
				'minor' => isset( $_POST['conditions']['rules_minor'][$index] ) ? $_POST['conditions']['rules_minor'][$index] : ''
			);
		}

		if ( ! empty( $conditions['rules'] ) )
			$instance['conditions'] = $conditions;
		else
			unset( $instance['conditions'] );

		if (
				( isset( $instance['conditions'] ) && ! isset( $old_instance['conditions'] ) )
				||
				(
					isset( $instance['conditions'], $old_instance['conditions'] )
					&&
					serialize( $instance['conditions'] ) != serialize( $old_instance['conditions'] )
				)
			) {
			do_action( 'widget_conditions_save' );
		}
		else if ( ! isset( $instance['conditions'] ) && isset( $old_instance['conditions'] ) ) {
			do_action( 'widget_conditions_delete' );
		}

		return $instance;
	}

	/**
	 * Filter the list of widgets for a sidebar so that active sidebars work as expected.
	 *
	 * @param array $widget_areas An array of widget areas and their widgets.
	 * @return array The modified $widget_area array.
	 */
	public static function sidebars_widgets( $widget_areas ) {
		$settings = array();

		foreach ( $widget_areas as $widget_area => $widgets ) {
			if ( empty( $widgets ) )
				continue;

			if ( 'wp_inactive_widgets' == $widget_area )
				continue;

			foreach ( $widgets as $position => $widget_id ) {
				// Find the conditions for this widget.
				if ( preg_match( '/^(.+?)-(\d+)$/', $widget_id, $matches ) ) {
					$id_base = $matches[1];
					$widget_number = intval( $matches[2] );
				}
				else {
					$id_base = $widget_id;
					$widget_number = null;
				}

				if ( ! isset( $settings[$id_base] ) ) {
					$settings[$id_base] = get_option( 'widget_' . $id_base );
				}

				// New multi widget (WP_Widget)
				if ( ! is_null( $widget_number ) ) {
					if ( isset( $settings[$id_base][$widget_number] ) && false === self::filter_widget( $settings[$id_base][$widget_number] ) ) {
						unset( $widget_areas[$widget_area][$position] );
					}
				}

				// Old single widget
				else if ( ! empty( $settings[ $id_base ] ) && false === self::filter_widget( $settings[$id_base] ) ) {
					unset( $widget_areas[$widget_area][$position] );
				}
			}
		}

		return $widget_areas;
	}

	public static function template_redirect() {
		self::$passed_template_redirect = true;
	}

	/**
	 * Determine whether the widget should be displayed based on conditions set by the user.
	 *
	 * @param array $instance The widget settings.
	 * @return array Settings to display or bool false to hide.
	 */
	public static function filter_widget( $instance ) {
		global $wp_query;

		if ( empty( $instance['conditions'] ) || empty( $instance['conditions']['rules'] ) )
			return $instance;

		// Store the results of all in-page condition lookups so that multiple widgets with
		// the same visibility conditions don't result in duplicate DB queries.
		static $condition_result_cache = array();

		$condition_result = false;

		foreach ( $instance['conditions']['rules'] as $rule ) {
			$condition_key = $rule['major'] . ":" . $rule['minor'];

			if ( isset( $condition_result_cache[ $condition_key ] ) ) {
				$condition_result = $condition_result_cache[ $condition_key ];
			}
			else {
				switch ( $rule['major'] ) {
					case 'date':
						switch ( $rule['minor'] ) {
							case '':
								$condition_result = is_date();
							break;
							case 'month':
								$condition_result = is_month();
							break;
							case 'day':
								$condition_result = is_day();
							break;
							case 'year':
								$condition_result = is_year();
							break;
						}
					break;
					case 'page':
						// Previously hardcoded post type options.
						if ( 'post' == $rule['minor'] )
							$rule['minor'] = 'post_type-post';
						else if ( ! $rule['minor'] )
							$rule['minor'] = 'post_type-page';

						switch ( $rule['minor'] ) {
							case '404':
								$condition_result = is_404();
							break;
							case 'search':
								$condition_result = is_search();
							break;
							case 'archive':
								$condition_result = is_archive();
							break;
							case 'posts':
								$condition_result = $wp_query->is_posts_page;
							break;
							case 'home':
								$condition_result = is_home();
							break;
							case 'front':
								if ( current_theme_supports( 'infinite-scroll' ) )
									$condition_result = is_front_page();
								else {
									$condition_result = is_front_page() && !is_paged();
								}
							break;
							default:
								if ( substr( $rule['minor'], 0, 10 ) == 'post_type-' ) {
									$condition_result = is_singular( substr( $rule['minor'], 10 ) );
								} elseif ( $rule['minor'] == get_option( 'page_for_posts' ) ) {
									// If $rule['minor'] is a page ID which is also the posts page
									$condition_result = $wp_query->is_posts_page;
								} else {
									// $rule['minor'] is a page ID
									$condition_result = is_page( $rule['minor'] );
								}
							break;
						}
					break;
					case 'tag':
						if ( ! $rule['minor'] && is_tag() )
							$condition_result = true;
						else if ( is_singular() && $rule['minor'] && has_tag( $rule['minor'] ) )
							$condition_result = true;
						else {
							$tag = get_tag( $rule['minor'] );

							if ( $tag && is_tag( $tag->slug ) )
								$condition_result = true;
						}
					break;
					case 'category':
						if ( ! $rule['minor'] && is_category() )
							$condition_result = true;
						else if ( is_category( $rule['minor'] ) )
							$condition_result = true;
						else if ( is_singular() && $rule['minor'] && in_array( 'category', get_post_taxonomies() ) &&  has_category( $rule['minor'] ) )
							$condition_result = true;
					break;
					case 'loggedin':
						$condition_result = is_user_logged_in();
						if ( 'loggedin' !== $rule['minor'] ) {
						    $condition_result = ! $condition_result;
						}
					break;
					case 'author':
						$post = get_post();
						if ( ! $rule['minor'] && is_author() )
							$condition_result = true;
						else if ( $rule['minor'] && is_author( $rule['minor'] ) )
							$condition_result = true;
						else if ( is_singular() && $rule['minor'] && $rule['minor'] == $post->post_author )
							$condition_result = true;
					break;
					case 'role':
						if( is_user_logged_in() ) {
							global $current_user;
							get_currentuserinfo();

							$user_roles = $current_user->roles;

							if( in_array( $rule['minor'], $user_roles ) ) {
								$condition_result = true;
							} else {
								$condition_result = false;
							}

						} else {
							$condition_result = false;
						}
					break;
					case 'taxonomy':
						$term = explode( '_tax_', $rule['minor'] ); // $term[0] = taxonomy name; $term[1] = term id

						if ( isset( $term[1] ) && is_tax( $term[0], $term[1] ) )
							$condition_result = true;
						else if ( isset( $term[1] ) && is_singular() && $term[1] && has_term( $term[1], $term[0] ) )
							$condition_result = true;
						else if ( is_singular() && $post_id = get_the_ID() ){
							$terms = get_the_terms( $post_id, $rule['minor'] ); // Does post have terms in taxonomy?
							if( $terms && ! is_wp_error( $terms ) ) {
								$condition_result = true;
							}
						}
					break;
				}

				if ( $condition_result || self::$passed_template_redirect ) {
					// Some of the conditions will return false when checked before the template_redirect
					// action has been called, like is_page(). Only store positive lookup results, which
					// won't be false positives, before template_redirect, and everything after.
					$condition_result_cache[ $condition_key ] = $condition_result;
				}
			}

			if ( $condition_result )
				break;
		}

		if ( ( 'show' == $instance['conditions']['action'] && ! $condition_result ) || ( 'hide' == $instance['conditions']['action'] && $condition_result ) )
			return false;

		return $instance;
	}

	public static function strcasecmp_name( $a, $b ) {
		return strcasecmp( $a->name, $b->name );
	}


	/**
	 * Add a contextual help tab for the widget visibility component
	 * @return void
	 */
	public static function contextual_help() {
		$screen = get_current_screen();

		// this will prevent the add_help_tab from being added to admin-ajax.php
		if ( $screen ) {
			$screen->add_help_tab( array(
				'id' => 'wds-widget-visibility',
				'title' => __( 'Widget Visibility', 'wds-widget-visibility' ),
				'content' => '',
				'callback' => array( __CLASS__, 'contextual_help_content' )
			) );
		}

	}

	/**
	 * Display the actual help tab content
	 * @return void
	 */
	public static function contextual_help_content() {
		$content1 = __( 'You can control the visibility of widgets using the Visibility button located next to the Save button on widgets. Clicking that button will open a panel that gives you full control over that widget\'s visibility. You can add the widget to all posts, pages, or custom post types, or specific posts, pages, post types by selecting <strong>Show</strong> in the first dropdown, then use the following dropdowns to specify where you want the widget to show.', 'wds-widget-visibility' );
		$content2 = __( 'You can also show the widget on all pages <em>except</em> those that you specify using the <strong>Hide</strong> option in the first dropdown. The controls are the same, the resultant widget will display on all pages <em>except</em> the one(s) that you specified.', 'wds-widget-visibility' );
		$content3 = __( 'Additional conditions can be added by clicking the <strong>Add</strong> link to the right of each condition. Conditions can be removed by clicking the <strong>Delete</strong> link.', 'wds-widget-visibility' );
		$content = sprintf( '<p>%1$s</p><p>%2$s</p><p>%3$s', $content1, $content2, $content3 );

		echo $content;
	}

}

add_action( 'init', array( 'WDS_Widget_Visibility', 'init' ) );
