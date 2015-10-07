<?php
# InfoIndex - A PHP based Document Management System

# InfoIndex is open software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Open Software Foundation, either version 2 of the License, or
# (at your option) any later version.
#
# InfoIndex is distributed under the protection of the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with InfoIndex.  If not, see <http://www.gnu.org/licenses/>.

/*******************************************************************/
/* InfoIndex - a php based Document Management System               /
/*                                                                  /
/* @package Infortributos-InfoIndex                                 /
/* @copyright Copyright (C) 2015 - Informativa y Tributos S.A.S     /
/* @developer Favio Galvis                                          /
/*******************************************************************/
/**
 * View all documents page
 *
 * @uses core.php
 * @uses authentication_api.php
 * @uses compress_api.php
 * @uses config_api.php
 * @uses current_user_api.php
 * @uses filter_api.php
 * @uses gpc_api.php
 * @uses html_api.php
 * @uses lang_api.php
 * @uses print_api.php
 * @uses project_api.php
 * @uses user_api.php
 */

require_once( 'core.php' );
require_api( 'authentication_api.php' );
require_api( 'compress_api.php' );
require_api( 'config_api.php' );
require_api( 'current_user_api.php' );
require_api( 'filter_api.php' );
require_api( 'gpc_api.php' );
require_api( 'html_api.php' );
require_api( 'lang_api.php' );
require_api( 'print_api.php' );
require_api( 'project_api.php' );
require_api( 'user_api.php' );

require_js( 'bugFilter.js' );
require_css( 'status_config.php' );

# Verify if the user is authenticated using current user cookie or open session
auth_ensure_user_authenticated();

# User supplied page list number through HTTP GET, HTTP POST and cookies.
$f_page_number		= gpc_get_int( 'page_number', 1 );

# Get Project Id and set it as current
$t_project_id = gpc_get_int( 'project_id', helper_get_current_project() );
if( ( ALL_PROJECTS == $t_project_id || project_exists( $t_project_id ) ) && $t_project_id != helper_get_current_project() ) {
	helper_set_current_project( $t_project_id );
	# Reloading the page is required so that the project browser
	# reflects the new current project
	print_header_redirect( $_SERVER['REQUEST_URI'], true, false, true );
}

$t_per_page = null;
$t_docs_count = null;
$t_page_count = null;

# Use filter to call the first 10 bug_rows
//$t_rows = filter_get_docs_rows( $f_page_number, $t_per_page, $t_page_count, $t_docs_count, null, null, null, true );
//if( $t_rows === false ) {
	//print_header_redirect( 'view_all_set.php?type=0' );
//}
$t_rows = file_get_visible_attachments_all();

# Get the bug ID, handlers and project_ids from the first page of bug rows
$t_bugslist = array();
$t_users_handlers = array();
$t_project_ids  = array();
$t_row_count = count( $t_rows );
for( $i=0; $i < $t_row_count; $i++ ) {
	array_push( $t_bugslist, $t_rows[$i]['id'] );
	$t_users_handlers[] = $t_rows[$i]['id'];
	$t_project_ids[] = $t_rows[$i]['id'];
}
$t_unique_users_handlers = array_unique( $t_users_handlers );
$t_unique_project_ids = array_unique( $t_project_ids );
# Cache users and project from the bug list
user_cache_array_rows( $t_unique_users_handlers );
project_cache_array_rows( $t_unique_project_ids );

# Set cookie with bug_id from the list to show
gpc_set_cookie( config_get( 'bug_list_cookie' ), implode( ',', $t_bugslist ) );

# Use navigator compresion for faster load times
compress_enable();

# Don't index view issues pages
html_robots_noindex();

layout_page_header_begin( lang_get( 'view_bugs_link' ) );

# Hold the redirection if a delay is set on the config
if( current_user_get_pref( 'refresh_delay' ) > 0 ) {
	$t_query = '?';

	if( $f_page_number > 1 )  {
		$t_query .= 'page_number=' . $f_page_number . '&';
	}

	$t_query .= 'refresh=true';

	html_meta_redirect( 'view_all_documents_page.php' . $t_query, current_user_get_pref( 'refresh_delay' ) * 60 );
}

layout_page_header_end();

layout_page_begin( __FILE__ );

# Redirect to the view_all page.
define( 'VIEW_ALL_DOCUMENTS_INC_ALLOW', true );
include( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'view_all_documents_inc.php' );

layout_page_end();
