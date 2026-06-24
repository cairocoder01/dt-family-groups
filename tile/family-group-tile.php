<?php
if ( !defined( 'ABSPATH' ) ) { exit; }

/**
 * Extends the Groups post type for family tracking:
 *   - Adds a 'Family' option to group_type
 *   - Adds a 'Family' tile visible on all groups (with full content shown only for family-type groups)
 *   - family_group_issues (tags) for family-level issue tracking
 *   - Renders a generational family tree when the group type is 'family'
 */
class DT_Family_Groups_Group_Tile {

    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        add_filter( 'dt_custom_fields_settings', [ $this, 'dt_custom_fields' ], 20, 2 );
        add_filter( 'dt_details_additional_tiles', [ $this, 'dt_details_additional_tiles' ], 10, 2 );
        add_action( 'dt_details_additional_section', [ $this, 'dt_add_section' ], 30, 2 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ], 99 );
    }

    public function dt_custom_fields( array $fields, string $post_type = '' ) {
        if ( $post_type !== 'groups' ) {
            return $fields;
        }

        if ( isset( $fields['group_type'] ) ) {
            $fields['group_type']['default']['family'] = [
                'label'       => __( 'Family', 'dt-family-groups' ),
                'description' => __( 'A family unit or household group.', 'dt-family-groups' ),
            ];
        }

        $fields['family_group_issues'] = [
            'name'        => __( 'Family Issues', 'dt-family-groups' ),
            'description' => __( 'Tags for marital and family challenges within this group', 'dt-family-groups' ),
            'type'        => 'tags',
            'default'     => [],
            'tile'        => 'family_group',
            'icon'        => get_template_directory_uri() . '/dt-assets/images/tag.svg?v=2',
        ];

        return $fields;
    }

    public function dt_details_additional_tiles( $tiles, $post_type = '' ) {
        if ( $post_type === 'groups' ) {
            $tiles['family_group'] = [
                'label'       => __( 'Family', 'dt-family-groups' ),
                'description' => __( 'Family group details and generational tree', 'dt-family-groups' ),
            ];
        }
        return $tiles;
    }

    public function enqueue_scripts() {
        if ( is_singular( 'groups' ) ) {
            wp_enqueue_script(
                'dt-family-groups-gen-map',
                plugin_dir_url( dirname( __FILE__ ) ) . 'js/family-gen-map.js',
                [ 'jquery' ],
                '0.1',
                true
            );
            wp_localize_script( 'dt-family-groups-gen-map', 'dtFamilyGroups', [
                'rest_url' => esc_url_raw( rest_url() ),
                'nonce'    => wp_create_nonce( 'wp_rest' ),
                'post_id'  => get_the_ID(),
                'i18n'     => [
                    'loading'      => __( 'Loading family tree…', 'dt-family-groups' ),
                    'error'        => __( 'Could not load family tree.', 'dt-family-groups' ),
                    'no_relations' => __( 'No family relationships found among group members. Add spouse, parent, or child connections to contacts in this group.', 'dt-family-groups' ),
                    'not_family'   => __( 'Set this group\'s type to "Family" to display the family tree.', 'dt-family-groups' ),
                ],
            ] );
        }
    }

    public function dt_add_section( $section, $post_type ) {
        if ( $post_type !== 'groups' || $section !== 'family_group' ) {
            return;
        }

        $post = DT_Posts::get_post( $post_type, get_the_ID() );
        $group_type = $post['group_type']['key'] ?? '';
        ?>
        <style>
            #dt-family-tree-container {
                min-height: 80px;
                overflow-x: auto;
                padding: 8px 0;
            }
            .dt-family-tree-msg {
                color: #999;
                font-style: italic;
                padding: 8px 0;
                font-size: 0.9em;
            }
            .dt-family-tree {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 32px;
                padding: 8px 0 16px;
            }
            .dt-family-generation {
                display: flex;
                flex-direction: row;
                justify-content: center;
                flex-wrap: wrap;
                gap: 16px;
                position: relative;
            }
            .dt-family-unit {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 0;
            }
            .dt-family-couple {
                display: flex;
                flex-direction: row;
                align-items: stretch;
                gap: 0;
            }
            .dt-family-person {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 4px;
                background: #f8f8f8;
                border: 1px solid #ddd;
                border-radius: 6px;
                padding: 8px 14px;
                min-width: 90px;
                max-width: 140px;
                text-align: center;
                font-size: 0.85em;
                line-height: 1.3;
            }
            .dt-family-person a {
                color: #3f729b;
                text-decoration: none;
                font-weight: 500;
                word-break: break-word;
            }
            .dt-family-person a:hover { text-decoration: underline; }
            .dt-family-person .dt-person-gender {
                font-size: 0.75em;
                color: #aaa;
            }
            .dt-family-person .dt-person-status {
                font-size: 0.72em;
                color: #fff;
                border-radius: 10px;
                padding: 1px 7px;
                margin-top: 2px;
            }
            .dt-family-spouse-join {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 0 6px;
                font-size: 1.1em;
                color: #bbb;
                cursor: default;
            }
            .dt-family-children-row {
                display: flex;
                flex-direction: row;
                justify-content: center;
                flex-wrap: wrap;
                gap: 10px;
                margin-top: 16px;
                padding-top: 12px;
                border-top: 2px solid #e0e0e0;
                position: relative;
            }
            .dt-family-children-row::before {
                content: '';
                position: absolute;
                top: -2px;
                left: 50%;
                transform: translateX(-50%);
                width: 2px;
                height: 12px;
                background: #e0e0e0;
            }
            .dt-family-lone {
                opacity: 0.7;
            }
        </style>

        <div class="cell small-12">
            <div id="dt-family-tree-container">
                <p class="dt-family-tree-msg">
                    <?php esc_html_e( 'Loading family tree…', 'dt-family-groups' ); ?>
                </p>
            </div>
        </div>

        <script>
            window.dtFamilyGroupType = <?php echo wp_json_encode( $group_type ); ?>;
        </script>
        <?php
    }
}
DT_Family_Groups_Group_Tile::instance();
