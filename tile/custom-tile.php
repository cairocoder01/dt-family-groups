<?php
if ( !defined( 'ABSPATH' ) ) { exit; }

/**
 * Adds family relationship fields to the Contacts post type.
 *
 * Fields:
 *   - family_marital_status  (key_select)  Single / Married / Divorced / Widowed
 *   - family_issues          (tags)        Open-ended marital/family issue tags
 *   - family_spouse          (connection)  Bidirectional spouse link (contacts_to_family_spouse)
 *   - family_parents         (connection)  Parent contacts (from side of contacts_to_family_children)
 *   - family_children        (connection)  Child contacts  (to   side of contacts_to_family_children)
 */
class DT_Family_Groups_Contact_Tile {

    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        add_filter( 'dt_details_additional_tiles', [ $this, 'dt_details_additional_tiles' ], 10, 2 );
        add_filter( 'dt_custom_fields_settings', [ $this, 'dt_custom_fields' ], 10, 2 );
    }

    public function dt_details_additional_tiles( $tiles, $post_type = '' ) {
        if ( $post_type === 'contacts' ) {
            $tiles['family'] = [
                'label'       => __( 'Family', 'dt-family-groups' ),
                'description' => __( 'Family relationships and marital status', 'dt-family-groups' ),
            ];
        }
        return $tiles;
    }

    public function dt_custom_fields( array $fields, string $post_type = '' ) {
        if ( $post_type !== 'contacts' ) {
            return $fields;
        }

        $fields['family_marital_status'] = [
            'name'    => __( 'Marital Status', 'dt-family-groups' ),
            'type'    => 'key_select',
            'default' => [
                ''        => [ 'label' => '' ],
                'single'   => [
                    'label' => __( 'Single', 'dt-family-groups' ),
                    'color' => '#2196F3',
                ],
                'married'  => [
                    'label' => __( 'Married', 'dt-family-groups' ),
                    'color' => '#4CAF50',
                ],
                'divorced' => [
                    'label' => __( 'Divorced', 'dt-family-groups' ),
                    'color' => '#FF9800',
                ],
                'widowed'  => [
                    'label' => __( 'Widowed', 'dt-family-groups' ),
                    'color' => '#9E9E9E',
                ],
            ],
            'tile'    => 'family',
            'icon'    => get_template_directory_uri() . '/dt-assets/images/circle-square-triangle.svg?v=2',
        ];

        $fields['family_issues'] = [
            'name'        => __( 'Family Issues', 'dt-family-groups' ),
            'description' => __( 'Tags for marital and family challenges', 'dt-family-groups' ),
            'type'        => 'tags',
            'default'     => [],
            'tile'        => 'family',
            'icon'        => get_template_directory_uri() . '/dt-assets/images/tag.svg?v=2',
        ];

        $fields['family_spouse'] = [
            'name'          => __( 'Spouse', 'dt-family-groups' ),
            'description'   => __( 'Spouse or partner of this contact', 'dt-family-groups' ),
            'type'          => 'connection',
            'post_type'     => 'contacts',
            'p2p_direction' => 'any',
            'p2p_key'       => 'contacts_to_family_spouse',
            'tile'          => 'family',
            'icon'          => get_template_directory_uri() . '/dt-assets/images/connection-people.svg?v=2',
            'create-icon'   => get_template_directory_uri() . '/dt-assets/images/add-contact.svg?v=2',
        ];

        // p2p_key: contacts_to_family_children  FROM=parent  TO=child
        // "family_parents" shows who this contact's parents are:
        //   this contact is on the TO (child) side → direction 'from' returns the FROM (parent) records
        $fields['family_parents'] = [
            'name'          => __( 'Parents', 'dt-family-groups' ),
            'description'   => __( 'Parent(s) of this contact', 'dt-family-groups' ),
            'type'          => 'connection',
            'post_type'     => 'contacts',
            'p2p_direction' => 'from',
            'p2p_key'       => 'contacts_to_family_children',
            'tile'          => 'family',
            'icon'          => get_template_directory_uri() . '/dt-assets/images/connection-people.svg?v=2',
            'create-icon'   => get_template_directory_uri() . '/dt-assets/images/add-contact.svg?v=2',
        ];

        $fields['family_children'] = [
            'name'          => __( 'Children', 'dt-family-groups' ),
            'description'   => __( 'Children of this contact', 'dt-family-groups' ),
            'type'          => 'connection',
            'post_type'     => 'contacts',
            'p2p_direction' => 'to',
            'p2p_key'       => 'contacts_to_family_children',
            'tile'          => 'family',
            'icon'          => get_template_directory_uri() . '/dt-assets/images/connection-people.svg?v=2',
            'create-icon'   => get_template_directory_uri() . '/dt-assets/images/add-contact.svg?v=2',
        ];

        return $fields;
    }
}
DT_Family_Groups_Contact_Tile::instance();
