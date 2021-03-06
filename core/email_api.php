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
 * Email API
 *
 * @package CoreAPI
 * @subpackage EmailAPI
 * @copyright Copyright 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 *
 * @uses access_api.php
 * @uses authentication_api.php
 * @uses bug_api.php
 * @uses docnote_api.php
 * @uses category_api.php
 * @uses config_api.php
 * @uses constant_inc.php
 * @uses current_user_api.php
 * @uses custom_field_api.php
 * @uses database_api.php
 * @uses email_queue_api.php
 * @uses event_api.php
 * @uses helper_api.php
 * @uses history_api.php
 * @uses lang_api.php
 * @uses logging_api.php
 * @uses project_api.php
 * @uses relationship_api.php
 * @uses sponsorship_api.php
 * @uses string_api.php
 * @uses user_api.php
 * @uses user_pref_api.php
 * @uses utility_api.php
 *
 * @uses PHPMailerAutoload.php PHPMailer library
 */

require_api( 'access_api.php' );
require_api( 'authentication_api.php' );
require_api( 'bug_api.php' );
require_api( 'docnote_api.php' );
require_api( 'category_api.php' );
require_api( 'config_api.php' );
require_api( 'constant_inc.php' );
require_api( 'current_user_api.php' );
require_api( 'custom_field_api.php' );
require_api( 'database_api.php' );
require_api( 'email_queue_api.php' );
require_api( 'event_api.php' );
require_api( 'helper_api.php' );
require_api( 'history_api.php' );
require_api( 'lang_api.php' );
require_api( 'logging_api.php' );
require_api( 'project_api.php' );
require_api( 'relationship_api.php' );
require_api( 'sponsorship_api.php' );
require_api( 'string_api.php' );
require_api( 'user_api.php' );
require_api( 'user_pref_api.php' );
require_api( 'utility_api.php' );

require_lib( 'phpmailer/PHPMailerAutoload.php' );

# reusable object of class SMTP
$g_phpMailer = null;

/**
 * Indicates how generated emails will be processed by the shutdown function
 * at the end of the current request's execution; this is a binary flag:
 * - EMAIL_SHUTDOWN_SKIP       Initial state: do nothing (no generated emails)
 * - EMAIL_SHUTDOWN_GENERATED  Emails will be sent, unless $g_email_send_using_cronjob is ON
 * - EMAIL_SHUTDOWN_FORCE      All queued emails will be sent regardless of cronjob settings
 * @see email_shutdown_function()
 * @global $g_email_shutdown_processing
 */
$g_email_shutdown_processing = EMAIL_SHUTDOWN_SKIP;

/**
 * Regex for valid email addresses
 * @see string_insert_hrefs()
 * This pattern is consistent with email addresses validation logic
 * @see $g_validate_email
 * Uses the standard HTML5 pattern defined in
 * {@link http://www.w3.org/TR/html5/forms.html#valid-e-mail-address}
 * Note: the original regex from the spec has been modified to
 * - escape the '/' in the first character class definition
 * - remove the '^' and '$' anchors to allow matching anywhere in a string
 *
 * @return string
 */
function email_regex_simple() {
	return "/[a-zA-Z0-9.!#$%&'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*/";
}

/**
 * check to see that the format is valid and that the mx record exists
 * @param string $p_email An email address.
 * @return boolean
 */
function email_is_valid( $p_email ) {
	$t_validate_email = config_get( 'validate_email' );

	# if we don't validate then just accept
	# If blank email is allowed or current user is admin, then accept blank emails which are useful for
	# accounts that should never receive email notifications (e.g. anonymous account)
	if( OFF == $t_validate_email ||
		ON == config_get( 'use_ldap_email' ) ||
		( is_blank( $p_email ) && ( ON == config_get( 'allow_blank_email' ) || current_user_is_administrator() ) )
	) {
		return true;
	}

	# E-mail validation method
	# Note: PHPMailer offers alternative validation methods.
	# It was decided in PR 172 (https://github.com/mantisbt/mantisbt/pull/172)
	# to just default to HTML5 without over-complicating things for end users
	# by offering a potentially confusing choice between the different methods.
	# Refer to PHPMailer documentation for ValidateAddress method for details.
	# @link https://github.com/PHPMailer/PHPMailer/blob/v5.2.9/class.phpmailer.php#L863
	$t_method = 'html5';

	# check email address is a valid format
	log_event( LOG_EMAIL, "Validating address '$p_email' with method '$t_method'" );
	if( PHPMailer::ValidateAddress( $p_email, $t_method ) ) {
		$t_domain = substr( $p_email, strpos( $p_email, '@' ) + 1 );

		# see if we're limited to a set of known domains
		$t_limit_email_domains = config_get( 'limit_email_domains' );
		if( !empty( $t_limit_email_domains ) ) {
			foreach( $t_limit_email_domains as $t_email_domain ) {
				if( 0 == strcasecmp( $t_email_domain, $t_domain ) ) {
					return true; # no need to check mx record details (below) if we've explicity allowed the domain
				}
			}
			log_event( LOG_EMAIL, "failed - not in limited domains list '$t_limit_email_domains'" );
			return false;
		}

		if( ON == config_get( 'check_mx_record' ) ) {
			$t_mx = '';

			# Check for valid mx records
			if( getmxrr( $t_domain, $t_mx ) ) {
				return true;
			} else {
				$t_host = $t_domain . '.';

				# for no mx record... try dns check
				if( checkdnsrr( $t_host, 'ANY' ) ) {
					return true;
				}
				log_event( LOG_EMAIL, "failed - mx/dns record check" );
			}
		} else {
			# Email format was valid but didn't check for valid mx records
			return true;
		}
	} else {
		log_event( LOG_EMAIL, "failed - invalid address" );
	}

	# Everything failed.  The email is invalid
	return false;
}

/**
 * Check if the email address is valid trigger an ERROR if it isn't
 * @param string $p_email An email address.
 * @return void
 */
function email_ensure_valid( $p_email ) {
	if( !email_is_valid( $p_email ) ) {
		trigger_error( ERROR_EMAIL_INVALID, ERROR_FTP_CONNECT_ERROR );
	}
}

/**
 * Check if the email address is disposable
 * @param string $p_email An email address.
 * @return boolean
 */
function email_is_disposable( $p_email ) {
	if( !class_exists( 'DisposableEmailChecker' ) ) {
		require_lib( 'disposable/disposable.php' );
	}

	return DisposableEmailChecker::is_disposable_email( $p_email );
}

/**
 * Check if the email address is disposable
 * trigger an ERROR if it isn't
 * @param string $p_email An email address.
 * @return void
 */
function email_ensure_not_disposable( $p_email ) {
	if( email_is_disposable( $p_email ) ) {
		trigger_error( ERROR_EMAIL_DISPOSABLE, ERROR_FTP_CONNECT_ERROR );
	}
}

/**
 * email_notify_flag
 * Get the value associated with the specific action and flag.
 * For example, you can get the value associated with notifying "admin"
 * on action "new", i.e. notify administrators on new bugs which can be
 * ON or OFF.
 * @param string $p_action Action.
 * @param string $p_flag   Flag.
 * @return integer
 */
function email_notify_flag( $p_action, $p_flag ) {
	$t_notify_flags = config_get( 'notify_flags' );
	$t_default_notify_flags = config_get( 'default_notify_flags' );
	if( isset( $t_notify_flags[$p_action][$p_flag] ) ) {
		return $t_notify_flags[$p_action][$p_flag];
	} else if( isset( $t_default_notify_flags[$p_flag] ) ) {
		return $t_default_notify_flags[$p_flag];
	}

	return OFF;
}

/**
 * Collect valid email recipients for email notification
 * @todo yarick123: email_collect_recipients(...) will be completely rewritten to provide additional information such as language, user access,..
 * @todo yarick123:sort recipients list by language to reduce switches between different languages
 * @param integer $p_bug_id                  A bug identifier.
 * @param string  $p_notify_type             Notification type.
 * @param array   $p_extra_user_ids_to_email Array of additional email addresses to notify.
 * @return array
 */
