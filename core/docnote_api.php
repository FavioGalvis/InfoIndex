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
 * Docnote API
 *
 * @package CoreAPI
 * @subpackage DocnoteAPI
 * @copyright Copyright 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses access_api.php
 * @uses antispam_api.php
 * @uses authentication_api.php
 * @uses bug_api.php
 * @uses bug_revision_api.php
 * @uses config_api.php
 * @uses constant_inc.php
 * @uses database_api.php
 * @uses email_api.php
 * @uses error_api.php
 * @uses event_api.php
 * @uses helper_api.php
 * @uses history_api.php
 * @uses lang_api.php
 * @uses user_api.php
 * @uses utility_api.php
 */

require_api( 'access_api.php' );
require_api( 'antispam_api.php' );
require_api( 'authentication_api.php' );
require_api( 'bug_api.php' );
require_api( 'bug_revision_api.php' );
require_api( 'config_api.php' );
require_api( 'constant_inc.php' );
require_api( 'database_api.php' );
require_api( 'email_api.php' );
require_api( 'error_api.php' );
require_api( 'event_api.php' );
require_api( 'helper_api.php' );
require_api( 'history_api.php' );
require_api( 'lang_api.php' );
require_api( 'user_api.php' );
require_api( 'utility_api.php' );

/**
 * Docnote Data Structure Definition
 */
class DocnoteData {
	/**
	 * Docnote ID
	 */
	public $id;

	/**
	 * Bug ID
	 */
	public $bug_id;

	/**
	 * Reporter ID
	 */
	public $reporter_id;

	/**
	 * Note text
	 */
	public $note;

	/**
	 * View State
	 */
	public $view_state;

	/**
	 * Date submitted
	 */
	public $date_submitted;

	/**
	 * Last Modified
	 */
	public $last_modified;

	/**
	 * Docnote type
	 */
	public $note_type;

	/**
	 * ???
	 */
	public $note_attr;

	/**
	 * Time tracking information
	 */
	public $time_tracking;

	/**
	 * Docnote Text id
	 */
	public $docnote_text_id;
}

/**
 * Check if a docnote with the given ID exists
 * return true if the docnote exists, false otherwise
 * @param integer $p_docnote_id A docnote identifier.
 * @return boolean
 * @access public
 */
function docnote_exists( $p_docnote_id ) {
	$t_query = 'SELECT COUNT(*) FROM {docnote} WHERE id=' . db_param();
	$t_result = db_query( $t_query, array( $p_docnote_id ) );

	if( 0 == db_result( $t_result ) ) {
		return false;
	} else {
		return true;
	}
}

/**
 * Check if a docnote with the given ID exists
 * return true if the docnote exists, raise an error if not
 * @param integer $p_docnote_id A docnote identifier.
 * @access public
 * @return void
 */
function docnote_ensure_exists( $p_docnote_id ) {
	if( !docnote_exists( $p_docnote_id ) ) {
		trigger_error( ERROR_DOCNOTE_NOT_FOUND, ERROR );
	}
}

/**
 * Check if the given user is the reporter of the docnote
 * return true if the user is the reporter, false otherwise
 * @param integer $p_docnote_id A docnote identifier.
 * @param integer $p_user_id    An user identifier.
 * @return boolean
 * @access public
 */
