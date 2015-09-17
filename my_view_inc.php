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
 * My View include file
 * @copyright Copyright 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 * @package MantisBT
 *
 * @uses access_api.php
 * @uses bug_api.php
 * @uses category_api.php
 * @uses config_api.php
 * @uses constant_inc.php
 * @uses current_user_api.php
 * @uses file_api.php
 * @uses filter_api.php
 * @uses filter_constants_inc.php
 * @uses helper_api.php
 * @uses icon_api.php
 * @uses lang_api.php
 * @uses print_api.php
 * @uses project_api.php
 * @uses string_api.php
 */

if( !defined( 'MY_VIEW_INC_ALLOW' ) ) {
	return;
}

require_api( 'access_api.php' );
require_api( 'bug_api.php' );
require_api( 'category_api.php' );
require_api( 'config_api.php' );
require_api( 'constant_inc.php' );
require_api( 'current_user_api.php' );
require_api( 'file_api.php' );
require_api( 'filter_api.php' );
require_api( 'filter_constants_inc.php' );
require_api( 'helper_api.php' );
require_api( 'icon_api.php' );
require_api( 'lang_api.php' );
require_api( 'print_api.php' );
require_api( 'project_api.php' );
require_api( 'string_api.php' );

$t_filter = current_user_get_bug_filter();
if( $t_filter === false ) {
	$t_filter = filter_get_default();
}

$t_sort = $t_filter['sort'];
$t_dir = $t_filter['dir'];

$t_icon_path = config_get( 'icon_path' );
$t_update_bug_threshold = config_get( 'update_bug_threshold' );
$t_bug_resolved_status_threshold = config_get( 'bug_resolved_status_threshold' );
$t_hide_status_default = config_get( 'hide_status_default' );
$t_default_show_changed = config_get( 'default_show_changed' );

$c_filter['assigned'] = filter_create_assigned_to_unresolved( helper_get_current_project(), $t_current_user_id );
$t_url_link_parameters['assigned'] = FILTER_PROPERTY_HANDLER_ID . '=' . $t_current_user_id . '&' . FILTER_PROPERTY_HIDE_STATUS . '=' . $t_bug_resolved_status_threshold;

$c_filter['recent_mod'] = array(
	FILTER_PROPERTY_CATEGORY_ID => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_SEVERITY => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_STATUS => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_HIGHLIGHT_CHANGED => $t_default_show_changed,
	FILTER_PROPERTY_REPORTER_ID => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_HANDLER_ID => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_RESOLUTION => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_BUILD => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_VERSION => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_HIDE_STATUS => array(
		'0' => META_FILTER_NONE,
	),
	FILTER_PROPERTY_MONITOR_USER_ID => array(
		'0' => META_FILTER_ANY,
	),
);
$t_url_link_parameters['recent_mod'] = FILTER_PROPERTY_HIDE_STATUS . '=none';

$c_filter['reported'] = filter_create_reported_by( helper_get_current_project(), $t_current_user_id );
$t_url_link_parameters['reported'] = FILTER_PROPERTY_REPORTER_ID . '=' . $t_current_user_id . '&' . FILTER_PROPERTY_HIDE_STATUS . '=' . $t_hide_status_default;

$c_filter['resolved'] = array(
	FILTER_PROPERTY_CATEGORY_ID => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_SEVERITY => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_STATUS => array(
		'0' => $t_bug_resolved_status_threshold,
	),
	FILTER_PROPERTY_HIGHLIGHT_CHANGED => $t_default_show_changed,
	FILTER_PROPERTY_REPORTER_ID => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_HANDLER_ID => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_RESOLUTION => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_BUILD => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_VERSION => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_HIDE_STATUS => array(
		'0' => $t_hide_status_default,
	),
	FILTER_PROPERTY_MONITOR_USER_ID => array(
		'0' => META_FILTER_ANY,
	),
);
$t_url_link_parameters['resolved'] = FILTER_PROPERTY_STATUS . '=' . $t_bug_resolved_status_threshold . '&' . FILTER_PROPERTY_HIDE_STATUS . '=' . $t_bug_resolved_status_threshold;