function email_collect_recipients( $p_bug_id, $p_notify_type, array $p_extra_user_ids_to_email = array() ) {
	$t_recipients = array();

	# add explicitly specified users
	if( ON == email_notify_flag( $p_notify_type, 'explicit' ) ) {
		foreach ( $p_extra_user_ids_to_email as $t_user_id ) {
			$t_recipients[$t_user_id] = true;
			log_event( LOG_EMAIL_RECIPIENT, 'Issue = #%d, add explicitly specified user = @U%d', $p_bug_id, $t_user_id );
		}
	}

	# add Reporter
	if( ON == email_notify_flag( $p_notify_type, 'reporter' ) ) {
		$t_reporter_id = bug_get_field( $p_bug_id, 'reporter_id' );
		$t_recipients[$t_reporter_id] = true;
		log_event( LOG_EMAIL_RECIPIENT, 'Issue = #%d, add Reporter = @U%d', $p_bug_id, $t_reporter_id );
	}

	# add Handler
	if( ON == email_notify_flag( $p_notify_type, 'handler' ) ) {
		$t_handler_id = bug_get_field( $p_bug_id, 'handler_id' );

		if( $t_handler_id > 0 ) {
			$t_recipients[$t_handler_id] = true;
			log_event( LOG_EMAIL_RECIPIENT, 'Issue = #%d, add Handler = @U%d', $p_bug_id, $t_handler_id );
		}
	}

	$t_project_id = bug_get_field( $p_bug_id, 'project_id' );

	# add users monitoring the bug
	if( ON == email_notify_flag( $p_notify_type, 'monitor' ) ) {
		$t_query = 'SELECT DISTINCT user_id FROM {bug_monitor} WHERE bug_id=' . db_param();
		$t_result = db_query( $t_query, array( $p_bug_id ) );

		while( $t_row = db_fetch_array( $t_result ) ) {
			$t_user_id = $t_row['user_id'];
			$t_recipients[$t_user_id] = true;
			log_event( LOG_EMAIL_RECIPIENT, 'Issue = #%d, add Monitor = @U%d', $p_bug_id, $t_user_id );
		}
	}

	# add users who contributed docnotes
	$t_docnote_id = docnote_get_latest_id( $p_bug_id );
	$t_docnote_view = docnote_get_field( $t_docnote_id, 'view_state' );
	$t_docnote_date = docnote_get_field( $t_docnote_id, 'last_modified' );
	$t_bug = bug_get( $p_bug_id );
	$t_bug_date = $t_bug->last_updated;

	if( ON == email_notify_flag( $p_notify_type, 'docnotes' ) ) {
		$t_query = 'SELECT DISTINCT reporter_id FROM {docnote} WHERE bug_id = ' . db_param();
		$t_result = db_query( $t_query, array( $p_bug_id ) );
		while( $t_row = db_fetch_array( $t_result ) ) {
			$t_user_id = $t_row['reporter_id'];
			$t_recipients[$t_user_id] = true;
			log_event( LOG_EMAIL_RECIPIENT, 'Issue = #%d, add Note Author = @U%d', $p_bug_id, $t_user_id );
		}
	}

	# add project users who meet the thresholds
	$t_bug_is_private = bug_get_field( $p_bug_id, 'view_state' ) == VS_PRIVATE;
	$t_threshold_min = email_notify_flag( $p_notify_type, 'threshold_min' );
	$t_threshold_max = email_notify_flag( $p_notify_type, 'threshold_max' );
	$t_threshold_users = project_get_all_user_rows( $t_project_id, $t_threshold_min );
	foreach( $t_threshold_users as $t_user ) {
		if( $t_user['access_level'] <= $t_threshold_max ) {
			if( !$t_bug_is_private || access_compare_level( $t_user['access_level'], config_get( 'private_bug_threshold' ) ) ) {
				$t_recipients[$t_user['id']] = true;
				log_event( LOG_EMAIL_RECIPIENT, 'Issue = #%d, add Project User = @U%d', $p_bug_id, $t_user['id'] );
			}
		}
	}

	# add users as specified by plugins
	$t_recipients_include_data = event_signal( 'EVENT_NOTIFY_USER_INCLUDE', array( $p_bug_id, $p_notify_type ) );
	foreach( $t_recipients_include_data as $t_plugin => $t_recipients_include_data2 ) {
		foreach( $t_recipients_include_data2 as $t_callback => $t_recipients_included ) {
			# only handle if we get an array from the callback
			if( is_array( $t_recipients_included ) ) {
				foreach( $t_recipients_included as $t_user_id ) {
					$t_recipients[$t_user_id] = true;
					log_event( LOG_EMAIL_RECIPIENT, 'Issue = #%d, %s plugin added user @U%d', $p_bug_id, $t_plugin, $t_user_id );
				}
			}
		}
	}

	# FIXME: the value of $p_notify_type could at this stage be either a status
	# or a built-in actions such as 'owner and 'sponsor'. We have absolutely no
	# idea whether 'new' is indicating a new bug has been filed, or if the
	# status of an existing bug has been changed to 'new'. Therefore it is best
	# to just assume built-in actions have precedence over status changes.
	switch( $p_notify_type ) {
		case 'new':
		case 'feedback': # This isn't really a built-in action (delete me!)
		case 'reopened':
		case 'resolved':
		case 'closed':
		case 'docnote':
			$t_pref_field = 'email_on_' . $p_notify_type;
			break;
		case 'owner':
			# The email_on_assigned notification type is now effectively
			# email_on_change_of_handler.
			$t_pref_field = 'email_on_assigned';
			break;
		case 'deleted':
		case 'updated':
		case 'sponsor':
		case 'relation':
		case 'monitor':
		case 'priority': # This is never used, but exists in the database!
			# Issue #19459 these notification actions are not actually implemented
			# in the database and therefore aren't adjustable on a per-user
			# basis! The exception is 'monitor' that makes no sense being a
			# customisable per-user preference.
		default:
			# Anything not built-in is probably going to be a status
			$t_pref_field = 'email_on_status';
			break;
	}

	# @@@ we could optimize by modifiying user_cache() to take an array
	#  of user ids so we could pull them all in.  We'll see if it's necessary
	$t_final_recipients = array();

	$t_user_ids = array_keys( $t_recipients );
	user_cache_array_rows( $t_user_ids );
	user_pref_cache_array_rows( $t_user_ids );
	user_pref_cache_array_rows( $t_user_ids, $t_bug->project_id );

	# Check whether users should receive the emails
	# and put email address to $t_recipients[user_id]
	foreach( $t_recipients as $t_id => $t_ignore ) {
		# Possibly eliminate the current user
		if( ( auth_get_current_user_id() == $t_id ) && ( OFF == config_get( 'email_receive_own' ) ) ) {
			log_event( LOG_EMAIL_RECIPIENT, 'Issue = #%d, drop @U%d (own)', $p_bug_id, $t_id );
			continue;
		}

		# Eliminate users who don't exist anymore or who are disabled
		if( !user_exists( $t_id ) || !user_is_enabled( $t_id ) ) {
			log_event( LOG_EMAIL_RECIPIENT, 'Issue = #%d, drop @U%d (disabled)', $p_bug_id, $t_id );
			continue;
		}

		# Exclude users who have this notification type turned off
		if( $t_pref_field ) {
			$t_notify = user_pref_get_pref( $t_id, $t_pref_field );
			if( OFF == $t_notify ) {
				log_event( LOG_EMAIL_RECIPIENT, 'Issue = #%d, drop @U%d (pref %s off)', $p_bug_id, $t_id, $t_pref_field );
				continue;
			} else {
				# Users can define the severity of an issue before they are emailed for
				# each type of notification
				$t_min_sev_pref_field = $t_pref_field . '_min_severity';
				$t_min_sev_notify = user_pref_get_pref( $t_id, $t_min_sev_pref_field );
				$t_bug_severity = bug_get_field( $p_bug_id, 'severity' );

				if( $t_bug_severity < $t_min_sev_notify ) {
					log_event( LOG_EMAIL_RECIPIENT, 'Issue = #%d, drop @U%d (pref threshold)', $p_bug_id, $t_id );
					continue;
				}
			}
		}

		# exclude users who don't have at least viewer access to the bug,
		# or who can't see docnotes if the last update included a docnote
		if( !access_has_bug_level( config_get( 'view_bug_threshold', null, $t_id, $t_bug->project_id ), $p_bug_id, $t_id )
		 || $t_bug_date == $t_docnote_date && !access_has_docnote_level( config_get( 'view_bug_threshold', null, $t_id, $t_bug->project_id ), $t_docnote_id, $t_id )
		) {
			log_event( LOG_EMAIL_RECIPIENT, 'Issue = #%d, drop @U%d (access level)', $p_bug_id, $t_id );
			continue;
		}

		# check to exclude users as specified by plugins
		$t_recipient_exclude_data = event_signal( 'EVENT_NOTIFY_USER_EXCLUDE', array( $p_bug_id, $p_notify_type, $t_id ) );
		$t_exclude = false;
		foreach( $t_recipient_exclude_data as $t_plugin => $t_recipient_exclude_data2 ) {
			foreach( $t_recipient_exclude_data2 as $t_callback => $t_recipient_excluded ) {
				# exclude if any plugin returns true (excludes the user)
				if( $t_recipient_excluded ) {
					$t_exclude = true;
					log_event( LOG_EMAIL_RECIPIENT, 'Issue = #%d, %s plugin dropped user @U%d', $p_bug_id, $t_plugin, $t_id );
				}
			}
		}

		# user was excluded by a plugin
		if( $t_exclude ) {
			continue;
		}

		# Finally, let's get their emails, if they've set one
		$t_email = user_get_email( $t_id );
		if( is_blank( $t_email ) ) {
			log_event( LOG_EMAIL_RECIPIENT, 'Issue = #%d, drop @U%d (no email)', $p_bug_id, $t_id );
		} else {
			# @@@ we could check the emails for validity again but I think
			#   it would be too slow
			$t_final_recipients[$t_id] = $t_email;
		}
	}

	return $t_final_recipients;
}

