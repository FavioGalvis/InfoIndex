<?php
# MantisBT - A PHP based bugtracking system

# MantisBT is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 2 of the License, or
# (at your option) any later version.
#
# MantisBT is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with MantisBT.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Layout API
 *
 * UI functions to render layout elements in every page. The layout api layer sits above the html api and abstract
 * the lower level html markup into web components
 *
 * Here is the call order for the layout functions
 *
 * layout_page_header
 *      layout_page_header_begin
 *      layout_page_header_end
 * layout_page_begin
 *      ...Page content here...
 * layout_page_end
 *
 *
 *
 * @package CoreAPI
 * @subpackage LayoutAPI
 * @copyright Copyright 2014 MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses access_api.php
 * @uses utility_api.php
 */


require_api( 'access_api.php' );
require_api( 'utility_api.php' );


/**
 * Print the page header section
 * @param string $p_page_title   Html page title.
 * @param string $p_redirect_url URL to redirect to if necessary.
 * @return void
 */
function layout_page_header( $p_page_title = null, $p_redirect_url = null ) {
	layout_page_header_begin( $p_page_title );
	if( $p_redirect_url !== null ) {
		html_meta_redirect( $p_redirect_url );
	}
	layout_page_header_end();
}

/**
 * Print the part of the page that comes before meta redirect tags should be inserted
 * @param string $p_page_title Page title.
 * @return void
 */
function layout_page_header_begin( $p_page_title = null ) {
	html_begin();
	html_head_begin();
	html_content_type();

	global $g_robots_meta;
	if( !is_blank( $g_robots_meta ) ) {
		echo "\t", '<meta name="robots" content="', $g_robots_meta, '" />', "\n";
	}

	html_title( $p_page_title );
	layout_head_meta();
	html_css();
	layout_head_css();
	html_rss_link();

	$t_favicon_image = config_get( 'favicon_image' );
	if( !is_blank( $t_favicon_image ) ) {
		echo "\t", '<link rel="shortcut icon" href="', helper_mantis_url( $t_favicon_image ), '" type="image/x-icon" />', "\n";
	}

	# Advertise the availability of the browser search plug-ins.
	echo "\t", '<link rel="search" type="application/opensearchdescription+xml" title="MantisBT: Text Search" href="' . string_sanitize_url( 'browser_search_plugin.php?type=text', true ) . '" />' . "\n";
	echo "\t", '<link rel="search" type="application/opensearchdescription+xml" title="MantisBT: Issue Id" href="' . string_sanitize_url( 'browser_search_plugin.php?type=id', true ) . '" />' . "\n";

	html_head_javascript();
}

/**
 * Print the part of the page that comes after meta tags and before the
 *  actual page content, but without login info or menus.  This is used
 *  directly during the login process and other times when the user may
 *  not be authenticated
 * @return void
 */
function layout_page_header_end() {
	global $g_error_send_page_header;

	event_signal( 'EVENT_LAYOUT_RESOURCES' );
	html_head_end();

	# Add right-to-left css if needed
	if( layout_is_rtl() ) {
		echo '<body class="skin-3 rtl">', "\n";
	} else {
		echo '<body class="skin-3">', "\n";
	}
	event_signal( 'EVENT_LAYOUT_BODY_BEGIN' );

	$g_error_send_page_header = false;
}

/**
 * Print page common elements including navbar, sidebar, info bar
 * @param string $p_active_sidebar_page sidebar page where the current page lives under
 * @return void
 */
function layout_page_begin( $p_active_sidebar_page = null ) {
	layout_navbar();

	if( !db_is_connected() ) {
		return;
	}

	layout_main_container_begin();

	layout_print_sidebar( $p_active_sidebar_page );

	layout_main_content_begin();

	layout_breadcrumbs();

	layout_page_content_begin();

	if( auth_is_user_authenticated() ) {
		if( ON == config_get( 'show_project_menu_bar' ) ) {
			print_project_menu_bar();
		}
	}

	event_signal( 'EVENT_LAYOUT_CONTENT_BEGIN' );
}

/**
 * Print elements at the end of each page
 * @param string $p_file Should always be the __FILE__ variable.
 * @return void
 */
function layout_page_end( $p_file = null ) {
	if( !db_is_connected() ) {
		return;
	}

	event_signal( 'EVENT_LAYOUT_CONTENT_END' );

	layout_page_content_end();
	layout_main_content_end();

	layout_footer();
	layout_scroll_up_button();

	layout_main_container_end();
	layout_body_javascript();

	html_body_end();
	html_end();
}

/**
 * Print common elements for admin pages
 * @return void
 */
function layout_admin_page_begin() {
	layout_navbar();

	layout_main_container_begin();
}

/**
 * Print elements at the end of each admin page
 * @return void
 */
function layout_admin_page_end() {
	layout_footer();
	layout_scroll_up_button();

	layout_main_container_end();
	layout_body_javascript();

	html_body_end();
    html_end();
}



/**
 * Check if the layout is setup for right to left languages
 * @return bool
 */
