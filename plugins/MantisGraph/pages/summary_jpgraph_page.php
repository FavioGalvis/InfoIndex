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
 * Initial code for this addon came from Duncan Lisset
 * Modified and "make MantisBT codeguidlines compatible" by Rufinus
 * @package MantisBT
 * @copyright Copyright 2000 - 2002  Kenzaburo Ito - kenito@300baud.org
 * @copyright Copyright 2002  MantisBT Team - mantisbt-dev@lists.sourceforge.net
 * @link http://www.mantisbt.org
 */

require_once( 'core.php' );
access_ensure_project_level( config_get( 'view_summary_threshold' ) );

layout_page_header();

layout_page_begin( 'summary_page.php' );

?>
    <br />
<?php

print_summary_menu( 'summary_jpgraph_page.php' );

$t_graphs = array( 'summary_graph_cumulative_bydate', 'summary_graph_bydeveloper', 'summary_graph_byreporter',
		'summary_graph_byseverity', 'summary_graph_bystatus', 'summary_graph_byresolution',
		'summary_graph_bycategory', 'summary_graph_bypriority' );
$t_wide = plugin_config_get( 'summary_graphs_per_row' );
$t_width = plugin_config_get( 'window_width' );
$t_graph_width = (int)( ( $t_width - 50 ) / $t_wide );

token_delete( TOKEN_GRAPH );

?>

    <div class="col-md-12 col-xs-12">
        <div class="space-10"></div>

        <div class="widget-box widget-color-blue2">
            <div class="widget-header widget-header-small">
                <h4 class="widget-title lighter">
                    <i class="ace-icon fa fa-bars"></i>
                    <?php echo lang_get('summary_title') ?>
                </h4>
            </div>

            <div class="widget-body">
                <div class="widget-main no-padding">

                    <div class="table-responsive">
                        <table class="table table-condensed">
                            <?php
                            $t_graph_count = count($t_graphs );
                            for ( $t_pos = 0; $t_pos < $t_graph_count; $t_pos++ ) {
                                if( 0 == ( $t_pos % $t_wide ) ) {
                                    print( "<tr>\n" );
                                }
                                echo '<td>';
                                printf( '<img src="%s.php&amp;width=%d" alt="" />', plugin_page( $t_graphs[$t_pos] ), $t_graph_width );
                                echo '</td>';
                                if( ( $t_wide - 1 ) == ( $t_pos % $t_wide ) ) {
                                    print( "</tr>\n" );
                                }
                            }
                            ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php
layout_page_end();