/**
 * Send password to user.
 * @since Inforequest Alpha-4 the addition of the verification for ther modern
 * version of the email was included for the $g_mail_use_modern_template.
 * @author Favio Galvis <fgalvis@infortributos.com>.
 * @param integer $p_user_id      A valid user identifier.
 * @param string  $p_password     A valid password.
 * @param string  $p_confirm_hash Confirmation hash.
 * @param string  $p_admin_name   Administrator name.
 * @return void
 */
function email_signup( $p_user_id, $p_password, $p_confirm_hash, $p_admin_name = '' ) {
	if( ( OFF == config_get( 'send_reset_password' ) ) || ( OFF == config_get( 'enable_email_notification' ) ) ) {
		return;
	}

	#	@@@ thraxisp - removed to address #6084 - user won't have any settings yet,
	#  use same language as display for the email
	#  lang_push( user_pref_get_language( $p_user_id ) );
	# retrieve the username, email and real name
	$t_username = user_get_field( $p_user_id, 'username' );
	$t_email = user_get_email( $p_user_id );
        $t_realname = user_get_realname($p_user_id );

	# Build Welcome Message
	$t_subject = '[' . config_get( 'window_title' ) . '] ' . lang_get( 'new_account_subject' );

	if( !empty( $p_admin_name ) ) {
		$t_intro_text = sprintf( lang_get( 'new_account_greeting_admincreated' ), $p_admin_name, $t_username );
	} else {
		$t_intro_text = sprintf( lang_get( 'new_account_greeting' ), $t_username );
	}
        
        # Select the email version to be build acording to the config
        # of $g_email_mail_use_modern_template and the include of custom functions
        # on custom_functions_inc.php
        if( config_get( 'mail_use_modern_template' ) ) {
            $t_hash_url = string_get_confirm_hash_url( $p_user_id, $p_confirm_hash );
            $t_endnote = lang_get( 'new_account_message' ) . lang_get( 'new_account_do_not_reply' );
            $t_message = custom_function_override_email_on_singup ( $t_realname, $t_intro_text, $t_hash_url, $t_endnote);
            $t_message = custom_function_override_email_message($t_message);
        } else {
            $t_message = $t_intro_text . "\n\n" . string_get_confirm_hash_url( $p_user_id, $p_confirm_hash ) . "\n\n" . lang_get( 'new_account_message' ) . "\n\n" . lang_get( 'new_account_do_not_reply' );
        }
	# Send signup email regardless of mail notification pref
	# or else users won't be able to sign up
	if( !is_blank( $t_email ) ) {
		email_store( $t_email, $t_subject, $t_message, null, true );
		log_event( LOG_EMAIL, 'Signup Email = %s, Hash = %s, User = @U%d', $t_email, $p_confirm_hash, $p_user_id );
	}

	# lang_pop(); # see above
}

/**
 * Send confirm_hash URL to user forgets the password
 * @since Inforequest Alpha-4 the addition of the verification for ther modern
 * version of the email was included for the $g_mail_use_modern_template.
 * @author Favio Galvis <fgalvis@infortributos.com>.
 * @param integer $p_user_id      A valid user identifier.
 * @param string  $p_confirm_hash Confirmation hash.
 * @return void
 */
function email_send_confirm_hash_url( $p_user_id, $p_confirm_hash ) {
	if( OFF == config_get( 'send_reset_password' ) ||
		OFF == config_get( 'enable_email_notification' ) ) {
		return;
	}

	lang_push( user_pref_get_language( $p_user_id ) );

	# retrieve the username, email and real name
	$t_username = user_get_field( $p_user_id, 'username' );
	$t_email = user_get_email( $p_user_id );
        $t_realname = user_get_realname($p_user_id );

	$t_subject = '[' . config_get( 'window_title' ) . '] ' . lang_get( 'lost_password_subject' );
        
        # Select the email version to be build acording to the config
        # of $g_email_mail_use_modern_template and the include of custom functions
        # on custom_functions_inc.php
        if( config_get( 'mail_use_modern_template' ) ) {
            $t_intro_text = lang_get( 'reset_request_msg' );
            $t_hash_url = string_get_confirm_hash_url( $p_user_id, $p_confirm_hash );
            $t_user_field = lang_get( 'new_account_username' ) . ' ' . $t_username;
            $t_user_addrs = lang_get( 'new_account_IP' ) . ' ' . $_SERVER['REMOTE_ADDR'];
            $t_endnote = lang_get( 'new_account_do_not_reply' );
            $t_message = custom_function_override_email_on_lostpassword( $t_realname, $t_intro_text, $t_hash_url, $t_user_field, $t_user_addrs, $t_endnote);
            $t_message = custom_function_override_email_message($t_message);
        } else {
            $t_message = lang_get( 'reset_request_msg' ) . " \n\n" . string_get_confirm_hash_url( $p_user_id, $p_confirm_hash ) . " \n\n" . lang_get( 'new_account_username' ) . ' ' . $t_username . " \n" . lang_get( 'new_account_IP' ) . ' ' . $_SERVER['REMOTE_ADDR'] . " \n\n" . lang_get( 'new_account_do_not_reply' );
        }

	# Send password reset regardless of mail notification preferences
	# or else users won't be able to receive their reset passwords
	if( !is_blank( $t_email ) ) {
		email_store( $t_email, $t_subject, $t_message, null, true );
		log_event( LOG_EMAIL, 'Password reset for user @U%d sent to %s', $p_user_id, $t_email );
	}

	lang_pop();
}

/**
 * notify the selected group a new user has signup
 * @param string $p_username Username of new user.
 * @param string $p_email    Email address of new user.
 * @return void
 */
function email_notify_new_account( $p_username, $p_email ) {
	$t_threshold_min = config_get( 'notify_new_user_created_threshold_min' );
	$t_threshold_users = project_get_all_user_rows( ALL_PROJECTS, $t_threshold_min );

	foreach( $t_threshold_users as $t_user ) {
		lang_push( user_pref_get_language( $t_user['id'] ) );

		$t_recipient_email = user_get_email( $t_user['id'] );
		$t_subject = '[' . config_get( 'window_title' ) . '] ' . lang_get( 'new_account_subject' );

		$t_message = lang_get( 'new_account_signup_msg' ) . "\n\n" . lang_get( 'new_account_username' ) . ' ' . $p_username . "\n" . lang_get( 'new_account_email' ) . ' ' . $p_email . "\n" . lang_get( 'new_account_IP' ) . ' ' . $_SERVER['REMOTE_ADDR'] . "\n" . config_get_global( 'path' ) . "\n\n" . lang_get( 'new_account_do_not_reply' );

		if( !is_blank( $t_recipient_email ) ) {
			email_store( $t_recipient_email, $t_subject, $t_message );
			log_event( LOG_EMAIL, 'New Account Notify for email = \'%s\'', $t_recipient_email );
		}

		lang_pop();
	}
}