function layout_is_rtl() {
	if( lang_get( 'directionality' ) == 'rtl' ) {
		return true;
	}
	return false;
}

/**
 * Print meta tags for the page head
 * @return null
 */
function layout_head_meta() {
	# use the following meta to force IE use its most up to date rendering engine
	echo '<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />' . "\n";

	echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" />' . "\n";
}

/**
 * Print css link directives for the head section of the page
 * @return null
 */
function layout_head_css() {
	# bootstrap & fontawesome
	html_css_cdn_link( '//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css' );
	html_css_cdn_link( '//maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css' );

	# page specific plugin styles

	# theme text fonts
	html_css_link( 'ace-fonts.css' );

	# theme styles -->
	html_css_link( 'ace.min.css' );
	html_css_link( 'ace-mantis.css' );

	# handle IE separately
	echo '<!--[if lte IE 9]>';
	html_css_link( 'ace-part2.min.css' );
	echo '<![endif]-->';
	html_css_link( 'ace-skins.min.css' );

	if( layout_is_rtl() ) {
		html_css_link( 'ace-rtl.min.css' );
	}

	echo '<!--[if lte IE 9]>';
	html_css_link( 'ace-ie.min.css' );
	echo '<![endif]-->';
	echo "\n";
}


/**
 * Print javascript directives for the head section of the page
 * @return null
 */
function layout_head_javascript() {
	# HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries
	echo '<!--[if lte IE 8]>';
	html_javascript_link( 'html5shiv.min.js' );
	html_javascript_link( 'respond.min.js' );
	echo '<![endif]-->';
	echo "\n";
}


/**
 * Print javascript directives before the closing of the page body element
 * @return null
 */
function layout_body_javascript() {
	# bootstrap
	html_javascript_cdn_link( '//maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js' );

	# theme scripts
	html_javascript_link( 'ace-extra.min.js' );
	html_javascript_link( 'ace-elements.min.js' );
	html_javascript_link( 'ace.min.js' );
}


/**
 * Print opening markup for login/signup/register pages
 * @return null
 */
function layout_login_page_begin() {
	html_begin();
	html_head_begin();
	html_content_type();

	global $g_robots_meta;
	if( !is_blank( $g_robots_meta ) ) {
		echo "\t", '<meta name="robots" content="', $g_robots_meta, '" />', "\n";
	}

	html_title();
	layout_head_meta();
	html_css();
	layout_head_css();
	html_rss_link();

	$t_favicon_image = config_get( 'favicon_image' );
	if( !is_blank( $t_favicon_image ) ) {
		echo "\t", '<link rel="shortcut icon" href="', helper_mantis_url( $t_favicon_image ), '" type="image/x-icon" />', "\n";
	}

	# Advertise the availability of the browser search plug-ins.
	echo "\t", '<link rel="search" type="application/opensearchdescription+xml" title="MantisBT: Text Search" href="' . string_sanitize_url( 'browser_search_plugin.php?type=text', true) . '" />' . "\n";
	echo "\t", '<link rel="search" type="application/opensearchdescription+xml" title="MantisBT: Issue Id" href="' . string_sanitize_url( 'browser_search_plugin.php?type=id', true) . '" />' . "\n";

	html_head_javascript();

	event_signal( 'EVENT_LAYOUT_RESOURCES' );
	html_head_end();

	echo '<body class="login-layout light-login">';
	layout_main_container_begin();
	layout_main_content_begin();
	echo '<div class="row">';
}

/**
 * Print closing markup for login/signup/register pages
 * @return null
 */
function layout_login_page_end() {
	echo '</div>';
	layout_main_content_end();
	layout_main_container_end();
	layout_body_javascript();

	echo '</body>', "\n";
}

/**
 * Render navbar at the top of the page
 * @return null
 */
function layout_navbar() {
	$t_logo_url = config_get('logo_url');

	echo '<div id="navbar" class="navbar navbar-default navbar-collapse navbar-fixed-top">';

	echo '<div id="navbar-container" class="navbar-container">';

	echo '<div class="navbar-header pull-left">';
	echo '<a href="' . $t_logo_url . '" class="navbar-brand">';
	echo '<span class="smaller-75"> ';
	echo config_get('window_title');
	echo ' </span>';
	echo '</a>';

	$t_toggle_class = (OFF == config_get('show_avatar') ? 'navbar-toggle' : 'navbar-toggle-img');
	echo '<button type="button" class="navbar-toggle ' . $t_toggle_class . ' collapsed pull-right" data-toggle="collapse" data-target=".navbar-buttons,.navbar-menu">';
	echo '<span class="sr-only">Toggle user menu</span>';
	if (auth_is_user_authenticated()) {
		layout_navbar_user_avatar();
	}
	echo '</button>';

	echo '<button type="button" class="small navbar-toggle menu-toggler pull-right grey" id="menu-toggler">';
	echo '<span class="sr-only">Toggle sidebar</span>';
	echo '<span class="icon-bar"></span>';
	echo '<span class="icon-bar"></span>';
	echo '<span class="icon-bar"></span>';
	echo '</button>';

	echo '</div>';

	echo '<div class="hidden-xs">';
	echo '<div class="navbar-buttons navbar-header pull-right navbar-collapse collapse">';
	echo '<ul class="nav ace-nav">';
	if (auth_is_user_authenticated()) {
		# projects dropdown menu
		layout_navbar_projects_menu();
		# user buttons such as messages, notifications and user menu
		layout_navbar_user_menu();
	}
	echo '</ul>';
	echo '</div>';
	echo '</div>';

	# mobile view
	echo '<div class="hidden-sm hidden-md hidden-lg">';
	echo '<nav class="navbar-menu pull-left navbar-collapse collapse" role="navigation" style="height: auto;">';
	echo '<ul class="nav navbar-nav">';
	if (auth_is_user_authenticated()) {
		layout_navbar_user_menu(false);
		layout_navbar_projects_menu();
	}
	echo '</ul>';
	echo '</div>';
	echo '</div>';

	echo '</div>';
	echo '</div>';
}