$c_filter['unassigned'] = filter_create_assigned_to_unresolved( helper_get_current_project(), 0 );
$t_url_link_parameters['unassigned'] = FILTER_PROPERTY_HANDLER_ID . '=[none]' . '&' . FILTER_PROPERTY_HIDE_STATUS . '=' . $t_hide_status_default;

# TODO: check. handler value looks wrong

$c_filter['monitored'] = filter_create_monitored_by( helper_get_current_project(), $t_current_user_id );
$t_url_link_parameters['monitored'] = FILTER_PROPERTY_MONITOR_USER_ID . '=' . $t_current_user_id . '&' . FILTER_PROPERTY_HIDE_STATUS . '=' . $t_hide_status_default;

$c_filter['feedback'] = array(
	FILTER_PROPERTY_CATEGORY_ID => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_SEVERITY => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_STATUS => array(
		'0' => config_get( 'bug_feedback_status' ),
	),
	FILTER_PROPERTY_HIGHLIGHT_CHANGED => $t_default_show_changed,
	FILTER_PROPERTY_REPORTER_ID => array(
		'0' => $t_current_user_id,
	),
	FILTER_PROPERTY_HANDLER_ID => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_RESOLUTION => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_BUILD => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_VERSION => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_HIDE_STATUS => array(
		'0' => $t_hide_status_default,
	),
	FILTER_PROPERTY_MONITOR_USER_ID => array(
		'0' => META_FILTER_ANY,
	),
);
$t_url_link_parameters['feedback'] = FILTER_PROPERTY_REPORTER_ID . '=' . $t_current_user_id . '&' . FILTER_PROPERTY_STATUS . '=' . config_get( 'bug_feedback_status' ) . '&' . FILTER_PROPERTY_HIDE_STATUS . '=' . $t_hide_status_default;

$c_filter['verify'] = array(
	FILTER_PROPERTY_CATEGORY_ID => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_SEVERITY => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_STATUS => array(
		'0' => $t_bug_resolved_status_threshold,
	),
	FILTER_PROPERTY_HIGHLIGHT_CHANGED => $t_default_show_changed,
	FILTER_PROPERTY_REPORTER_ID => array(
		'0' => $t_current_user_id,
	),
	FILTER_PROPERTY_HANDLER_ID => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_RESOLUTION => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_BUILD => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_VERSION => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_HIDE_STATUS => array(
		'0' => $t_hide_status_default,
	),
	FILTER_PROPERTY_MONITOR_USER_ID => array(
		'0' => META_FILTER_ANY,
	),
);
$t_url_link_parameters['verify'] = FILTER_PROPERTY_REPORTER_ID . '=' . $t_current_user_id . '&' . FILTER_PROPERTY_STATUS . '=' . $t_bug_resolved_status_threshold;

$c_filter['my_comments'] = array(
	FILTER_PROPERTY_CATEGORY_ID => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_SEVERITY => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_STATUS => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_HIGHLIGHT_CHANGED => $t_default_show_changed,
	FILTER_PROPERTY_REPORTER_ID => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_HANDLER_ID => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_RESOLUTION => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_BUILD => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_VERSION => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_HIDE_STATUS => array(
		'0' => $t_hide_status_default,
	),
	FILTER_PROPERTY_MONITOR_USER_ID => array(
		'0' => META_FILTER_ANY,
	),
	FILTER_PROPERTY_NOTE_USER_ID=> array(
		'0' => META_FILTER_MYSELF,
	),
);

$t_url_link_parameters['my_comments'] = FILTER_PROPERTY_NOTE_USER_ID. '=' . META_FILTER_MYSELF . '&' . FILTER_PROPERTY_HIDE_STATUS . '=' . $t_hide_status_default;
$t_rows = filter_get_bug_rows( $f_page_number, $t_per_page, $t_page_count, $t_bug_count, $c_filter[$t_box_title] );