/**
 * send a generic email
 * $p_notify_type: use check who she get notified of such event.
 * $p_message_id: message id to be translated and included at the top of the email message.
 * Return false if it were problems sending email
 * @param integer $p_bug_id                  A bug identifier.
 * @param string  $p_notify_type             Notification type.
 * @param integer $p_message_id              Message identifier.
 * @param array   $p_header_optional_params  Optional Parameters (default null).
 * @param array   $p_extra_user_ids_to_email Array of additional users to email.
 * @return void
 */
function email_generic( $p_bug_id, $p_notify_type, $p_message_id = null, array $p_header_optional_params = null, array $p_extra_user_ids_to_email = array() ) {
	# @todo yarick123: email_collect_recipients(...) will be completely rewritten to provide additional information such as language, user access,..
	# @todo yarick123:sort recipients list by language to reduce switches between different languages
	$t_recipients = email_collect_recipients( $p_bug_id, $p_notify_type, $p_extra_user_ids_to_email );
	email_generic_to_recipients( $p_bug_id, $p_notify_type, $t_recipients, $p_message_id, $p_header_optional_params, $p_extra_user_ids_to_email );
}

/**
 * Sends a generic email to the specific set of recipients.
 *
 * @param integer $p_bug_id                  A bug identifier
 * @param string  $p_notify_type             Notification type
 * @param array   $p_recipients              Array of recipients (key: user id, value: email address)
 * @param integer $p_message_id              Message identifier
 * @param array   $p_header_optional_params  Optional Parameters (default null)
 * @return void
 */
function email_generic_to_recipients( $p_bug_id, $p_notify_type, array $p_recipients, $p_message_id = null, array $p_header_optional_params = null ) {
	if( OFF == config_get( 'enable_email_notification' ) ) {
		return;
	}

	ignore_user_abort( true );

	docnote_get_all_docnotes( $p_bug_id );

	$t_project_id = bug_get_field( $p_bug_id, 'project_id' );

	if( is_array( $p_recipients ) ) {
		# send email to every recipient
		foreach( $p_recipients as $t_user_id => $t_user_email ) {
			log_event( LOG_EMAIL, 'Issue = #%d, Type = %s, Msg = \'%s\', User = @U%d, Email = \'%s\'.', $p_bug_id, $p_notify_type, $p_message_id, $t_user_id, $t_user_email );

			# load (push) user language here as build_visible_bug_data assumes current language
			lang_push( user_pref_get_language( $t_user_id, $t_project_id ) );

			$t_visible_bug_data = email_build_visible_bug_data( $t_user_id, $p_bug_id, $p_message_id );
			email_bug_info_to_one_user( $t_visible_bug_data, $p_message_id, $t_project_id, $t_user_id, $p_header_optional_params );

			lang_pop();
		}
	}
}

/**
 * Send notices that a user is now monitoring the bug.  Typically this will only be sent when the added
 * user is not the logged in user.  This is assuming that receive own notifications is OFF (default).
 * @param integer $p_bug_id  A valid bug identifier.
 * @param integer $p_user_id A valid user identifier.
 * @return void
 */
function email_monitor_added( $p_bug_id, $p_user_id ) {
	log_event( LOG_EMAIL, 'Issue #%d monitored by user @U%d', $p_bug_id, $p_user_id );

	$t_opt = array();
	$t_opt[] = bug_format_id( $p_bug_id );
	$t_opt[] = user_get_name( $p_user_id );

	email_generic( $p_bug_id, 'monitor', 'email_notification_title_for_action_monitor', $t_opt, array( $p_user_id ) );
}

/**
 * send notices when a relationship is ADDED
 * @param integer $p_bug_id         A bug identifier.
 * @param integer $p_related_bug_id Related bug identifier.
 * @param integer $p_rel_type       Relationship type.
 * @return void
 */
function email_relationship_added( $p_bug_id, $p_related_bug_id, $p_rel_type ) {
	log_event( LOG_EMAIL, 'Relationship added: Issue #%d, related issue %d, relationship type %s.', $p_bug_id, $p_related_bug_id, $p_rel_type );

	$t_opt = array();
	$t_opt[] = bug_format_id( $p_related_bug_id );
	global $g_relationships;
	if( !isset( $g_relationships[$p_rel_type] ) ) {
		trigger_error( ERROR_RELATIONSHIP_NOT_FOUND, ERROR_FTP_CONNECT_ERROR );
	}

	$t_recipients = email_collect_recipients( $p_bug_id, 'relation' );

	# Recipient has to have access to both bugs to get the notification.
    $t_recipients = email_filter_recipients_for_bug( $p_bug_id, $t_recipients );
    $t_recipients = email_filter_recipients_for_bug( $p_related_bug_id, $t_recipients );

    email_generic_to_recipients( $p_bug_id, 'relation', $t_recipients, $g_relationships[$p_rel_type]['#notify_added'], $t_opt );
}

/**
 * Filter recipients to remove ones that don't have access to the specified bug.
 *
 * @param integer $p_bug_id       The bug id
 * @param array   $p_recipients   The recipients array (key: id, value: email)
 * @return array The filtered list of recipients in same format
 * @access private
 */
function email_filter_recipients_for_bug( $p_bug_id, array $p_recipients ) {
    $t_view_bug_threshold = config_get( 'view_bug_threshold' );

    $t_authorized_recipients = array();

    foreach( $p_recipients as $t_recipient_id => $t_recipient_email ) {
        if( access_has_bug_level( $t_view_bug_threshold, $p_bug_id, $t_recipient_id ) ) {
            $t_authorized_recipients[$t_recipient_id] = $t_recipient_email;
        }
    }

    return $t_authorized_recipients;
}

/**
 * send notices when a relationship is DELETED
 * @param integer $p_bug_id         A bug identifier.
 * @param integer $p_related_bug_id Related bug identifier.
 * @param integer $p_rel_type       Relationship type.
 * @return void
 */
function email_relationship_deleted( $p_bug_id, $p_related_bug_id, $p_rel_type ) {
	log_event( LOG_EMAIL, 'Relationship deleted: Issue #%d, related issue %d, relationship type %s.', $p_bug_id, $p_related_bug_id, $p_rel_type );

	$t_opt = array();
	$t_opt[] = bug_format_id( $p_related_bug_id );
	global $g_relationships;
	if( !isset( $g_relationships[$p_rel_type] ) ) {
		trigger_error( ERROR_RELATIONSHIP_NOT_FOUND, ERROR_FTP_CONNECT_ERROR );
	}

    $t_recipients = email_collect_recipients( $p_bug_id, 'relation' );

    # Recipient has to have access to both bugs to get the notification.
    $t_recipients = email_filter_recipients_for_bug( $p_bug_id, $t_recipients );
    $t_recipients = email_filter_recipients_for_bug( $p_related_bug_id, $t_recipients );

    email_generic_to_recipients( $p_bug_id, 'relation', $t_recipients, $g_relationships[$p_rel_type]['#notify_deleted'], $t_opt );
}

/**
 * send notices to all the handlers of the parent bugs when a child bug is RESOLVED
 * @param integer $p_bug_id A bug identifier.
 * @return void
 */
function email_relationship_child_resolved( $p_bug_id ) {
	email_relationship_child_resolved_closed( $p_bug_id, 'email_notification_title_for_action_relationship_child_resolved' );
}

/**
 * send notices to all the handlers of the parent bugs when a child bug is CLOSED
 * @param integer $p_bug_id A bug identifier.
 * @return void
 */
function email_relationship_child_closed( $p_bug_id ) {
	email_relationship_child_resolved_closed( $p_bug_id, 'email_notification_title_for_action_relationship_child_closed' );
}

/**
 * send notices to all the handlers of the parent bugs still open when a child bug is resolved/closed
 *
 * @param integer $p_bug_id     A bug identifier.
 * @param integer $p_message_id A message identifier.
 * @return void
 */