/**
 * Print navbar menu item
 * @param string $p_url destination url of the menu item
 * @param string $p_title menu item title
 * @param string $p_icon icon to use for this menu
 * @return null
 */
function layout_navbar_menu_item( $p_url, $p_title, $p_icon ) {
	echo '<li>';
	echo '<a href="' . $p_url . '">';
	echo '<i class="ace-icon fa ' . $p_icon . '"> </i> ' . $p_title;
	echo '</a>';
	echo '</li>';
}

/**
 * Print navbar user menu at the top right of the page
 * @param bool $p_show_avatar decide whether to show logged in user avatar
 * @return null
 */
function layout_navbar_user_menu( $p_show_avatar = true ) {
	if( !auth_is_user_authenticated() ) {
		return;
	}

	$t_username = current_user_get_field( 'username' );

	echo '<li class="grey">';
	echo '<a data-toggle="dropdown" href="#" class="dropdown-toggle">';
	if( $p_show_avatar ) {
		layout_navbar_user_avatar( 'nav-user-photo' );
		echo '<span class="user-info">';
		echo $t_username;
		echo '</span>';
		echo '<i class="ace-icon fa fa-angle-down"></i>';
	} else {
		echo '&#160;' . $t_username . '&#160;' . "\n";
		echo '<i class="ace-icon fa fa-angle-down bigger-110"></i>';
	}
	echo '</a>';
	echo '<ul class="user-menu dropdown-menu dropdown-menu-right dropdown-yellow dropdown-caret dropdown-close">';

	# My Account
	layout_navbar_menu_item( helper_mantis_url( 'account_page.php' ), lang_get( 'account_link' ), 'fa-user' );

	# RSS Feed
	if( OFF != config_get( 'rss_enabled' ) ) {
		layout_navbar_menu_item( htmlspecialchars( rss_get_issues_feed_url() ), lang_get( 'rss' ), 'fa-rss-square orange' );
	}

	echo '<li class="divider"></li>';

	# Logout
	layout_navbar_menu_item( helper_mantis_url( 'logout_page.php' ), lang_get( 'logout_link' ), 'fa-sign-out' );
	echo '</ul>';
	echo '</li>';
}


/**
 * Print navbar projects menu at the top right of the page
 * @return null
 */
function layout_navbar_projects_menu() {
	if( !auth_is_user_authenticated() ) {
		return;
	}

	# Project Selector (hidden if only one project visible to user)
	$t_show_project_selector = true;
	$t_project_ids = current_user_get_accessible_projects();
	if( count( $t_project_ids ) == 1 ) {
		$t_project_id = (int) $t_project_ids[0];
		if( count( current_user_get_accessible_subprojects( $t_project_id ) ) == 0 ) {
			$t_show_project_selector = false;
		}
	}

	if( $t_show_project_selector ) {
		echo '<li class="grey">' . "\n";
		echo '<a data-toggle="dropdown" href="#" class="dropdown-toggle">' . "\n";

		$t_current_project_id = helper_get_current_project();
		if( ALL_PROJECTS == $t_current_project_id) {
			echo '&#160;' . string_attribute( lang_get( 'all_projects' ) ) . '&#160;' . "\n";
		} else {
			echo '&#160;' . string_attribute( project_get_field( $t_current_project_id, 'name' ) ) . '&#160;' . "\n";
		}

		echo ' <i class="ace-icon fa fa-angle-down bigger-110"></i>' . "\n";
		echo '</a>' . "\n";

		echo '<ul class="dropdown-menu dropdown-menu-right dropdown-yellow dropdown-caret dropdown-close">' . "\n";
		layout_navbar_projects_list( join( ';', helper_get_current_project_trace() ), true, null, true );
		echo '</ul>' . "\n";
		echo '</li>' . "\n";
	} else {
		# User has only one project, set it as both current and default
		if( ALL_PROJECTS == helper_get_current_project() ) {
			helper_set_current_project( $t_project_id );

			if( !current_user_is_protected() ) {
				current_user_set_default_project( $t_project_id );
			}

			# Force reload of current page, except if we got here after
			# creating the first project
			$t_redirect_url = str_replace( config_get( 'short_path' ), '', $_SERVER['REQUEST_URI'] );
			if( 'manage_proj_create.php' != $t_redirect_url ) {
				html_meta_redirect( $t_redirect_url, 0, false );
			}
		}
	}
}