function docnote_is_user_reporter( $p_docnote_id, $p_user_id ) {
	if( docnote_get_field( $p_docnote_id, 'reporter_id' ) == $p_user_id ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Add a docnote to a bug
 * return the ID of the new docnote
 * @param integer $p_bug_id          A bug identifier.
 * @param string  $p_docnote_text    The docnote text to add.
 * @param string  $p_time_tracking   Time tracking value - hh:mm string.
 * @param boolean $p_private         Whether docnote is private.
 * @param integer $p_type            The docnote type.
 * @param string  $p_attr            Docnote Attribute.
 * @param integer $p_user_id         A user identifier.
 * @param boolean $p_send_email      Whether to generate email.
 * @param integer $p_date_submitted  Date submitted (defaults to now()).
 * @param integer $p_last_modified   Last modification date (defaults to now()).
 * @param boolean $p_skip_bug_update Skip bug last modification update (useful when importing bugs/docnotes).
 * @param boolean $p_log_history     Log changes to docnote history (defaults to true).
 * @return boolean|integer false or indicating docnote id added
 * @access public
 */
function docnote_add( $p_bug_id, $p_docnote_text, $p_time_tracking = '0:00', $p_private = false, $p_type = DOCNOTE, $p_attr = '', $p_user_id = null, $p_send_email = true, $p_date_submitted = 0, $p_last_modified = 0, $p_skip_bug_update = false, $p_log_history = true ) {
	$c_bug_id = (int)$p_bug_id;
	$c_time_tracking = helper_duration_to_minutes( $p_time_tracking );
	$c_type = (int)$p_type;
	$c_date_submitted = $p_date_submitted <= 0 ? db_now() : (int)$p_date_submitted;
	$c_last_modified = $p_last_modified <= 0 ? db_now() : (int)$p_last_modified;

	antispam_check();

	if( REMINDER !== $p_type ) {
		# Check if this is a time-tracking note
		$t_time_tracking_enabled = config_get( 'time_tracking_enabled' );
		if( ON == $t_time_tracking_enabled && $c_time_tracking > 0 ) {
			$t_time_tracking_without_note = config_get( 'time_tracking_without_note' );
			if( is_blank( $p_docnote_text ) && OFF == $t_time_tracking_without_note ) {
				error_parameters( lang_get( 'docnote' ) );
				trigger_error( ERROR_EMPTY_FIELD, ERROR );
			}
			$c_type = TIME_TRACKING;
		} else if( is_blank( $p_docnote_text ) ) {
			# This is not time tracking (i.e. it's a normal docnote)
			# @todo should we not trigger an error in this case ?
			return false;
		}
	}

	# Event integration
	$t_docnote_text = event_signal( 'EVENT_DOCNOTE_DATA', $p_docnote_text, $c_bug_id );

	# insert docnote text
	$t_query = 'INSERT INTO {docnote_text} ( note ) VALUES ( ' . db_param() . ' )';
	db_query( $t_query, array( $t_docnote_text ) );

	# retrieve docnote text id number
	$t_docnote_text_id = db_insert_id( db_get_table( 'docnote_text' ) );

	# get user information
	if( $p_user_id === null ) {
		$p_user_id = auth_get_current_user_id();
	}

	# Check for private docnotes.
	if( $p_private && access_has_bug_level( config_get( 'set_view_status_threshold' ), $p_bug_id, $p_user_id ) ) {
		$t_view_state = VS_PRIVATE;
	} else {
		$t_view_state = VS_PUBLIC;
	}

	# insert docnote info
	$t_query = 'INSERT INTO {docnote}
			(bug_id, reporter_id, docnote_text_id, view_state, date_submitted, last_modified, note_type, note_attr, time_tracking)
		VALUES ('
		. db_param() . ', ' . db_param() . ', ' . db_param() . ', ' . db_param() . ', '
		. db_param() . ', ' . db_param() . ', ' . db_param() . ', ' . db_param() . ', '
		. db_param() . ' )';
	$t_params = array(
		$c_bug_id, $p_user_id, $t_docnote_text_id, $t_view_state,
		$c_date_submitted, $c_last_modified, $c_type, $p_attr,
		$c_time_tracking );
	db_query( $t_query, $t_params );

	# get docnote id
	$t_docnote_id = db_insert_id( db_get_table( 'docnote' ) );

	# update bug last updated
	if( !$p_skip_bug_update ) {
		bug_update_date( $p_bug_id );
	}

	# log new bug
	if( true == $p_log_history ) {
		history_log_event_special( $p_bug_id, DOCNOTE_ADDED, docnote_format_id( $t_docnote_id ) );
	}

	# Event integration
	event_signal( 'EVENT_DOCNOTE_ADD', array( $p_bug_id, $t_docnote_id ) );

	# only send email if the text is not blank, otherwise, it is just recording of time without a comment.
	if( true == $p_send_email && !is_blank( $t_docnote_text ) ) {
		email_generic( $p_bug_id, 'docnote', 'email_notification_title_for_action_docnote_submitted' );
	}

	return $t_docnote_id;
}

/**
 * Delete a docnote
 * @param integer $p_docnote_id A bug note identifier.
 * @return boolean
 * @access public
 */
function docnote_delete( $p_docnote_id ) {
	$t_bug_id = docnote_get_field( $p_docnote_id, 'bug_id' );
	$t_docnote_text_id = docnote_get_field( $p_docnote_id, 'docnote_text_id' );

	# Remove the docnote
	$t_query = 'DELETE FROM {docnote} WHERE id=' . db_param();
	db_query( $t_query, array( $p_docnote_id ) );

	# Remove the docnote text
	$t_query = 'DELETE FROM {docnote_text} WHERE id=' . db_param();
	db_query( $t_query, array( $t_docnote_text_id ) );

	# log deletion of bug
	history_log_event_special( $t_bug_id, DOCNOTE_DELETED, docnote_format_id( $p_docnote_id ) );

	# Event integration
	event_signal( 'EVENT_DOCNOTE_DELETED', array( $t_bug_id, $p_docnote_id ) );

	return true;
}

/**
 * delete all docnotes associated with the given bug
 * @param integer $p_bug_id A bug identifier.
 * @return void
 * @access public
 */
function docnote_delete_all( $p_bug_id ) {
	# Delete the docnote text items
	$t_query = 'SELECT docnote_text_id FROM {docnote} WHERE bug_id=' . db_param();
	$t_result = db_query( $t_query, array( (int)$p_bug_id ) );
	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_docnote_text_id = $t_row['docnote_text_id'];

		# Delete the corresponding docnote texts
		$t_query = 'DELETE FROM {docnote_text} WHERE id=' . db_param();
		db_query( $t_query, array( $t_docnote_text_id ) );
	}

	# Delete the corresponding docnotes
	$t_query = 'DELETE FROM {docnote} WHERE bug_id=' . db_param();
	db_query( $t_query, array( (int)$p_bug_id ) );
}

/**
 * Get the text associated with the docnote
 * @param integer $p_docnote_id A docnote identifier.
 * @return string docnote text
 * @access public
 */
function docnote_get_text( $p_docnote_id ) {
	$t_docnote_text_id = docnote_get_field( $p_docnote_id, 'docnote_text_id' );

	# grab the docnote text
	$t_query = 'SELECT note FROM {docnote_text} WHERE id=' . db_param();
	$t_result = db_query( $t_query, array( $t_docnote_text_id ) );

	return db_result( $t_result );
}

/**
 * Get a field for the given docnote
 * @param integer $p_docnote_id A docnote identifier.
 * @param string  $p_field_name Field name to retrieve.
 * @return string field value
 * @access public
 */
function docnote_get_field( $p_docnote_id, $p_field_name ) {
	static $s_vars;
	global $g_cache_docnote;

	if( isset( $g_cache_docnote[(int)$p_docnote_id] ) ) {
		return $g_cache_docnote[(int)$p_docnote_id]->$p_field_name;
	}

	if( $s_vars == null ) {
		$s_vars = getClassProperties( 'DocnoteData', 'public' );
	}

	if( !array_key_exists( $p_field_name, $s_vars ) ) {
		error_parameters( $p_field_name );
		trigger_error( ERROR_DB_FIELD_NOT_FOUND, WARNING );
	}

	$t_query = 'SELECT ' . $p_field_name . ' FROM {docnote} WHERE id=' . db_param();
	$t_result = db_query( $t_query, array( $p_docnote_id ), 1 );

	return db_result( $t_result );
}

/**
 * Get latest docnote id
 * @param integer $p_bug_id A bug identifier.
 * @return int latest docnote id
 * @access public
 */
function docnote_get_latest_id( $p_bug_id ) {
	$t_query = 'SELECT id FROM {docnote} WHERE bug_id=' . db_param() . ' ORDER by last_modified DESC';
	$t_result = db_query( $t_query, array( (int)$p_bug_id ), 1 );

	return (int)db_result( $t_result );
}

/**
 * Build the docnotes array for the given bug_id filtered by specified $p_user_access_level.
 * Docnotes are sorted by date_submitted according to 'docnote_order' configuration setting.
 * Return DocnoteData class object with raw values from the tables except the field
 * last_modified - it is UNIX_TIMESTAMP.
 * @param integer $p_bug_id             A bug identifier.
 * @param integer $p_user_docnote_order Sort order.
 * @param integer $p_user_docnote_limit Number of docnotes to display to user.
 * @param integer $p_user_id            An user identifier.
 * @return array array of docnotes
 * @access public
 */
function docnote_get_all_visible_docnotes( $p_bug_id, $p_user_docnote_order, $p_user_docnote_limit, $p_user_id = null ) {
	if( $p_user_id === null ) {
		$t_user_id = auth_get_current_user_id();
	} else {
		$t_user_id = $p_user_id;
	}

	$t_project_id = bug_get_field( $p_bug_id, 'project_id' );
	$t_user_access_level = user_get_access_level( $t_user_id, $t_project_id );

	$t_all_docnotes = docnote_get_all_docnotes( $p_bug_id );

	$t_private_docnote_visible = access_compare_level( $t_user_access_level, config_get( 'private_docnote_threshold' ) );
	$t_time_tracking_visible = access_compare_level( $t_user_access_level, config_get( 'time_tracking_view_threshold' ) );

	$t_docnotes = array();
	$t_docnote_count = count( $t_all_docnotes );
	$t_docnote_limit = $p_user_docnote_limit > 0 ? $p_user_docnote_limit : $t_docnote_count;
	$t_docnotes_found = 0;

	# build a list of the latest docnotes that the user can see
	for( $i = 0; ( $i < $t_docnote_count ) && ( $t_docnotes_found < $t_docnote_limit ); $i++ ) {
		$t_docnote = array_pop( $t_all_docnotes );

		if( $t_private_docnote_visible || $t_docnote->reporter_id == $t_user_id || ( VS_PUBLIC == $t_docnote->view_state ) ) {
			# If the access level specified is not enough to see time tracking information
			# then reset it to 0.
			if( !$t_time_tracking_visible ) {
				$t_docnote->time_tracking = 0;
			}

			$t_docnotes[$t_docnotes_found++] = $t_docnote;
		}
	}

	# reverse the list for users with ascending view preferences
	if( 'ASC' == $p_user_docnote_order ) {
		$t_docnotes = array_reverse( $t_docnotes );
	}

	return $t_docnotes;
}

/**
 * Build the docnotes array for the given bug_id.
 * Return DocnoteData class object with raw values from the tables except the field
 * last_modified - it is UNIX_TIMESTAMP.
 * The data is not filtered by VIEW_STATE !!
 * @param integer $p_bug_id A bug identifier.
 * @return array array of docnotes
 * @access public
 */
function docnote_get_all_docnotes( $p_bug_id ) {
	global $g_cache_docnotes, $g_cache_docnote;

	if( !isset( $g_cache_docnotes ) ) {
		$g_cache_docnotes = array();
	}

	if( !isset( $g_cache_docnote ) ) {
		$g_cache_docnote = array();
	}

	# the cache should be aware of the sorting order
	if( !isset( $g_cache_docnotes[(int)$p_bug_id] ) ) {
		# Now sorting by submit date and id (#11742). The date_submitted
		# column is currently not indexed, but that does not seem to affect
		# performance in a measurable way
		$t_query = 'SELECT b.*, t.note
			          	FROM      {docnote} b
			          	LEFT JOIN {docnote_text} t ON b.docnote_text_id = t.id
						WHERE b.bug_id=' . db_param() . '
						ORDER BY b.date_submitted ASC, b.id ASC';
		$t_docnotes = array();

		# BUILD docnotes array
		$t_result = db_query( $t_query, array( $p_bug_id ) );

		while( $t_row = db_fetch_array( $t_result ) ) {
			$t_docnote = new DocnoteData;

			$t_docnote->id = $t_row['id'];
			$t_docnote->bug_id = $t_row['bug_id'];
			$t_docnote->docnote_text_id = $t_row['docnote_text_id'];
			$t_docnote->note = $t_row['note'];
			$t_docnote->view_state = $t_row['view_state'];
			$t_docnote->reporter_id = $t_row['reporter_id'];
			$t_docnote->date_submitted = $t_row['date_submitted'];
			$t_docnote->last_modified = $t_row['last_modified'];
			$t_docnote->note_type = $t_row['note_type'];
			$t_docnote->note_attr = $t_row['note_attr'];
			$t_docnote->time_tracking = $t_row['time_tracking'];

			# Handle old docnotes before setting type to time tracking
			if ( $t_docnote->time_tracking != 0 ) {
				$t_docnote->note_type = TIME_TRACKING;
			}

			$t_docnotes[] = $t_docnote;
			$g_cache_docnote[(int)$t_docnote->id] = $t_docnote;
		}

		$g_cache_docnotes[(int)$p_bug_id] = $t_docnotes;
	}

	return $g_cache_docnotes[(int)$p_bug_id];
}

/**
 * Update the time_tracking field of the docnote
 * @param integer $p_docnote_id    A docnote identifier.
 * @param string  $p_time_tracking Timetracking string (hh:mm format).
 * @return void
 * @access public
 */
function docnote_set_time_tracking( $p_docnote_id, $p_time_tracking ) {
	$c_docnote_time_tracking = helper_duration_to_minutes( $p_time_tracking );

	$t_query = 'UPDATE {docnote} SET time_tracking = ' . db_param() . ' WHERE id=' . db_param();
	db_query( $t_query, array( $c_docnote_time_tracking, $p_docnote_id ) );
}

/**
 * Update the last_modified field of the docnote
 * @param integer $p_docnote_id A docnote identifier.
 * @return void
 * @access public
 */
function docnote_date_update( $p_docnote_id ) {
	$t_query = 'UPDATE {docnote} SET last_modified=' . db_param() . ' WHERE id=' . db_param();
	db_query( $t_query, array( db_now(), $p_docnote_id ) );
}

/**
 * Set the docnote text
 * @param integer $p_docnote_id   A docnote identifier.
 * @param string  $p_docnote_text The docnote text to set.
 * @return boolean
 * @access public
 */
function docnote_set_text( $p_docnote_id, $p_docnote_text ) {
	$t_old_text = docnote_get_text( $p_docnote_id );

	if( $t_old_text == $p_docnote_text ) {
		return true;
	}

	$t_bug_id = docnote_get_field( $p_docnote_id, 'bug_id' );
	$t_docnote_text_id = docnote_get_field( $p_docnote_id, 'docnote_text_id' );

	# insert an 'original' revision if needed
	if( bug_revision_count( $t_bug_id, REV_DOCNOTE, $p_docnote_id ) < 1 ) {
		$t_user_id = docnote_get_field( $p_docnote_id, 'reporter_id' );
		$t_timestamp = docnote_get_field( $p_docnote_id, 'last_modified' );
		bug_revision_add( $t_bug_id, $t_user_id, REV_DOCNOTE, $t_old_text, $p_docnote_id, $t_timestamp );
	}

	$t_query = 'UPDATE {docnote_text} SET note=' . db_param() . ' WHERE id=' . db_param();
	db_query( $t_query, array( $p_docnote_text, $t_docnote_text_id ) );

	# updated the last_updated date
	docnote_date_update( $p_docnote_id );
	bug_update_date( $t_bug_id );

	# insert a new revision
	$t_user_id = auth_get_current_user_id();
	$t_revision_id = bug_revision_add( $t_bug_id, $t_user_id, REV_DOCNOTE, $p_docnote_text, $p_docnote_id );

	# log new docnote
	history_log_event_special( $t_bug_id, DOCNOTE_UPDATED, docnote_format_id( $p_docnote_id ), $t_revision_id );

	return true;
}

/**
 * Set the view state of the docnote
 * @param integer $p_docnote_id A docnote identifier.
 * @param boolean $p_private    Whether docnote should be set to private status.
 * @return boolean
 * @access public
 */
function docnote_set_view_state( $p_docnote_id, $p_private ) {
	$t_bug_id = docnote_get_field( $p_docnote_id, 'bug_id' );

	if( $p_private ) {
		$t_view_state = VS_PRIVATE;
	} else {
		$t_view_state = VS_PUBLIC;
	}

	$t_query = 'UPDATE {docnote} SET view_state=' . db_param() . ' WHERE id=' . db_param();
	db_query( $t_query, array( $t_view_state, $p_docnote_id ) );

	history_log_event_special( $t_bug_id, DOCNOTE_STATE_CHANGED, $t_view_state, docnote_format_id( $p_docnote_id ) );

	return true;
}

/**
 * Pad the docnote id with the appropriate number of zeros for printing
 * @param integer $p_docnote_id A docnote identifier.
 * @return string
 * @access public
 */
function docnote_format_id( $p_docnote_id ) {
	$t_padding = config_get( 'display_docnote_padding' );

	return utf8_str_pad( $p_docnote_id, $t_padding, '0', STR_PAD_LEFT );
}

/**
 * Returns an array of docnote stats
 * @param integer $p_bug_id A bug identifier.
 * @param string  $p_from   Starting date (yyyy-mm-dd) inclusive, if blank, then ignored.
 * @param string  $p_to     Ending date (yyyy-mm-dd) inclusive, if blank, then ignored.
 * @return array array of docnote stats
 * @access public
 */
function docnote_stats_get_events_array( $p_bug_id, $p_from, $p_to ) {
	$c_to = strtotime( $p_to ) + SECONDS_PER_DAY - 1;
	$c_from = strtotime( $p_from );

	if( !is_blank( $c_from ) ) {
		$t_from_where = ' AND bn.date_submitted >= ' . $c_from;
	} else {
		$t_from_where = '';
	}

	if( !is_blank( $c_to ) ) {
		$t_to_where = ' AND bn.date_submitted <= ' . $c_to;
	} else {
		$t_to_where = '';
	}

	$t_results = array();

	$t_query = 'SELECT username, realname, SUM(time_tracking) AS sum_time_tracking
				FROM {user} u, {docnote} bn
				WHERE u.id = bn.reporter_id AND bn.time_tracking != 0 AND
				bn.bug_id = ' . db_param() . $t_from_where . $t_to_where .
				' GROUP BY u.username, u.realname';

	$t_result = db_query( $t_query, array( $p_bug_id ) );

	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_results[] = $t_row;
	}

	return $t_results;
}

/**
 * Returns an array of docnote stats
 * @param integer $p_project_id A project identifier.
 * @param string  $p_from       Starting date (yyyy-mm-dd) inclusive, if blank, then ignored.
 * @param string  $p_to         Ending date (yyyy-mm-dd) inclusive, if blank, then ignored.
 * @param integer $p_cost       Cost.
 * @return array array of docnote stats
 * @access public
 */
function docnote_stats_get_project_array( $p_project_id, $p_from, $p_to, $p_cost ) {
	$t_params = array();
	$c_to = strtotime( $p_to ) + SECONDS_PER_DAY - 1;
	$c_from = strtotime( $p_from );

	if( $c_to === false || $c_from === false ) {
		error_parameters( array( $p_from, $p_to ) );
		trigger_error( ERROR_GENERIC, ERROR );
	}

	if( ALL_PROJECTS != $p_project_id ) {
		$t_project_where = ' AND b.project_id = ' . db_param() . ' AND bn.bug_id = b.id ';
		$t_params[] = $p_project_id;
	} else {
		$t_project_where = '';
	}

	if( !is_blank( $c_from ) ) {
		$t_from_where = ' AND bn.date_submitted >= ' . db_param();
		$t_params[] = $c_from;
	} else {
		$t_from_where = '';
	}

	if( !is_blank( $c_to ) ) {
		$t_to_where = ' AND bn.date_submitted <= ' . db_param();
		$t_params[] = $c_to;
	} else {
		$t_to_where = '';
	}

	$t_results = array();

	$t_query = 'SELECT username, realname, summary, bn.bug_id, SUM(time_tracking) AS sum_time_tracking
			FROM {user} u, {docnote} bn, {bug} b
			WHERE u.id = bn.reporter_id AND bn.time_tracking != 0 AND bn.bug_id = b.id
			' . $t_project_where . $t_from_where . $t_to_where . '
			GROUP BY bn.bug_id, u.username, u.realname, b.summary
			ORDER BY bn.bug_id';
	$t_result = db_query( $t_query, $t_params );

	$t_cost_min = $p_cost / 60.0;

	while( $t_row = db_fetch_array( $t_result ) ) {
		$t_total_cost = $t_cost_min * $t_row['sum_time_tracking'];
		$t_row['cost'] = $t_total_cost;
		$t_results[] = $t_row;
	}

	return $t_results;
}

/**
 * Clear a docnote from the cache or all bug notes if no docnote id specified.
 * @param integer $p_docnote_id Identifier to clear (optional).
 * @return boolean
 * @access public
 */
function docnote_clear_cache( $p_docnote_id = null ) {
	global $g_cache_docnote, $g_cache_docnotes;

	if( null === $p_docnote_id ) {
		$g_cache_docnote = array();
	} else {
		unset( $g_cache_docnote[(int)$p_docnote_id] );
	}
	$g_cache_docnotes = array();

	return true;
}