function email_relationship_child_resolved_closed( $p_bug_id, $p_message_id ) {
	# retrieve all the relationships in which the bug is the destination bug
	$t_relationship = relationship_get_all_dest( $p_bug_id );
	$t_relationship_count = count( $t_relationship );
	if( $t_relationship_count == 0 ) {
		# no parent bug found
		return;
	}

	for( $i = 0;$i < $t_relationship_count;$i++ ) {
		if( $t_relationship[$i]->type == BUG_DEPENDANT ) {
			$t_src_bug_id = $t_relationship[$i]->src_bug_id;
			$t_status = bug_get_field( $t_src_bug_id, 'status' );
			if( $t_status < config_get( 'bug_resolved_status_threshold' ) ) {

				# sent the notification just for parent bugs not resolved/closed
				$t_opt = array();
				$t_opt[] = bug_format_id( $p_bug_id );
				email_generic( $t_src_bug_id, 'handler', $p_message_id, $t_opt );
			}
		}
	}
}

/**
 * Store email in queue for sending
 *
 * @param string  $p_recipient Email recipient address.
 * @param string  $p_subject   Subject of email message.
 * @param string  $p_message   Body text of email message.
 * @param array   $p_headers   Array of additional headers to send with the email.
 * @param boolean $p_force     True to force sending of emails in shutdown function,
 *                             even when using cronjob
 * @return integer|null
 */
function email_store( $p_recipient, $p_subject, $p_message, array $p_headers = null, $p_force = false ) {
	global $g_email_shutdown_processing;

	$t_recipient = trim( $p_recipient );
	$t_subject = string_email( trim( $p_subject ) );
	$t_message = string_email_links( trim( $p_message ) );

	# short-circuit if no recipient is defined, or email disabled
	# note that this may cause signup messages not to be sent
	if( is_blank( $p_recipient ) || ( OFF == config_get( 'enable_email_notification' ) ) ) {
		return null;
	}

	$t_email_data = new EmailData;

	$t_email_data->email = $t_recipient;
	$t_email_data->subject = $t_subject;
	$t_email_data->body = $t_message;
	$t_email_data->metadata = array();
	$t_email_data->metadata['headers'] = $p_headers === null ? array() : $p_headers;
	$t_email_data->metadata['priority'] = config_get( 'mail_priority' );

	# Urgent = 1, Not Urgent = 5, Disable = 0
	$t_email_data->metadata['charset'] = 'utf-8';

	$t_hostname = '';
	if( isset( $_SERVER['SERVER_NAME'] ) ) {
		$t_hostname = $_SERVER['SERVER_NAME'];
	} else {
		$t_address = explode( '@', config_get( 'from_email' ) );
		if( isset( $t_address[1] ) ) {
			$t_hostname = $t_address[1];
		}
	}
	$t_email_data->metadata['hostname'] = $t_hostname;

	$t_email_id = email_queue_add( $t_email_data );

	# Set the email processing flag for the shutdown function
	$g_email_shutdown_processing |= EMAIL_SHUTDOWN_GENERATED;
	if( $p_force ) {
		$g_email_shutdown_processing |= EMAIL_SHUTDOWN_FORCE;
	}

	return $t_email_id;
}

/**
 * This function sends all the emails that are stored in the queue.
 * It will be called
 * - immediately after queueing messages in case of synchronous emails
 * - from a cronjob in case of asynchronous emails
 * If a failure occurs, then the function exits.
 * @todo In case of synchronous email sending, we may get a race condition where two requests send the same email.
 * @param boolean $p_delete_on_failure Indicates whether to remove email from queue on failure (default false).
 * @return void
 */
function email_send_all( $p_delete_on_failure = false ) {
	$t_ids = email_queue_get_ids();

	log_event( LOG_EMAIL, 'Processing e-mail queue (' . count( $t_ids ) . ' messages)' );

	foreach( $t_ids as $t_id ) {
		$t_email_data = email_queue_get( $t_id );
		$t_start = microtime( true );

		# check if email was not found.  This can happen if another request picks up the email first and sends it.
		if( $t_email_data === false ) {
			$t_email_sent = true;
			log_event( LOG_EMAIL, 'Message #$t_id has already been sent' );
		} else {
			log_event( LOG_EMAIL, 'Sending message #' . $t_id );
			$t_email_sent = email_send( $t_email_data );
		}

		if( !$t_email_sent ) {
			if( $p_delete_on_failure ) {
				email_queue_delete( $t_email_data->email_id );
			}

			# If unable to place the email in the email server queue and more
			# than 5 seconds have elapsed, then we assume that the server
			# connection is down, hence no point to continue trying with the
			# rest of the emails.
			if( microtime( true ) - $t_start > 5 ) {
				log_event( LOG_EMAIL, 'Server not responding for 5 seconds, aborting' );
				break;
			}
		}
	}
}

/**
 * This function sends an email message based on the supplied email data.
 *
 * @param EmailData $p_email_data Email Data object representing the email to send.
 * @return boolean
 */
function email_send( EmailData $p_email_data ) {
	global $g_phpMailer;

	$t_email_data = $p_email_data;

	$t_recipient = trim( $t_email_data->email );
	$t_subject = string_email( trim( $t_email_data->subject ) );
	$t_message = string_email_links( trim( $t_email_data->body ) );

	$t_debug_email = config_get_global( 'debug_email' );
	$t_mailer_method = config_get( 'phpMailer_method' );

	$t_log_msg = 'ERROR: Message could not be sent - ';

	if( is_null( $g_phpMailer ) ) {
		if( $t_mailer_method == PHPMAILER_METHOD_SMTP ) {
			register_shutdown_function( 'email_smtp_close' );
		}
		$t_mail = new PHPMailer( true );
	} else {
		$t_mail = $g_phpMailer;
	}

	if( isset( $t_email_data->metadata['hostname'] ) ) {
		$t_mail->Hostname = $t_email_data->metadata['hostname'];
	}

	# @@@ should this be the current language (for the recipient) or the default one (for the user running the command) (thraxisp)
	$t_lang = config_get( 'default_language' );
	if( 'auto' == $t_lang ) {
		$t_lang = config_get( 'fallback_language' );
	}
	$t_mail->SetLanguage( lang_get( 'phpmailer_language', $t_lang ) );

	# Select the method to send mail
	switch( config_get( 'phpMailer_method' ) ) {
		case PHPMAILER_METHOD_MAIL:
			$t_mail->IsMail();
			break;

		case PHPMAILER_METHOD_SENDMAIL:
			$t_mail->IsSendmail();
			break;

		case PHPMAILER_METHOD_SMTP:
			$t_mail->IsSMTP();

			# SMTP collection is always kept alive
			$t_mail->SMTPKeepAlive = true;

			if( !is_blank( config_get( 'smtp_username' ) ) ) {
				# Use SMTP Authentication
				$t_mail->SMTPAuth = true;
				$t_mail->Username = config_get( 'smtp_username' );
				$t_mail->Password = config_get( 'smtp_password' );
			}

			if( !is_blank( config_get( 'smtp_connection_mode' ) ) ) {
				$t_mail->SMTPSecure = config_get( 'smtp_connection_mode' );
			}

			$t_mail->Port = config_get( 'smtp_port' );

			break;
	}

	$t_mail->IsHTML( true );              # set email format to plain text
	$t_mail->WordWrap = 80;              # set word wrap to 80 characters
	$t_mail->Priority = $t_email_data->metadata['priority'];  # Urgent = 1, Not Urgent = 5, Disable = 0
	$t_mail->CharSet = $t_email_data->metadata['charset'];
	$t_mail->Host = config_get( 'smtp_host' );
	$t_mail->From = config_get( 'from_email' );
	$t_mail->Sender = config_get( 'return_path_email' );
	$t_mail->FromName = config_get( 'from_name' );
	$t_mail->AddCustomHeader( 'Auto-Submitted:auto-generated' );
	$t_mail->AddCustomHeader( 'X-Auto-Response-Suppress: All' );

	# Setup new line and encoding to avoid extra new lines with some smtp gateways like sendgrid.net
	$t_mail->LE         = "\r\n";
	$t_mail->Encoding   = 'quoted-printable';

	if( !empty( $t_debug_email ) ) {
		$t_message = 'To: ' . $t_recipient . "\n\n" . $t_message;
		$t_recipient = $t_debug_email;
	}

	try {
		$t_mail->AddAddress( $t_recipient, '' );
	}
	catch ( phpmailerException $e ) {
		log_event( LOG_EMAIL, $t_log_msg . $t_mail->ErrorInfo );
		$t_success = false;
		$t_mail->ClearAllRecipients();
		$t_mail->ClearAttachments();
		$t_mail->ClearReplyTos();
		$t_mail->ClearCustomHeaders();
		return $t_success;
	}

	$t_mail->Subject = $t_subject;
	$t_mail->Body = make_lf_crlf( "\n" . $t_message );

	if( isset( $t_email_data->metadata['headers'] ) && is_array( $t_email_data->metadata['headers'] ) ) {
		foreach( $t_email_data->metadata['headers'] as $t_key => $t_value ) {
			switch( $t_key ) {
				case 'Message-ID':
					# Note: hostname can never be blank here as we set metadata['hostname']
					# in email_store() where mail gets queued.
					if( !strchr( $t_value, '@' ) && !is_blank( $t_mail->Hostname ) ) {
						$t_value = $t_value . '@' . $t_mail->Hostname;
					}
					$t_mail->set( 'MessageID', '<' . $t_value . '>' );
					break;
				case 'In-Reply-To':
					$t_mail->AddCustomHeader( $t_key . ': <' . $t_value . '@' . $t_mail->Hostname . '>' );
					break;
				default:
					$t_mail->AddCustomHeader( $t_key . ': ' . $t_value );
					break;
			}
		}
	}

	try {
		$t_success = $t_mail->Send();
		if( $t_success ) {
			$t_success = true;

			if( $t_email_data->email_id > 0 ) {
				email_queue_delete( $t_email_data->email_id );
			}
		} else {
			# We should never get here, as an exception is thrown after failures
			log_event( LOG_EMAIL, $t_log_msg . $t_mail->ErrorInfo );
			$t_success = false;
		}
	}
	catch ( phpmailerException $e ) {
		log_event( LOG_EMAIL, $t_log_msg . $t_mail->ErrorInfo );
		$t_success = false;
	}

	$t_mail->ClearAllRecipients();
	$t_mail->ClearAttachments();
	$t_mail->ClearReplyTos();
	$t_mail->ClearCustomHeaders();

	return $t_success;
}