/**
 * Print projects that the current user has access to.
 *
 * @param int $p_project_id 	The current project id or null to use cookie.
 * @param bool $p_include_all_projects  true: include "All Projects", otherwise false.
 * @param int|null $p_filter_project_id  The id of a project to exclude or null.
 * @param string|bool $p_trace  The current project trace, identifies the sub-project via a path from top to bottom.
 * @param bool $p_can_report_only If true, disables projects in which user can't report issues; defaults to false (all projects enabled)
 */
function layout_navbar_projects_list( $p_project_id = null, $p_include_all_projects = true, $p_filter_project_id = null, $p_trace = false, $p_can_report_only = false ) {
	$t_user_id = auth_get_current_user_id();
	$t_project_ids = user_get_accessible_projects( $t_user_id );
	$t_can_report = true;
	project_cache_array_rows( $t_project_ids );

	if( $p_include_all_projects && $p_filter_project_id !== ALL_PROJECTS ) {
		echo '<li><a href="' . helper_mantis_url( 'set_project.php' ) . '?project_id=' . ALL_PROJECTS . '"';
		if( $p_project_id !== null ) {
			check_selected( $p_project_id, ALL_PROJECTS, false );
		}
		if( ALL_PROJECTS == $p_project_id ) {
			echo '><i class="ace-icon fa fa-dot-circle-o"></i> ';
		} else {
			echo '><i class="ace-icon fa fa-circle-o"></i> ';
		}
		echo lang_get( 'all_projects' ) . ' </a></li>' . "\n";
		echo '<li class="divider"></li>' . "\n";
	}

	foreach( $t_project_ids as $t_id ) {
		if( $p_can_report_only ) {
			$t_report_bug_threshold = config_get( 'report_bug_threshold', null, $t_user_id, $t_id );
			$t_can_report = access_has_project_level( $t_report_bug_threshold, $t_id, $t_user_id );
		}

		echo '<li><a href="' . helper_mantis_url( 'set_project.php' ) . '?project_id=' . $t_id . '"';
		check_selected( $p_project_id, $t_id, false );
		check_disabled( $t_id == $p_filter_project_id || !$t_can_report );
		if( $t_id == $p_project_id ) {
			echo '><i class="ace-icon fa fa-dot-circle-o"></i> ';
		} else {
			echo '><i class="ace-icon fa fa-circle-o"></i> ';
		}
		echo string_attribute( project_get_field( $t_id, 'name' ) ) . ' </a></li>' . "\n";
		layout_navbar_subproject_option_list( $t_id, $p_project_id, $p_filter_project_id, $p_trace, $p_can_report_only );
	}
}

/**
 * List projects that the current user has access to
 *
 * @param integer $p_parent_id         A parent project identifier.
 * @param integer $p_project_id        A project identifier.
 * @param integer $p_filter_project_id A filter project identifier.
 * @param boolean $p_trace             Whether to trace parent projects.
 * @param boolean $p_can_report_only   If true, disables projects in which user can't report issues; defaults to false (all projects enabled).
 * @param array   $p_parents           Array of parent projects.
 * @return void
 */
function layout_navbar_subproject_option_list( $p_parent_id, $p_project_id = null, $p_filter_project_id = null, $p_trace = false, $p_can_report_only = false, array $p_parents = array() ) {
	array_push( $p_parents, $p_parent_id );
	$t_user_id = auth_get_current_user_id();
	$t_project_ids = user_get_accessible_subprojects( $t_user_id, $p_parent_id );
	$t_can_report = true;

	foreach( $t_project_ids as $t_id ) {
		if( $p_can_report_only ) {
			$t_report_bug_threshold = config_get( 'report_bug_threshold', null, $t_user_id, $t_id );
			$t_can_report = access_has_project_level( $t_report_bug_threshold, $t_id, $t_user_id );
		}

		if( $p_trace ) {
			$t_full_id = join( $p_parents, ";" ) . ';' . $t_id;
		} else {
			$t_full_id = $t_id;
		}

		echo '<li>';
		echo '<a href="' . helper_mantis_url( 'set_project.php' ) . '?project_id=' . $t_full_id . '"';
		check_selected( $p_project_id, $t_full_id, false );
		check_disabled( $t_id == $p_filter_project_id || !$t_can_report );
		echo '>';
		echo str_repeat( '&#160;', count( $p_parents ) * 4 );
		if( $t_full_id == $p_project_id ) {
			echo '<i class="ace-icon fa fa-dot-circle-o"></i> ';
		} else {
			echo '<i class="ace-icon fa fa-circle-o"></i> ';
		}
		echo string_attribute( project_get_field( $t_id, 'name' ) )
			. '</a></li>' . "\n";

		layout_navbar_subproject_option_list( $t_id, $p_project_id, $p_filter_project_id, $p_trace, $p_can_report_only, $p_parents );
	}
}


