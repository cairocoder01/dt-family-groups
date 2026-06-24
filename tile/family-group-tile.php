<?php
if ( !defined( 'ABSPATH' ) ) { exit; }

/**
 * Extends the Groups post type for family tracking:
 *   - Adds a 'Family' option to group_type
 *   - Adds a 'Family' tile visible on all groups
 *   - family_group_issues (tags) for family-level issue tracking
 *   - Renders a generational family tree (compact in tile, full in modal)
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
                [],
                '0.9',
                true
            );

            $post       = DT_Posts::get_post( 'groups', get_the_ID() );
            $group_name = $post['name'] ?? '';
            $group_type = $post['group_type']['key'] ?? '';

            wp_localize_script( 'dt-family-groups-gen-map', 'dtFamilyGroups', [
                'rest_url'   => esc_url_raw( rest_url() ),
                'nonce'      => wp_create_nonce( 'wp_rest' ),
                'post_id'    => get_the_ID(),
                'group_name' => esc_html( $group_name ),
                'group_type' => esc_html( $group_type ),
                'i18n'       => [
                    'loading'           => __( 'Loading family tree…', 'dt-family-groups' ),
                    'error'             => __( 'Could not load family tree.', 'dt-family-groups' ),
                    'no_members'        => __( 'No members in this group yet.', 'dt-family-groups' ),
                    'not_family'        => __( 'Set this group\'s type to "Family" to display the family tree.', 'dt-family-groups' ),
                    'other_members'     => __( 'Other Group Members', 'dt-family-groups' ),
                    'no_connections'    => __( 'No family connections recorded yet.', 'dt-family-groups' ),
                    'expand'            => __( 'View Full Family Tree', 'dt-family-groups' ),
                    'close'             => __( 'Close', 'dt-family-groups' ),
                    'family_tree_title' => __( 'Family Tree', 'dt-family-groups' ),
                ],
            ] );
        }
    }

    public function dt_add_section( $section, $post_type ) {
        if ( $post_type !== 'groups' || $section !== 'family_group' ) {
            return;
        }
        ?>

        <style>
            /* ── Shared card / tree styles ─────────────────────────────────── */
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
                gap: 16px;
                padding: 8px 0 16px;
            }

            /* ── Parents row ────────────────────────────────────────────────── */
            .dt-family-parents-row {
                display: flex;
                flex-direction: row;
                align-items: flex-start;
                justify-content: center;
                flex-wrap: wrap;
                gap: 10px 20px;
                padding-bottom: 12px;
                border-bottom: 1px solid #ececec;
                width: 100%;
            }

            /* ── Children row ───────────────────────────────────────────────── */
            .dt-family-children-row {
                display: flex;
                flex-direction: row;
                align-items: flex-start;
                justify-content: center;
                flex-wrap: wrap;
                gap: 8px;
                width: 100%;
            }

            /* ── Non-universal parent label on child cards ──────────────────── */
            .dt-parent-label {
                font-size: 0.68em;
                color: #999;
                font-style: italic;
                margin-top: 1px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                max-width: 100%;
            }

            .dt-family-unit {
                display: flex;
                flex-direction: column;
                align-items: center;
                flex-shrink: 0;
            }
            .dt-family-couple {
                display: flex;
                flex-direction: row;
                align-items: center;
                flex-wrap: nowrap;
                gap: 0;
            }
            .dt-family-person {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 3px;
                background: #f8f8f8;
                border: 1px solid #ddd;
                border-radius: 6px;
                padding: 7px 12px;
                min-width: 80px;
                max-width: 130px;
                text-align: center;
                font-size: 0.82em;
                line-height: 1.3;
            }
            .dt-family-person a {
                color: #3f729b;
                text-decoration: none;
                font-weight: 500;
                word-break: break-word;
            }
            .dt-family-person a:hover { text-decoration: underline; }
            .dt-person-gender { font-size: 0.75em; color: #aaa; }
            .dt-person-status {
                font-size: 0.7em;
                color: #fff;
                border-radius: 10px;
                padding: 1px 6px;
            }
            .dt-family-spouse-join {
                padding: 0 4px;
                font-size: 1em;
                color: #bbb;
                flex-shrink: 0;
                align-self: center;
            }
            /* ── Unconnected members section ───────────────────────────────── */
            .dt-family-unconnected {
                width: 100%;
                border-top: 1px dashed #e0e0e0;
                padding-top: 12px;
                margin-top: 4px;
            }
            .dt-family-section-label {
                font-size: 0.75em;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                color: #aaa;
                margin: 0 0 10px;
                font-weight: 600;
            }
            .dt-family-person-grid {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                justify-content: center;
            }
            .dt-family-person-grid .dt-family-person {
                opacity: 0.75;
            }

            /* ── Multiple independent family lines (side-by-side) ─────────── */
            .dt-family-root-branch {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 16px;
                width: 100%;
            }
            .dt-family-root-branches {
                display: flex;
                flex-direction: row;
                align-items: flex-start;
                width: 100%;
            }
            .dt-family-root-branches .dt-family-root-branch {
                flex: 1;
                min-width: 0;
                width: auto;
                padding: 0 8px;
            }
            .dt-family-branch-divider {
                width: 1px;
                background: #ddd;
                align-self: stretch;
                flex-shrink: 0;
            }

            /* ── Compact tile container ────────────────────────────────────── */
            #dt-family-tree-container {
                position: relative;
                max-height: 260px;
                overflow: hidden;
            }
            #dt-family-tree-container::after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                height: 56px;
                background: linear-gradient(to bottom, transparent, #fff);
                pointer-events: none;
            }
            /* When no clipping is needed (short trees) remove the fade */
            #dt-family-tree-container.dt-no-clip { max-height: none; overflow: visible; }
            #dt-family-tree-container.dt-no-clip::after { display: none; }

            /* ── Expand button ─────────────────────────────────────────────── */
            .dt-family-expand-btn {
                display: none; /* shown by JS once tree loads */
                margin-top: 6px;
                width: 100%;
                background: none;
                border: 1px solid #3f729b;
                color: #3f729b;
                border-radius: 4px;
                padding: 5px 10px;
                font-size: 0.82em;
                cursor: pointer;
                text-align: center;
            }
            .dt-family-expand-btn:hover { background: #3f729b; color: #fff; }

            /* ── Modal overlay ─────────────────────────────────────────────── */
            .dt-family-modal-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.65);
                z-index: 99999;
                align-items: center;
                justify-content: center;
                padding: 20px;
                box-sizing: border-box;
            }
            .dt-family-modal-overlay.is-open { display: flex; }

            .dt-family-modal-box {
                background: #fff;
                border-radius: 8px;
                width: 100%;
                max-width: 980px;
                max-height: 90vh;
                display: flex;
                flex-direction: column;
                box-shadow: 0 8px 40px rgba(0,0,0,0.35);
                overflow: hidden;
            }
            .dt-family-modal-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 14px 18px;
                border-bottom: 1px solid #e8e8e8;
                flex-shrink: 0;
            }
            .dt-family-modal-title {
                font-size: 1em;
                font-weight: 600;
                margin: 0;
                color: #333;
            }
            .dt-family-modal-close {
                background: none;
                border: none;
                font-size: 1.1em;
                cursor: pointer;
                color: #888;
                padding: 4px 8px;
                line-height: 1;
                border-radius: 4px;
            }
            .dt-family-modal-close:hover { background: #f0f0f0; color: #333; }

            .dt-family-modal-body {
                overflow: auto;
                padding: 20px;
                flex: 1;
                /* wider cards in the modal */
            }
            .dt-family-modal-body .dt-family-person {
                min-width: 100px;
                max-width: 160px;
                padding: 9px 14px;
                font-size: 0.88em;
            }
            .dt-family-modal-body .dt-family-tree { gap: 36px; }
            .dt-family-modal-body .dt-family-generation { gap: 14px; }
        </style>

        <div class="cell small-12">
            <div id="dt-family-tree-container">
                <p class="dt-family-tree-msg">
                    <?php esc_html_e( 'Loading family tree…', 'dt-family-groups' ); ?>
                </p>
            </div>
            <button id="dt-family-open-modal" class="dt-family-expand-btn">
                <?php esc_html_e( 'View Full Family Tree', 'dt-family-groups' ); ?> ↗
            </button>
        </div>

        <!-- Full-tree modal -->
        <div id="dt-family-modal" class="dt-family-modal-overlay" role="dialog"
             aria-modal="true" aria-labelledby="dt-family-modal-title-text">
            <div class="dt-family-modal-box">
                <div class="dt-family-modal-header">
                    <h3 class="dt-family-modal-title" id="dt-family-modal-title-text">
                        <?php esc_html_e( 'Family Tree', 'dt-family-groups' ); ?>
                    </h3>
                    <button id="dt-family-modal-close" class="dt-family-modal-close"
                            aria-label="<?php esc_attr_e( 'Close', 'dt-family-groups' ); ?>">✕</button>
                </div>
                <div id="dt-family-modal-tree" class="dt-family-modal-body"></div>
            </div>
        </div>

        <?php
    }
}
DT_Family_Groups_Group_Tile::instance();