/**
 * closes opened kept alive SMTP connection (if it was opened)
 *
 * @return void
 */
function email_smtp_close() {
	global $g_phpMailer;

	if( !is_null( $g_phpMailer ) ) {
		if( $g_phpMailer->smtp->Connected() ) {
			$g_phpMailer->smtp->Quit();
			$g_phpMailer->smtp->Close();
		}
		$g_phpMailer = null;
	}
}

/**
 * formats the subject correctly
 * we include the project name, bug id, and summary.
 *
 * @param integer $p_bug_id A bug identifier.
 * @return string
 */
function email_build_subject( $p_bug_id ) {
	# grab the project name
	$p_project_name = project_get_field( bug_get_field( $p_bug_id, 'project_id' ), 'name' );

	# grab the subject (summary)
	$p_subject = bug_get_field( $p_bug_id, 'summary' );

	# pad the bug id with zeros
	$t_bug_id = bug_format_id( $p_bug_id );

	# build standard subject string
	$t_email_subject = '[' . $p_project_name . ' ' . $t_bug_id . ']: ' . $p_subject;

	# update subject as defined by plugins
	$t_email_subject = event_signal( 'EVENT_DISPLAY_EMAIL_BUILD_SUBJECT', $t_email_subject, array( 'bug_id' => $p_bug_id ) );

	return $t_email_subject;
}

/**
 * clean up LF to CRLF
 *
 * @param string $p_string String to convert linefeeds on.
 * @return string
 */
function make_lf_crlf( $p_string ) {
	$t_string = str_replace( "\n", "\r\n", $p_string );
	return str_replace( "\r\r\n", "\r\n", $t_string );
}

/**
 * Send a bug reminder to the given user(s), or to each user if the first parameter is an array
 *
 * @param integer|array $p_recipients User id or list of user ids array to send reminder to.
 * @param integer       $p_bug_id     Issue for which the reminder is sent.
 * @param string        $p_message    Optional message to add to the e-mail.
 * @return array List of users ids to whom the reminder e-mail was actually sent
 */
function email_bug_reminder( $p_recipients, $p_bug_id, $p_message ) {
	if( OFF == config_get( 'enable_email_notification' ) ) {
		return array();
	}

	if( !is_array( $p_recipients ) ) {
		$p_recipients = array(
			$p_recipients,
		);
	}

	$t_project_id = bug_get_field( $p_bug_id, 'project_id' );
	$t_sender_id = auth_get_current_user_id();
	$t_sender = user_get_name( $t_sender_id );

	$t_subject = email_build_subject( $p_bug_id );
	$t_date = date( config_get( 'normal_date_format' ) );

	$t_result = array();
	foreach( $p_recipients as $t_recipient ) {
		lang_push( user_pref_get_language( $t_recipient, $t_project_id ) );

		$t_email = user_get_email( $t_recipient );

		if( access_has_project_level( config_get( 'show_user_email_threshold' ), $t_project_id, $t_recipient ) ) {
			$t_sender_email = ' <' . user_get_email( $t_sender_id ) . '>';
		} else {
			$t_sender_email = '';
		}
		$t_header = "\n" . lang_get( 'on_date' ) . ' ' . $t_date . ', ' . $t_sender . ' ' . $t_sender_email . lang_get( 'sent_you_this_reminder_about' ) . ': ' . "\n\n";
		$t_contents = $t_header . string_get_bug_view_url_with_fqdn( $p_bug_id, $t_recipient ) . " \n\n" . $p_message;

		$t_id = email_store( $t_email, $t_subject, $t_contents );
		if( $t_id !== null ) {
			$t_result[] = $t_recipient;
		}
		log_event( LOG_EMAIL, 'queued reminder email #' . $t_id . ' for U' . $t_recipient );

		lang_pop();
	}

	return $t_result;
}

/**
 * Send bug info to given user
 * return true on success
 * @param array   $p_visible_bug_data       Array of bug data information.
 * @param string  $p_message_id             A message identifier.
 * @param integer $p_project_id             A project identifier.
 * @param integer $p_user_id                A valid user identifier.
 * @param array   $p_header_optional_params Array of additional email headers.
 * @return void
 */
function email_bug_info_to_one_user( array $p_visible_bug_data, $p_message_id, $p_project_id, $p_user_id, array $p_header_optional_params = null ) {
	$t_user_email = user_get_email( $p_user_id );

	# check whether email should be sent
	# @@@ can be email field empty? if yes - then it should be handled here
	if( ON !== config_get( 'enable_email_notification' ) || is_blank( $t_user_email ) ) {
		return;
	}

	# build subject
	$t_subject = email_build_subject( $p_visible_bug_data['email_bug'] );

	# build message

	if( is_array( $p_header_optional_params ) ) {
		$t_message = vsprintf( $t_message, $p_header_optional_params );
	}

	if( ( $t_message !== null ) && ( !is_blank( $t_message ) ) ) {
		$t_message .= " \n";
	}

	$t_message .= email_format_bug_message( $p_visible_bug_data, $p_message_id );

	# build headers
	$t_bug_id = $p_visible_bug_data['email_bug'];
	$t_message_md5 = md5( $t_bug_id . $p_visible_bug_data['email_date_submitted'] );
	$t_mail_headers = array(
		'keywords' => $p_visible_bug_data['set_category'],
	);
	if( $p_message_id == 'email_notification_title_for_action_bug_submitted' ) {
		$t_mail_headers['Message-ID'] = $t_message_md5;
	} else {
		$t_mail_headers['In-Reply-To'] = $t_message_md5;
	}

	# send mail
	email_store( $t_user_email, $t_subject, $t_message, $t_mail_headers );

	return;
}

/**
 * Build the bug info part of the message
 * @param array $p_visible_bug_data Bug data array to format.
 * @return string
 */