$t_count_bis = count ($t_rows);
for( $i = 0;$i < $t_count_bis; $i++ ) {
    if ( $t_box_title == 'unassigned'){
            $t_bug_assign = $t_rows[$i];
            $t_current_auth = access_get_project_level( $t_bug_assign->project_id );
            $t_bug_status = $t_bug_assign->status;
            $t_enum_workflow_bis = get_status_option_list( $t_current_auth, $t_bug_status, FALSE, NULL, $t_bug_assign->project_id );
            if( count( $t_enum_workflow_bis ) > 0 ) {
            } else {
                unset($t_rows[$i]);
            }
    }
}
$t_rows = array_values($t_rows);
# Improve performance by caching category data in one pass
if( helper_get_current_project() == 0 ) {
	$t_categories = array();
	foreach( $t_rows as $t_row ) {
		$t_categories[] = $t_row->category_id;
	}

	category_cache_array_rows( array_unique( $t_categories ) );
}

$t_filter = array_merge( $c_filter[$t_box_title], $t_filter );

$t_box_title_label = lang_get( 'my_view_title_' . $t_box_title );

$t_collapse_block = is_collapsed( $t_box_title );
$t_block_css = $t_collapse_block ? 'collapsed' : '';
$t_block_icon = $t_collapse_block ? 'fa-chevron-down' : 'fa-chevron-up';

$t_bug_string = $t_bug_count == 1 ? 'bug' : 'bugs';

# -- ====================== BUG LIST ========================= --
?>
<div id="<?php echo $t_box_title ?>" class="widget-box widget-color-blue2 <?php echo $t_block_css ?>">
	<div class="widget-header widget-header-small">
		<h4 class="widget-title lighter">
			<i class="ace-icon fa fa-list-alt"></i>
<?php
#-- Box title
$t_box_url = html_entity_decode( config_get( 'bug_count_hyperlink_prefix' ) ).'&' . $t_url_link_parameters[$t_box_title];
print_link( $t_box_url, $t_box_title_label, false, 'white' );

# -- Viewing range info
if( count( $t_rows ) > 0 ) {
	$v_start = $t_filter[FILTER_PROPERTY_ISSUES_PER_PAGE] * ( $f_page_number - 1 ) + 1;
	$v_end = $v_start + count( $t_rows ) - 1;
} else {
	$v_start = 0;
	$v_end = 0;
}
echo '<span class="badge"> ' . " $v_start - $v_end / $t_bug_count " . ' </span>';
?>
		</h4>
		<div class="widget-toolbar">
			<a data-action="collapse" href="#">
				<i class="1 ace-icon fa <?php echo $t_block_icon ?> bigger-125"></i>
			</a>
		</div>
	</div>

	<div class="widget-body">
		<div class="widget-main no-padding">
			<div class="table-responsive">
				<table class="table table-bordered table-condensed table-striped table-hover">
