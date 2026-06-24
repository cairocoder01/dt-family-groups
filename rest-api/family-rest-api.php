<?php
if ( !defined( 'ABSPATH' ) ) { exit; }

/**
 * REST endpoint: GET /wp-json/dt-family-groups/v1/family-tree/{group_id}
 *
 * Returns the members of the group along with their in-group family relationships
 * (spouse, parents, children) so the client can render a generational tree.
 */
class DT_Family_Groups_REST_API {

    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'add_api_routes' ] );
    }

    public function add_api_routes() {
        $namespace = 'dt-family-groups/v1';

        register_rest_route(
            $namespace,
            '/family-tree/(?P<post_id>\d+)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'family_tree' ],
                'permission_callback' => [ $this, 'family_tree_permissions' ],
                'args'                => [
                    'post_id' => [
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param );
                        },
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );
    }

    public function family_tree_permissions( WP_REST_Request $request ) {
        $post_id = (int) $request->get_param( 'post_id' );
        return DT_Posts::can_view( 'groups', $post_id );
    }

    public function family_tree( WP_REST_Request $request ) {
        $post_id = (int) $request->get_param( 'post_id' );

        $group = DT_Posts::get_post( 'groups', $post_id );
        if ( is_wp_error( $group ) ) {
            return $group;
        }

        $group_type  = $group['group_type']['key'] ?? '';
        $raw_members = $group['members'] ?? [];

        // Build a set of member IDs for quick in-group lookups.
        $member_id_set = [];
        foreach ( $raw_members as $m ) {
            $member_id_set[ $m['ID'] ] = true;
        }

        $members = [];
        foreach ( $raw_members as $m ) {
            $contact = DT_Posts::get_post( 'contacts', $m['ID'] );
            if ( is_wp_error( $contact ) ) {
                // Include with minimal data if the current user can't view the contact.
                $members[] = [
                    'ID'             => $m['ID'],
                    'name'           => $m['post_title'] ?? __( '(Private)', 'dt-family-groups' ),
                    'post_url'       => '',
                    'gender'         => '',
                    'marital_status' => '',
                    'marital_color'  => '',
                    'spouse_ids'     => [],
                    'parent_ids'     => [],
                    'children_ids'   => [],
                ];
                continue;
            }

            $marital_key   = $contact['family_marital_status']['key'] ?? '';
            $marital_label = $contact['family_marital_status']['label'] ?? '';
            $marital_color = $this->marital_color( $marital_key );

            $spouse_ids   = $this->extract_in_group_ids( $contact['family_spouse'] ?? [], $member_id_set );
            $parent_ids   = $this->extract_in_group_ids( $contact['family_parents'] ?? [], $member_id_set );
            $children_ids = $this->extract_in_group_ids( $contact['family_children'] ?? [], $member_id_set );

            $members[] = [
                'ID'             => $m['ID'],
                'name'           => $contact['name'] ?? $m['post_title'],
                'post_url'       => $contact['permalink'] ?? '',
                'gender'         => $contact['gender']['key'] ?? '',
                'marital_status' => $marital_label,
                'marital_color'  => $marital_color,
                'spouse_ids'     => $spouse_ids,
                'parent_ids'     => $parent_ids,
                'children_ids'   => $children_ids,
            ];
        }

        return [
            'group_id'   => $post_id,
            'group_type' => $group_type,
            'members'    => $members,
        ];
    }

    private function extract_in_group_ids( array $connections, array $member_id_set ) {
        $ids = [];
        foreach ( $connections as $c ) {
            $id = (int) ( $c['ID'] ?? 0 );
            if ( $id && isset( $member_id_set[ $id ] ) ) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    private function marital_color( string $key ) {
        $colors = [
            'single'   => '#2196F3',
            'married'  => '#4CAF50',
            'divorced' => '#FF9800',
            'widowed'  => '#9E9E9E',
        ];
        return $colors[ $key ] ?? '';
    }
}
DT_Family_Groups_REST_API::instance();