function email_format_bug_message( array $p_visible_bug_data, $p_message_id ) {
	$t_normal_date_format = config_get( 'normal_date_format' );
	$t_complete_date_format = config_get( 'complete_date_format' );
        if( config_get( 'mail_use_modern_template' ) ) {
            $t_email_separator1 = div_d_modern ();
            $t_email_separator2 = div_b_modern ();
            $t_email_separator3 = div_c_modern ();
        } else {
            $t_email_separator1 = config_get( 'email_separator1' );
            $t_email_separator2 = config_get( 'email_separator2' );
        }
	$t_email_padding_length = config_get( 'email_padding_length' );

	$t_status = $p_visible_bug_data['email_status'];

	$p_visible_bug_data['email_date_submitted'] = date( $t_complete_date_format, $p_visible_bug_data['email_date_submitted'] );
	$p_visible_bug_data['email_last_modified'] = date( $t_complete_date_format, $p_visible_bug_data['email_last_modified'] );

	$p_visible_bug_data['email_status'] = get_enum_element( 'status', $t_status );
	$p_visible_bug_data['email_severity'] = get_enum_element( 'severity', $p_visible_bug_data['email_severity'] );
	$p_visible_bug_data['email_priority'] = get_enum_element( 'priority', $p_visible_bug_data['email_priority'] );
	$p_visible_bug_data['email_reproducibility'] = get_enum_element( 'reproducibility', $p_visible_bug_data['email_reproducibility'] );

	$t_message = $t_email_separator1 . " \n";
        
        $t_message .= '<div style="text-align:center;font-family:Helvetica,Arial,sans-serif;font-weight:bold;font-size:15px;margin-bottom:0;margin-top:3px;color:#5f5f5f;line-height:135%">' . lang_get_defaulted( $p_message_id, null ) . '</div>';

        $t_message .= email_format_attribute( $p_visible_bug_data, 'email_summary' );
                
	if( isset( $p_visible_bug_data['email_bug_view_url'] ) ) {
		$t_message .= $p_visible_bug_data['email_bug_view_url'] . " \n";
		$t_message .= $t_email_separator2 . " \n";
	}

	$t_message .= email_format_attribute( $p_visible_bug_data, 'email_reporter' );
	$t_message .= email_format_attribute( $p_visible_bug_data, 'email_handler' );
	$t_message .= $t_email_separator2 . " \n";
	$t_message .= email_format_attribute( $p_visible_bug_data, 'email_project' );
        # custom fields formatting
	foreach( $p_visible_bug_data['custom_fields'] as $t_custom_field_name => $t_custom_field_data ) {
		$t_message .= utf8_str_pad( lang_get_defaulted( $t_custom_field_name, null ) . ': ', $t_email_padding_length, ' ', STR_PAD_RIGHT );
		$t_message .= string_custom_field_value_for_email( $t_custom_field_data['value'], $t_custom_field_data['type'] );
		$t_message .= '<br>';
	}
	//$t_message .= email_format_attribute( $p_visible_bug_data, 'email_bug' );
	//$t_message .= email_format_attribute( $p_visible_bug_data, 'email_category' );
	//$t_message .= email_format_attribute( $p_visible_bug_data, 'email_reproducibility' );
	//$t_message .= email_format_attribute( $p_visible_bug_data, 'email_severity' );
	//$t_message .= email_format_attribute( $p_visible_bug_data, 'email_priority' );
	$t_message .= email_format_attribute( $p_visible_bug_data, 'email_status' );
	//$t_message .= email_format_attribute( $p_visible_bug_data, 'email_target_version' );

	# end foreach custom field

	if( config_get( 'bug_resolved_status_threshold' ) <= $t_status ) {
		$p_visible_bug_data['email_resolution'] = get_enum_element( 'resolution', $p_visible_bug_data['email_resolution'] );
		$t_message .= email_format_attribute( $p_visible_bug_data, 'email_resolution' );
		//$t_message .= email_format_attribute( $p_visible_bug_data, 'email_fixed_in_version' );
	}
	$t_message .= $t_email_separator2 . " \n";

	$t_message .= email_format_attribute( $p_visible_bug_data, 'email_date_submitted' );
	//$t_message .= email_format_attribute( $p_visible_bug_data, 'email_last_modified' );
	$t_message .= $t_email_separator2 . " \n";

	//$t_message .= lang_get( 'email_description' ) . ": \n" . $p_visible_bug_data['email_description'] . "\n";

	/*if( !is_blank( $p_visible_bug_data['email_steps_to_reproduce'] ) ) {
		$t_message .= '<br>' . lang_get( 'email_steps_to_reproduce' ) . ": \n" . $p_visible_bug_data['email_steps_to_reproduce'] . "\n";
	}*/

	/*if( !is_blank( $p_visible_bug_data['email_additional_information'] ) ) {
		$t_message .= '<br>' . lang_get( 'email_additional_information' ) . ": \n" . $p_visible_bug_data['email_additional_information'] . "\n";
	}*/

	if( isset( $p_visible_bug_data['relations'] ) ) {
		if( $p_visible_bug_data['relations'] != '' ) {
			$t_message .= $t_email_separator2 . "\n" . str_pad( lang_get( 'bug_relationships' ), 20 ) . str_pad( lang_get( 'id' ), 8 ) . lang_get( 'summary' ) . "\n" . $t_email_separator2 . "\n" . $p_visible_bug_data['relations'];
		}
	}

	# Sponsorship
	if( isset( $p_visible_bug_data['sponsorship_total'] ) && ( $p_visible_bug_data['sponsorship_total'] > 0 ) ) {
		$t_message .= $t_email_separator2 . " \n";
		$t_message .= sprintf( lang_get( 'total_sponsorship_amount' ), sponsorship_format_amount( $p_visible_bug_data['sponsorship_total'] ) ) . '<br>';

		if( isset( $p_visible_bug_data['sponsorships'] ) ) {
			foreach( $p_visible_bug_data['sponsorships'] as $t_sponsorship ) {
				$t_date_added = date( config_get( 'normal_date_format' ), $t_sponsorship->date_submitted );

				$t_message .= $t_date_added . ': ';
				$t_message .= user_get_name( $t_sponsorship->user_id );
				$t_message .= ' (' . sponsorship_format_amount( $t_sponsorship->amount ) . ')' . " \n";
			}
		}
	}

	$t_message .= $t_email_separator2 . " \n\n";

	# format docnotes
	if( config_get( 'mail_show_bug_notes' ) ) {
            foreach( $p_visible_bug_data['docnotes'] as $t_docnote ) {
                    $t_last_modified = date( $t_normal_date_format, $t_docnote->last_modified );

                    $t_formatted_docnote_id = docnote_format_id( $t_docnote->id );
                    $t_docnote_link = string_process_docnote_link( config_get( 'docnote_link_tag' ) . $t_docnote->id, false, false, true );

                    if( $t_docnote->time_tracking > 0 ) {
                            $t_time_tracking = ' ' . lang_get( 'time_tracking' ) . ' ' . db_minutes_to_hhmm( $t_docnote->time_tracking ) . '<br>';
                    } else {
                            $t_time_tracking = '';
                    }

                    if( user_exists( $t_docnote->reporter_id ) ) {
                            $t_access_level = access_get_project_level( $p_visible_bug_data['email_project_id'], $t_docnote->reporter_id );
                            $t_access_level_string = ' (' . get_enum_element( 'access_levels', $t_access_level ) . ') - ';
                    } else {
                            $t_access_level_string = '';
                    }

                    $t_string = ' (' . $t_formatted_docnote_id . ') ' . user_get_name( $t_docnote->reporter_id ) . $t_access_level_string . $t_last_modified . '<br>' . $t_time_tracking . ' ' . $t_docnote_link;

                    $t_message .= $t_email_separator2 . " \n";
                    $t_message .= $t_string . " \n";
                    $t_message .= $t_email_separator2 . " \n";
                    $t_message .= $t_docnote->note . '<br>';
            }
        }

	# format history
	if( array_key_exists( 'history', $p_visible_bug_data ) ) {
		$t_message .= lang_get( 'bug_history' ) . '<br>';
		$t_message .= custom_function_override_email_table_history_head_header () . utf8_str_pad( lang_get( 'date_modified' ), 17 ) . custom_function_override_email_table_history_head_body () . utf8_str_pad( lang_get( 'username' ), 15 ) . custom_function_override_email_table_history_head_body () . utf8_str_pad( lang_get( 'field' ), 25 ) . custom_function_override_email_table_history_head_body () . utf8_str_pad( lang_get( 'change' ), 20 ) . custom_function_override_email_table_history_head_footer ();

		//$t_message .= $t_email_separator2 . " \n";
                $t_last_history_1 = NULL;
                $t_last_history_2 = NULL;
                $t_array_lenght = count( $p_visible_bug_data['history'] );
                $i = 0;
		foreach( $p_visible_bug_data['history'] as $t_raw_history_item ) {
			$t_localized_item = history_localize_item( $t_raw_history_item['field'], $t_raw_history_item['type'], $t_raw_history_item['old_value'], $t_raw_history_item['new_value'], false );
                        if ( ($t_array_lenght-2) == $i )
                        {
                            $t_last_history_2 = custom_function_override_email_table_history_body_header () . utf8_str_pad( date( $t_normal_date_format, $t_raw_history_item['date'] ), 17 ) . custom_function_override_email_table_history_body_body () . utf8_str_pad( $t_raw_history_item['username'], 15 ) . custom_function_override_email_table_history_body_body () . utf8_str_pad( $t_localized_item['note'], 25 ) . custom_function_override_email_table_history_body_body () . utf8_str_pad( $t_localized_item['change'], 20 ) . custom_function_override_email_table_history_body_footer ();
                        }
			$t_last_history_1 = custom_function_override_email_table_history_body_header () . utf8_str_pad( date( $t_normal_date_format, $t_raw_history_item['date'] ), 17 ) . custom_function_override_email_table_history_body_body () . utf8_str_pad( $t_raw_history_item['username'], 15 ) . custom_function_override_email_table_history_body_body () . utf8_str_pad( $t_localized_item['note'], 25 ) . custom_function_override_email_table_history_body_body () . utf8_str_pad( $t_localized_item['change'], 20 ) . custom_function_override_email_table_history_body_footer ();
                        $i++;
		}
                if ( $t_last_history_2 != NULL ) {
                    $t_message .= $t_last_history_2;
                }
                $t_message .= $t_last_history_1;
                $t_message .= custom_function_override_email_table_history_footer_footer ();
		$t_message .= $t_email_separator3 . " \n\n";
	}

        if( config_get( 'mail_use_modern_template' ) ) {
            $t_message = custom_function_override_email_message($t_message);
            return $t_message;
        } else {
            return $t_message;
        }
}