<tbody>
<?php
# -- Loop over bug rows and create $v_* variables --
$t_count = count( $t_rows );
if( $t_count == 0 ) {
	echo '<tr><td>&#160;</td></tr>';
}
for( $i = 0;$i < $t_count; $i++ ) {
    $t_bug = $t_rows[$i];
    
    $t_summary = string_display_line_links( $t_bug->summary );
                    $t_last_updated = date( config_get( 'normal_date_format' ), $t_bug->last_updated );

                    # Check for attachments
                    $t_attachment_count = 0;
                    # TODO: factor in the allow_view_own_attachments configuration option
                    # instead of just using a global check.
                    if( ( file_can_view_bug_attachments( $t_bug->id, null ) ) ) {
                            $t_attachment_count = file_bug_attachment_count( $t_bug->id );
                    }

                    # grab the project name
                    $project_name = project_get_field( $t_bug->project_id, 'name' );

                    if( VS_PRIVATE == $t_bug->view_state ) {
                        $t_bug_class = 'my-buglist-private';
                    } else {
                            $t_bug_class = '';
                    }
                    ?>

            <tr class="my-buglist-bug <?php echo $t_bug_class?>">
                    <?php
                    # -- Bug ID and details link + Pencil shortcut --?>
                    <td class="nowrap width-13 my-buglist-id">
                            <?php
                                    print_bug_link( $t_bug->id );

                                    echo '<br />';

                                    # choose color based on status
                                    $status_label = html_get_status_css_class( $t_bug->status, auth_get_current_user_id(), $t_bug->project_id );
                                    $t_status = string_attribute( get_enum_element( 'status', bug_get_field( $t_bug->id, 'status' ), $t_bug->project_id ) );
                                    echo '<i class="fa fa-square-o fa-xlg ' . $status_label . '" title="' . $t_status . '"></i> ';

                                    if( !bug_is_readonly( $t_bug->id ) && access_has_bug_level( $t_update_bug_threshold, $t_bug->id ) ) {
                                            echo '<a class="edit" href="' . string_get_bug_update_url( $t_bug->id ) . '"><img src="' . $t_icon_path . 'update.png' . '" alt="' . lang_get( 'update_bug_button' ) . '" /></a>';
                                    }

                                    if( ON == config_get( 'show_priority_text' ) ) {
                                            print_formatted_priority_string( $t_bug );
                                    } else {
                                            print_status_icon( $t_bug->priority );
                                    }

                                    if( $t_attachment_count > 0 ) {
                                            $t_href = string_get_bug_view_url( $t_bug->id ) . '#attachments';
                                            $t_href_title = sprintf( lang_get( 'view_attachments_for_issue' ), $t_attachment_count, $t_bug->id );
                                            $t_alt_text = $t_attachment_count . lang_get( 'word_separator' ) . lang_get( 'attachments' );
                                            echo '<a class="attachments" href="' . $t_href . '" title="' . $t_href_title . '">';
                                            echo '<img src="' . $t_icon_path . 'attachment.png" alt="' . $t_alt_text . '" title="' . $t_alt_text . '" /></a>';
                                    }

                                    if( VS_PRIVATE == $t_bug->view_state ) {
                                            echo '<img src="' . $t_icon_path . 'protected.gif" width="8" height="15" alt="' . lang_get( 'private' ) . '" />';
                                    }
                                    ?>
                    </td>

                    <?php
                    # -- Summary --?>
                    <td>
                            <?php
                                    if( ON == config_get( 'show_document_project_links' ) && helper_get_current_project() != $t_bug->project_id ) {
                                            echo '<span>[', string_display_line( project_get_name( $t_bug->project_id ) ), '] </span>';
                                    }
                                    $t_bug_url = string_get_bug_view_url( $t_bug->id, null );
                                    $t_bug_url_title = string_html_specialchars( sprintf( lang_get( 'label' ), lang_get( 'issue_id' ) . $t_bug->id ) . lang_get( 'word_separator' ) . $t_bug->summary );
                                    echo '<span><a href="' . $t_bug_url . '" title="' . $t_bug_url_title . '">' . $t_summary . '</a></span><br />';
                    ?>
                            <?php
                    # type project name if viewing 'all projects' or bug is in subproject
                    echo '<span class="small">', string_display_line( category_full_name( $t_bug->category_id, true, $t_bug->project_id ) ), '</span>';

                    echo '<span class="small"> - ';
                    if( $t_bug->last_updated > strtotime( '-' . $t_filter[FILTER_PROPERTY_HIGHLIGHT_CHANGED] . ' hours' ) ) {
                            echo '<strong>' . $t_last_updated . '</strong>';
                    } else {
                            echo $t_last_updated;
                    }
                    echo '</span>';
                    ?>
                    </td>
            </tr>
            <?php
	# -- end of Repeating bug row --
    }

# -- ====================== end of BUG LIST ========================= --
?>
</tbody>
</table>
	</div>
	</div>
	<div class="widget-toolbox padding-8 clearfix">
		<?php
			echo ' ' . $t_bug_count . ' ' . lang_get( $t_bug_string ) . ' ';
		?>
		<a class="btn btn-xs btn-primary btn-white btn-round" href="<?php echo $t_box_url ?>">
			<?php echo lang_get( 'view_bugs_link' ) ?>
		</a>
	</div>
</div>
</div>
<?php
# Free the memory allocated for the rows in this box since it is not longer needed.
unset( $t_rows );