/**
 * Print user avatar in the navbar
 * @param string $p_img_class css class to use with the img tag
 * @return null
 */
function layout_navbar_user_avatar( $p_img_class = '' ) {
	$t_default_avatar = '<i class="ace-icon fa fa-user fa-2x white"></i> ';

	if( OFF === config_get( 'show_avatar' ) ) {
		echo $t_default_avatar;
		return;
	}

	$p_user_id = auth_get_current_user_id();
	if( !user_exists( $p_user_id ) ) {
		echo $t_default_avatar;
		return;
	}

	if( access_has_project_level( config_get( 'show_avatar_threshold' ), null, $p_user_id ) ) {
		$t_avatar = user_get_avatar( $p_user_id, 40 );
		if( false !== $t_avatar ) {
			$t_avatar_url = htmlspecialchars( $t_avatar[0] );
			echo '<img class="' . $p_img_class .  '" src="' . $t_avatar_url . '" alt="User avatar" />';
			return;
		}
	}

	echo $t_default_avatar;
}

/**
 * Print sidebar
 * @param string $p_active_sidebar_page page where the displayed page lives under
 * @return void
 */
function layout_print_sidebar( $p_active_sidebar_page = null ) {
	if( auth_is_user_authenticated() ) {
		$t_protected = current_user_get_field( 'protected' );
		$t_current_project = helper_get_current_project();

		# Starting sidebar markup
		layout_sidebar_begin();

		$t_menu_options = array();

		# Main Page
		if( config_get( 'news_enabled' ) == ON ) {
			layout_sidebar_menu( 'main_page.php', 'main_link', 'fa-bullhorn', $p_active_sidebar_page  );
		}

		# Plugin / Event added options
		$t_event_menu_options = event_signal( 'EVENT_MENU_MAIN_FRONT' );
		foreach( $t_event_menu_options as $t_plugin => $t_plugin_menu_options ) {
			foreach( $t_plugin_menu_options as $t_callback => $t_callback_menu_options ) {
				if( is_array( $t_callback_menu_options ) ) {
					$t_menu_options = array_merge( $t_menu_options, $t_callback_menu_options );
				} else {
					if( !is_null( $t_callback_menu_options ) ) {
						$t_menu_options[] = $t_callback_menu_options;
					}
				}
			}
		}

		# My View
		layout_sidebar_menu( 'my_view_page.php', 'my_view_link', 'fa-dashboard', $p_active_sidebar_page );

		# View Bugs
		layout_sidebar_menu( 'view_all_bug_page.php', 'view_bugs_link', 'fa-list-alt', $p_active_sidebar_page );
                
                # View Docs
		layout_sidebar_menu( 'view_all_documents_page.php', 'view_documents_link', 'fa-file-archive-o', $p_active_sidebar_page );

		# Report Bugs
		if( access_has_project_level( config_get( 'report_bug_threshold' ) ) ) {
			$t_bug_url = string_get_bug_report_url();
			layout_sidebar_menu( $t_bug_url, 'report_bug_link', 'fa-edit', $p_active_sidebar_page );
		}

		# Changelog Page
		if( access_has_project_level( config_get( 'view_changelog_threshold' ) ) ) {
			layout_sidebar_menu( 'changelog_page.php', 'changelog_link', 'fa-retweet', $p_active_sidebar_page );
		}

		# Roadmap Page
		if( access_has_project_level( config_get( 'roadmap_view_threshold' ) ) ) {
			layout_sidebar_menu( 'roadmap_page.php', 'roadmap_link', 'fa-road', $p_active_sidebar_page );
		}

		# Summary Page
		if( access_has_project_level( config_get( 'view_summary_threshold' ) ) ) {
			layout_sidebar_menu( 'summary_page.php', 'summary_link', 'fa-bar-chart-o', $p_active_sidebar_page );
		}

		# Project Documentation Page
		if( ON == config_get( 'enable_project_documentation' ) ) {
			layout_sidebar_menu( 'proj_doc_page.php', 'docs_link', 'fa-book', $p_active_sidebar_page );
		}

		# Project Wiki
		if( ON == config_get_global( 'wiki_enable' )  ) {
			layout_sidebar_menu( 'wiki.php?type=project&amp;id=' . $t_current_project, 'wiki', 'fa-graduation-cap', $p_active_sidebar_page );
		}

		# Plugin / Event added options
		$t_event_menu_options = event_signal( 'EVENT_MENU_MAIN' );
		foreach( $t_event_menu_options as $t_plugin => $t_plugin_menu_options ) {
			foreach( $t_plugin_menu_options as $t_callback => $t_callback_menu_options ) {
				if( is_array( $t_callback_menu_options ) ) {
					$t_menu_options = array_merge( $t_menu_options, $t_callback_menu_options );
				} else {
					if( !is_null( $t_callback_menu_options ) ) {
						$t_menu_options[] = $t_callback_menu_options;
					}
				}
			}
		}

		# Manage Users (admins) or Manage Project (managers) or Manage Custom Fields
		if( access_has_global_level( config_get( 'manage_site_threshold' ) ) ) {
			layout_sidebar_menu( 'manage_overview_page.php', 'manage_link', 'fa-gears', $p_active_sidebar_page );
		} else {
			$t_show_access = min( config_get( 'manage_user_threshold' ), config_get( 'manage_project_threshold' ), config_get( 'manage_custom_fields_threshold' ) );
			if( access_has_global_level( $t_show_access ) || access_has_any_project( $t_show_access ) ) {
				$t_current_project = helper_get_current_project();
				if( access_has_global_level( config_get( 'manage_user_threshold' ) ) ) {
					$t_link = 'manage_user_page.php';
				} else {
					if( access_has_project_level( config_get( 'manage_project_threshold' ), $t_current_project ) && ( $t_current_project <> ALL_PROJECTS ) ) {
						$t_link = 'manage_proj_edit_page.php?project_id=' . $t_current_project;
					} else {
						$t_link = 'manage_proj_page.php';
					}
				}
				layout_sidebar_menu( $t_link , 'manage_link', 'fa-gears' );
			}
		}

		# Add custom options
		$t_custom_options = prepare_custom_menu_options( 'main_menu_custom_options' );
		$t_menu_options = array_merge( $t_menu_options, $t_custom_options );

		# Time Tracking / Billing
		if( config_get( 'time_tracking_enabled' ) && access_has_global_level( config_get( 'time_tracking_reporting_threshold' ) ) ) {
			layout_sidebar_menu( 'billing_page.php', 'time_tracking_billing_link', 'fa-clock-o', $p_active_sidebar_page );
		}

		# Ending sidebar markup
		layout_sidebar_end();
	}
}