/**
 * if $p_visible_bug_data contains specified attribute the function
 * returns concatenated translated attribute name and original
 * attribute value. Else return empty string.
 * @param array  $p_visible_bug_data Visible Bug Data array.
 * @param string $p_attribute_id     Attribute ID.
 * @return string
 */
function email_format_attribute( array $p_visible_bug_data, $p_attribute_id ) {
	if( array_key_exists( $p_attribute_id, $p_visible_bug_data ) ) {
		return utf8_str_pad( lang_get( $p_attribute_id ) . ': ', config_get( 'email_padding_length' ), ' ', STR_PAD_RIGHT ) . $p_visible_bug_data[$p_attribute_id] . '<br>';
	}
	return '';
}

/**
 * Build the bug raw data visible for specified user to be translated and sent by email to the user
 * (Filter the bug data according to user access level)
 * return array with bug data. See usage in email_format_bug_message(...)
 * @param integer $p_user_id    A user identifier.
 * @param integer $p_bug_id     A bug identifier.
 * @param string  $p_message_id A message identifier.
 * @return array
 */
function email_build_visible_bug_data( $p_user_id, $p_bug_id, $p_message_id ) {
	# Override current user with user to construct bug data for.
	# This is to make sure that APIs that check against current user (e.g. relationship) work correctly.
	$t_current_user_id = current_user_set( $p_user_id );

	$t_project_id = bug_get_field( $p_bug_id, 'project_id' );
	$t_user_access_level = user_get_access_level( $p_user_id, $t_project_id );
	$t_user_docnote_order = user_pref_get_pref( $p_user_id, 'docnote_order' );
	$t_user_docnote_limit = user_pref_get_pref( $p_user_id, 'email_docnote_limit' );

	$t_row = bug_get_extended_row( $p_bug_id );
	$t_bug_data = array();

	$t_bug_data['email_bug'] = $p_bug_id;

	if( $p_message_id !== 'email_notification_title_for_action_bug_deleted' ) {
		$t_bug_data['email_bug_view_url'] = string_get_bug_view_url_with_fqdn( $p_bug_id );
	}

	if( access_compare_level( $t_user_access_level, config_get( 'view_handler_threshold' ) ) ) {
		if( 0 != $t_row['handler_id'] ) {
			$t_bug_data['email_handler'] = user_get_name( $t_row['handler_id'] );
		} else {
			$t_bug_data['email_handler'] = '';
		}
	}

	$t_bug_data['email_reporter'] = user_get_name( $t_row['reporter_id'] );
	$t_bug_data['email_project_id'] = $t_row['project_id'];
	$t_bug_data['email_project'] = project_get_field( $t_row['project_id'], 'name' );

	$t_category_name = category_full_name( $t_row['category_id'], false );
	$t_bug_data['email_category'] = $t_category_name;

	$t_bug_data['email_date_submitted'] = $t_row['date_submitted'];
	$t_bug_data['email_last_modified'] = $t_row['last_updated'];

	$t_bug_data['email_status'] = $t_row['status'];
	$t_bug_data['email_severity'] = $t_row['severity'];
	$t_bug_data['email_priority'] = $t_row['priority'];
	$t_bug_data['email_reproducibility'] = $t_row['reproducibility'];

	$t_bug_data['email_resolution'] = $t_row['resolution'];
	$t_bug_data['email_fixed_in_version'] = $t_row['fixed_in_version'];

	if( !is_blank( $t_row['target_version'] ) && access_compare_level( $t_user_access_level, config_get( 'roadmap_view_threshold' ) ) ) {
		$t_bug_data['email_target_version'] = $t_row['target_version'];
	}

	$t_bug_data['email_summary'] = $t_row['summary'];
	$t_bug_data['email_description'] = $t_row['description'];
	$t_bug_data['email_additional_information'] = $t_row['additional_information'];
	$t_bug_data['email_steps_to_reproduce'] = $t_row['steps_to_reproduce'];

	$t_bug_data['set_category'] = '[' . $t_bug_data['email_project'] . '] ' . $t_category_name;

	$t_bug_data['custom_fields'] = custom_field_get_linked_fields( $p_bug_id, $t_user_access_level );
	$t_bug_data['docnotes'] = docnote_get_all_visible_docnotes( $p_bug_id, $t_user_docnote_order, $t_user_docnote_limit, $p_user_id );

	# put history data
	if( ( ON == config_get( 'history_default_visible' ) ) && access_compare_level( $t_user_access_level, config_get( 'view_history_threshold' ) ) ) {
		$t_bug_data['history'] = history_get_raw_events_array( $p_bug_id, $p_user_id );
	}

	# Sponsorship Information
	if( ( config_get( 'enable_sponsorship' ) == ON ) && ( access_has_bug_level( config_get( 'view_sponsorship_total_threshold' ), $p_bug_id, $p_user_id ) ) ) {
		$t_sponsorship_ids = sponsorship_get_all_ids( $p_bug_id );
		$t_bug_data['sponsorship_total'] = sponsorship_get_amount( $t_sponsorship_ids );

		if( access_has_bug_level( config_get( 'view_sponsorship_details_threshold' ), $p_bug_id, $p_user_id ) ) {
			$t_bug_data['sponsorships'] = array();
			foreach( $t_sponsorship_ids as $t_id ) {
				$t_bug_data['sponsorships'][] = sponsorship_get( $t_id );
			}
		}
	}

	$t_bug_data['relations'] = relationship_get_summary_text( $p_bug_id );

	current_user_set( $t_current_user_id );

	return $t_bug_data;
}

/**
 * The email sending shutdown function
 * Will send any queued emails, except when $g_email_send_using_cronjob = ON.
 * If $g_email_shutdown_processing EMAIL_SHUTDOWN_FORCE flag is set, emails
 * will be sent regardless of cronjob setting.
 * @return void
 */
function email_shutdown_function() {
	global $g_email_shutdown_processing;

	# Nothing to do if
	# - no emails have been generated in the current request
	# - system is configured to use cron job (unless processing is forced)
	if(    $g_email_shutdown_processing == EMAIL_SHUTDOWN_SKIP
		|| (   !( $g_email_shutdown_processing & EMAIL_SHUTDOWN_FORCE )
			&& config_get( 'email_send_using_cronjob' )
		   )
	) {
		return;
	}

	$t_msg ='Shutdown function called for ' . $_SERVER['SCRIPT_NAME'];
	if( $g_email_shutdown_processing & EMAIL_SHUTDOWN_FORCE ) {
		$t_msg .= ' (email processing forced)';
	}
	log_event( LOG_EMAIL, $t_msg );

	if( $g_email_shutdown_processing ) {
		email_send_all();
	}
}