/**
 * Print sidebar opening elements
 * @return null
 */
function layout_sidebar_begin() {
	$t_collapse_block = is_collapsed( 'sidebar' );
	$t_block_css = $t_collapse_block ? 'menu-min' : '';

	echo '<div id="sidebar" class="sidebar sidebar-fixed responsive compact ' . $t_block_css . '">';

	echo '<ul class="nav nav-list">';
}


/**
 * Print sidebar menu item
 * @param string $p_page page name
 * @param string $p_title menu title in english
 * @param string $p_icon icon to use for this menu
 * @param string $p_active_sidebar_page page name to set as active
 * @return null
 */
function layout_sidebar_menu( $p_page, $p_title, $p_icon, $p_active_sidebar_page = null ) {
	if( $p_page == $p_active_sidebar_page ||
		$p_page == basename( $_SERVER['SCRIPT_NAME'] ) ) {
		echo '<li class="active">' . "\n";
	} else {
		echo '<li>' . "\n";
	}
	echo '<a href="' . helper_mantis_url( $p_page ) . '">' . "\n";
	echo '<i class="menu-icon fa ' . $p_icon . '"></i> ' . "\n";
	echo '<span class="menu-text"> ' . lang_get( $p_title ) . ' </span>' . "\n";
	echo '</a>' . "\n";
	echo '<b class="arrow"></b>' . "\n";
	echo '</li>' . "\n";
}


/**
 * Print sidebar closing elements
 * @return null
 */
function layout_sidebar_end() {
	echo '</ul>';

	$t_collapse_block = is_collapsed( 'sidebar' );

	echo '<div id="sidebar" class="sidebar-toggle sidebar-collapse">';
	if( layout_is_rtl() ) {
		$t_block_icon = $t_collapse_block ? 'fa-angle-double-left' : 'fa-angle-double-right';
		echo '<i data-icon2="ace-icon fa fa-angle-double-left" data-icon1="ace-icon fa fa-angle-double-right"
		class="ace-icon fa ' . $t_block_icon . '"></i>';
	} else {
		$t_block_icon = $t_collapse_block ? 'fa-angle-double-right' : 'fa-angle-double-left';
		echo '<i data-icon2="ace-icon fa fa-angle-double-right" data-icon1="ace-icon fa fa-angle-double-left"
		class="ace-icon fa ' . $t_block_icon . '"></i>';
	}
	echo '</div>';
	echo '</div>';
}

/**
 * Render opening markup for main container
 * @return null
 */
function layout_main_container_begin() {
	echo '<div class="main-container" id="main-container">', "\n";
}

/**
 * Render closing markup for main container
 * @return null
 */
function layout_main_container_end() {
	echo '</div>' , "\n";
}

/**
 * Render opening markup for main content
 * @return null
 */
function layout_main_content_begin() {
	echo '<div class="main-content">' , "\n";
}

/**
 * Render closing markup for main content
 * @return null
 */
function layout_main_content_end() {
	echo '</div>' , "\n";
}

/**
 * Render opening markup for main page content
 * @return null
 */
function layout_page_content_begin() {
	echo '  <div class="page-content">' , "\n";
	echo '    <div class="row">' , "\n";
}

/**
 * Render closing markup for main page content
 * @return null
 */
function layout_page_content_end() {
	echo '  </div>' , "\n";
	echo '</div>' , "\n";
}


/**
 * Render breadcrumbs bar.
 * @return null
 */
function layout_breadcrumbs() {
	$t_username = current_user_get_field( 'username' );
	$t_protected = current_user_get_field( 'protected' );
	$t_access_level = get_enum_element( 'access_levels', current_user_get_access_level() );

	echo '<div id="breadcrumbs" class="breadcrumbs">' , "\n";

	# Login information
	echo '<ul class="breadcrumb">' , "\n";
	if( current_user_is_anonymous() ) {
		$t_return_page = $_SERVER['SCRIPT_NAME'];
		if( isset( $_SERVER['QUERY_STRING'] ) ) {
			$t_return_page .= '?' . $_SERVER['QUERY_STRING'];
		}

		$t_return_page = string_url( $t_return_page );

		echo ' <li><i class="fa fa-user home-icon active"></i> ' . lang_get( 'anonymous' ) . ' </li>' . "\n";

		echo '<div class="btn-group btn-corner">' . "\n";
		echo '	<button href="' . helper_mantis_url( 'login_page.php?return=' . $t_return_page ) .
			'" class="btn btn-primary btn-xs">' . lang_get( 'login_link' ) . '</button>' . "\n";
		if( config_get_global( 'allow_signup' ) == ON ) {
			echo '	<button href="' . helper_mantis_url( 'signup_page.php' ) . '" class="btn btn-primary btn-xs">' .
				lang_get( 'signup_link' ) . '</button>' . "\n";
		}
		echo '</div>' . "\n";

	} else {
		echo '  <li><i class="fa fa-user home-icon active"></i>';
		$t_page = ( OFF == $t_protected ) ? 'account_page.php' : 'my_view_page.php';
		echo '  <a href="' . helper_mantis_url( $t_page ) . '">' . string_html_specialchars( $t_username ) . '</a></li>' .  "\n";

		$t_label = layout_is_rtl() ? 'arrowed-right' : 'arrowed';
		echo '  <span class="label hidden-xs label-default ' . $t_label . '">' . $t_access_level . '</span>' , "\n";
	}
	echo '</ul>' , "\n";

	# Recently visited
	if( last_visited_enabled() ) {
		$t_ids = last_visited_get_array();

		if( count( $t_ids ) > 0 ) {
			echo '<div class="nav-recent hidden-xs">' . lang_get( 'recently_visited' ) . ': ';
			$t_first = true;

			foreach( $t_ids as $t_id ) {
				if( !$t_first ) {
					echo ', ';
				} else {
					$t_first = false;
				}

				echo string_get_bug_view_link( $t_id );
			}
			echo '</div>';
		}
	}

	# Bug Jump form
	# CSRF protection not required here - form does not result in modifications
	echo '<div id="nav-search" class="nav-search">';
	echo '<form class="form-search" method="post" action="' . helper_mantis_url( 'jump_to_bug.php' ) . '">';
	echo '<span class="input-icon">';
	echo '<input type="text" name="bug_id" autocomplete="off" class="nav-search-input" placeholder="' . lang_get( 'issue_id' ) . '">';
	echo '<i class="ace-icon fa fa-search nav-search-icon"></i>';
	echo '</span>';
	echo '</form>';
	echo '</div>';

	echo '</div>';
}

/**
 * Print the page footer information
 * @return void
 */
function layout_footer() {
	global $g_queries_array, $g_request_time;

	# If a user is logged in, update their last visit time.
	# We do this at the end of the page so that:
	#  1) we can display the user's last visit time on a page before updating it
	#  2) we don't invalidate the user cache immediately after fetching it
	#  3) don't do this on the password verification or update page, as it causes the
	#    verification comparison to fail
	if( auth_is_user_authenticated() && !current_user_is_anonymous() && !( is_page_name( 'verify.php' ) || is_page_name( 'account_update.php' ) ) ) {
		$t_user_id = auth_get_current_user_id();
		user_update_last_visit( $t_user_id );
	}

	layout_footer_begin();

	# Show MantisBT version and copyright statement
	$t_version_suffix = '';
	$t_copyright_years = ' 2015 - ' . date( 'Y' );
	if( config_get( 'show_version' ) == ON ) {
		$t_version_suffix = ' ' . htmlentities( MANTIS_VERSION . config_get_global( 'version_suffix' ) );
	}
	echo '<div class="col-md-6 col-xs-12 no-padding">' . "\n";
	echo '<address>' . "\n";
	echo '<strong>Desarrollado por <a href="http://www.infortributos.com" title="Software de Requerimientos">Informatica y Tributos S.A.S. ' . $t_version_suffix . '</a></strong> <br>' . "\n";
	echo "<small>Copyright &copy;$t_copyright_years Inforequest</small>" . '<br>';

	# Show optional user-specified custom copyright statement
	$t_copyright_statement = config_get( 'copyright_statement' );
	if( $t_copyright_statement ) {
		echo '<small>' . $t_copyright_statement . '</small>' . "\n";
	}

	# Show contact information
	if( !is_page_name( 'login_page' ) ) {
		$t_webmaster_contact_information = sprintf( lang_get( 'webmaster_contact_information' ), string_html_specialchars( config_get( 'webmaster_email' ) ) );
		echo '<small>' . $t_webmaster_contact_information . '</small>' . '<br>' . "\n";
	}

	echo '</address>' . "\n";
	echo '</div>' . "\n";


	# We don't have a button anymore, so for now we will only show the resized
	# version of the logo when not on login page.
	if( !is_page_name( 'login_page' ) ) {
		echo '<div class="col-md-6 col-xs-12">' . "\n";
		echo '<div class="pull-right" id="powered-by-mantisbt-logo">' . "\n";
		$t_mantisbt_logo_url = helper_mantis_url( 'images/infortributos_logo.png' );
		echo '<a href="http://www.infortributos.com" '.
			'title="Desarrollado por Informatica y Tributos S.A.S.">' .
			'<img src="' . $t_mantisbt_logo_url . '" width="102" height="35" ' .
			'alt="Desarrollado por Informatica y Tributos S.A.S." />' .
			'</a>' . "\n";
		echo '</div>' . "\n";
		echo '</div>' . "\n";
	}

	event_signal( 'EVENT_LAYOUT_PAGE_FOOTER' );

	if( config_get( 'show_timer' ) || config_get( 'show_memory_usage' ) || config_get( 'show_queries_count' ) ) {
		echo '<div class="col-xs-12 no-padding grey">' . "\n";
		echo '<address class="no-margin pull-right">' . "\n";
	}


	# Print the page execution time
	if( config_get( 'show_timer' ) ) {
		$t_page_execution_time = sprintf( lang_get( 'page_execution_time' ), number_format( microtime( true ) - $g_request_time, 4 ) );
		echo '<small><i class="fa fa-clock-o"></i> ' . $t_page_execution_time . '</small>&#160;&#160;&#160;&#160;' . "\n";
	}

	# Print the page memory usage
	if( config_get( 'show_memory_usage' ) ) {
		$t_page_memory_usage = sprintf( lang_get( 'memory_usage_in_kb' ), number_format( memory_get_peak_usage() / 1024 ) );
		echo '<small><i class="fa fa-bolt"></i> ' . $t_page_memory_usage . '</small>&#160;&#160;&#160;&#160;' . "\n";
	}

	# Determine number of unique queries executed
	if( config_get( 'show_queries_count' ) ) {
		$t_total_queries_count = count( $g_queries_array );
		$t_unique_queries_count = 0;
		$t_total_query_execution_time = 0;
		$t_unique_queries = array();
		for ( $i = 0; $i < $t_total_queries_count; $i++ ) {
			if( !in_array( $g_queries_array[$i][0], $t_unique_queries ) ) {
				$t_unique_queries_count++;
				$g_queries_array[$i][3] = false;
				array_push( $t_unique_queries, $g_queries_array[$i][0] );
			} else {
				$g_queries_array[$i][3] = true;
			}
			$t_total_query_execution_time += $g_queries_array[$i][1];
		}

		$t_total_queries_executed = sprintf( lang_get( 'total_queries_executed' ), $t_total_queries_count );
		echo '<small><i class="fa fa-database"></i> ' . $t_total_queries_executed . '</small>&#160;&#160;&#160;&#160;' . "\n";
		if( config_get_global( 'db_log_queries' ) ) {
			$t_unique_queries_executed = sprintf( lang_get( 'unique_queries_executed' ), $t_unique_queries_count );
			echo '<small><i class="fa fa-database"></i> ' . $t_unique_queries_executed . '</small>&#160;&#160;&#160;&#160;' . "\n";
		}
		$t_total_query_time = sprintf( lang_get( 'total_query_execution_time' ), $t_total_query_execution_time );
		echo '<small><i class="fa fa-clock-o"></i> ' . $t_total_query_time . '</small>&#160;&#160;&#160;&#160;' . "\n";
	}

	if( config_get( 'show_timer' ) || config_get( 'show_memory_usage' ) || config_get( 'show_queries_count' ) ) {
		echo '</address>' . "\n";
		echo '</div>' . "\n";
	}

	# Print table of log events
	log_print_to_page();

	layout_footer_end();
}

/**
 * Render opening markup for footer section
 * @return null
 */
function layout_footer_begin() {
	echo '<div class="clearfix"></div>' . "\n";
	echo '<div class="space-20"></div>' . "\n";
	echo '<div class="footer">' . "\n";
	echo '<div class="footer-inner">' . "\n";
	echo '<div class="footer-content">' . "\n";
}

/**
 * Render closing markup for footer section
 * @return null
 */
function layout_footer_end() {
	echo '</div>' . "\n";
	echo '</div>' . "\n";
	echo '</div>' . "\n";
}

/**
 * Render scroll up link to go at the bottom of the page
 * @return null
 */
function layout_scroll_up_button() {
	echo '<a class="btn-scroll-up btn btn-sm btn-inverse display" id="btn-scroll-up" href="#">' . "\n";
	echo '<i class="ace-icon fa fa-angle-double-up icon-only bigger-110"></i>' . "\n";
	echo '</a>' . "\n";
}