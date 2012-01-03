<?php
/*
 * plugin initialization class
 *
 */

class PDb_Init
{
    // set to true for convenience during development.
    const UNINSTALL_ON_DEACTIVATE = false;

    // arrays for building default field set
    public static $internal_fields;
    public static $main_fields;
    public static $admin_fields;
    public static $personal_fields;
    public static $source_fields;
    public static $field_groups;


    function __construct( $mode = false )
    {
        if ( ! $mode )
            wp_die( 'class must be be called on the activation hooks', 'object not correctly instantiated' );

        error_log( __METHOD__.' called with '.$mode );

        switch( $mode )
        {
            case 'activate' :
								$this->_activate();
                break;

            case 'deactivate' :
								$this->_deactivate();
                break;

            case 'uninstall' :
								$this->_uninstall();
                break;
        }
    }

    /**
     * Set up tables, add options, etc. - All preparation that only needs to be done once
     */
    public function on_activate()
    {
        new PDb_Init( 'activate' );
    }

    /**
     * Do nothing like removing settings, etc.
     * The user could reactivate the plugin and wants everything in the state before activation.
     * Take a constant to remove everything, so you can develop & test easier.
     */
    public function on_deactivate()
    {
        $mode = 'deactivate';
        if ( self::UNINSTALL_ON_DEACTIVATE )
            $mode = 'uninstall';

        new PDb_Init( $mode );
    }

    /**
     * Remove/Delete everything - If the user wants to uninstall, then he wants the state of origin.
     */
    public function on_uninstall()
    {

        new PDb_Init( 'uninstall' );
    }

    private function _activate()
    {
      // define the arrays for loading the initial db records
      $this->_define_init_arrays();

      global $wpdb;

      // skip if the tables have already been installed
      // this will be modified when the db update capability is added
      // we will then be checking a db version number for a changed version
      if ($wpdb->get_var('show tables like "'.Participants_Db::$participants_table.'"') != Participants_Db::$participants_table) :

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // create the field values table
        $sql = 'CREATE TABLE '.Participants_Db::$fields_table.' (
          `id` INT(3) NOT NULL AUTO_INCREMENT,
          `order` INT(3) NOT NULL DEFAULT 0,
          `name` VARCHAR(30) NOT NULL,
          `title` TINYTEXT NOT NULL,
          `default` TINYTEXT NULL,
          `group` VARCHAR(30) NOT NULL,
          `help_text` TEXT NULL,
          `form_element` TINYTEXT NULL,
          `values` TEXT NULL,
          `validation` TINYTEXT NULL,
          `column` INT(3) DEFAULT 0,
          `sortable` BOOLEAN DEFAULT 0,
          `import` BOOLEAN DEFAULT 0,
          `persistent` BOOLEAN DEFAULT 0,
          `signup` BOOLEAN DEFAULT 0,
          UNIQUE KEY  ( `name` ),
          INDEX  ( `order` ),
          INDEX  ( `group` ),
          PRIMARY KEY  ( `id` )
          )
          DEFAULT CHARACTER SET utf8
          ';
        dbDelta($sql);

        // create the groups table
        $sql = 'CREATE TABLE '.Participants_Db::$groups_table.' (
          `id` INT(3) NOT NULL AUTO_INCREMENT,
          `order` INT(3) NOT NULL DEFAULT 0,
          `display` BOOLEAN DEFAULT 1,
          `title` TINYTEXT NOT NULL,
          `name` VARCHAR(30) NOT NULL,
          `description` TEXT NULL,
          UNIQUE KEY ( `name` ),
          PRIMARY KEY ( `id` )
          )
          DEFAULT CHARACTER SET utf8
          AUTO_INCREMENT = 1
          ';
        dbDelta($sql);

        // create the main data table
        $sql = 'CREATE TABLE ' . Participants_Db::$participants_table . ' (
          `id` int(6) NOT NULL AUTO_INCREMENT,
          `private_id` VARCHAR(6) NOT NULL,
          ';
        foreach( array_keys( self::$field_groups ) as $group ) {

        // these are not added to the sql in the loop
        if ( $group == 'internal' ) continue;

        foreach( self::${$group.'_fields'} as $name => &$defaults ) {

          if ( ! isset( $defaults['form_element'] ) ) $defaults['form_element'] = 'text-line';

          switch ( $defaults['form_element'] ) {

            case 'multi-select':
            case 'multi-checkbox':
            case 'text-field':
            $datatype = 'TEXT';
            break;

            case 'date':
            $datatype = 'DATE';
            break;

            case 'checkbox':
            case 'radio':
            case 'dropdown':
            case 'text-line':
            default :
            $datatype = 'TINYTEXT';

          }

          $sql .= '`'.$name.'` '.$datatype.' NULL,
          ';
        }
        }
        $sql .= '`date_recorded` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `date_updated` TIMESTAMP NOT NULL,
          PRIMARY KEY  (`id`)
          )
          DEFAULT CHARACTER SET utf8
          AUTO_INCREMENT = '.Participants_Db::$id_base_number.'
          ;';

        dbDelta($sql);

        // don't do this if the tables don't exist yet
        $table = $wpdb->get_var('show tables like "'.Participants_Db::$fields_table.'"');
        if ( $table != Participants_Db::$fields_table ) return;

        // put the default fields into the database
        $i = 0;
				unset( $defaults );
        foreach( array_keys( self::$field_groups ) as $group ) {
					
          foreach( self::${$group.'_fields'} as $name => $defaults ) {
						
            $defaults['name'] = $name;
            $defaults['group'] = $group;
            $defaults['import'] = 'main' == $group ? 1 : 0;
            $defaults['order'] = $i;
            $defaults['validation'] = isset( $defaults['validation'] ) ? $defaults['validation'] : 'no';

            if ( isset( $defaults['values'] ) && is_array( $defaults['values'] ) ) {

              $defaults['values'] = serialize( $defaults['values'] );

            }

            $wpdb->insert( Participants_Db::$fields_table, $defaults );

            $i++;

          }

        }

        // put in the default groups
        $i = 1;
        $defaults = array();
        foreach( self::$field_groups as $group=>$title ) {
          $defaults['name'] = $group;
          $defaults['title'] = $title;
          $defaults['display'] = $group == 'internal' ? 0 : 1;
          $defaults['order'] = $i;

          $wpdb->insert( Participants_Db::$groups_table, $defaults );

          $i++;

        }

        // create the default record
        $this->_set_default_record();

      endif;// if table doesn't exist
    }

    private function _deactivate()
    {
        error_log( __METHOD__.' plugin deactivated' );
    }

    private function _uninstall()
    {
        error_log( __METHOD__.' plugin uninstalled' );
        
				global $wpdb;
				
				// delete tables
				$sql = 'DROP TABLE `'.Participants_Db::$fields_table.'`, `'.Participants_Db::$participants_table.'`, `'.Participants_Db::$groups_table.'`;';
				$wpdb->query( $sql );
				
				// remove options
				delete_option( Participants_Db::$participants_db_options );
    }

    /**
     * defines arrays containg a starting set of fields, groups, etc.
     *
     * @return void
     */
    private function _define_init_arrays() {

      // define the default field groups
      self::$field_groups = array(
																	'main'      => 'Participant Info',
																	'admin'     => 'Administrative Info',
																	'personal'  => 'Personal Info',
																	'source'    => 'Source of the Record',
                                  'internal'  => 'Record Info',
																	);

      // fields for keeping track of records; not manually edited, but they can be displayed
      self::$internal_fields = array(
                            'id'             => array(
                                                    'title' => 'Record ID',
                                                    'signup' => 1,
                                                    'form_element'=>'text-line',
																										),
                            'private_id'     => array(
                                                    'title' => 'Private ID',
                                                    'signup' => 1,
                                                    'form_element' => 'text',
                                                    'default' => 'RPNE2',
																										),
                            'date_recorded'  => array(
                                                    'title' => 'Date Recorded',
                                                    'form_element'=>'date',
																										),
                            'date_updated'   => array(
                                                    'title' => 'Date Updated',
                                                    'form_element'=>'date',
																										),
                            );

      // these are some fields just to get things started
      // in the released plugin, these will be defined by the user
      //
      // the key is the id slug of the field
      // the fields in the array are:
      //  title - a display title
      //  help_text - help text to appear on the form
      //   default - a default value
      //   sortable - a listing can be sorted by this value if set
      //   column - column in the list view and order (missing or 0 for not used)
      //   persistent - is the field persistent from one entry to the next (for
      //                convenience while entering multiple records)
      //   import - is the field one to be imported or exported
      //   validation - if the field needs to be validated, use this regex or just
      //               yes for a value that must be filled in
      //   form_element - the element to use in the form--defaults to
      //                 input, Could be text-line (input), text-field (textarea),
      //                 radio, dropdown (option) or checkbox, also select-other
      //                 multi-checkbox and asmselect.(http://www.ryancramer.com/journal/entries/select_multiple/)
      //                 The mysql data type is determined by this.
      //   values array title=>value pairs for checkboxes, radio buttons, dropdowns
      //               for checkbox, first item is visible option, if value
      //               matches 'default' value then it defaults checked
      self::$main_fields = array(
                                  'first_name'   => array(
																												'title' => 'First Name',
																												'form_element' => 'text-line',
																												'validation' => 'yes',
																												'sortable' => 1,
																												'column' => 1,
																												'signup' => 1 
																												),
                                  'last_name'    => array(
																												'title' => 'Last Name',
																												'form_element' => 'text-line',
																												'validation' => 'yes',
																												'sortable' => 1,
																												'column' => 2,
																												'signup' => 1 
																												),
                                  'address'      => array(
																												'title' => 'Address',
																												'form_element' => 'text-line',
																												),
                                  'city'         => array(
																												'title' => 'City',
																												'sortable' => 1,
																												'persistent' => 1,
																												'form_element' => 'text-line',
																												'column' => 3, 
																											),
                                  'state'        => array(
																												'title' => 'State',
																												'form_element' => 'text-line',
																											),
                                  'country'      => array(
																												'title' => 'Country',
																												'form_element' => 'text-line',
																											),
                                  'zip'          => array(
																												'title' => 'Zip Code',
																												'form_element' => 'text-line', 
																											),
                                  'phone'        => array(
																												'title' => 'Phone',
																												'help_text' => 'primary contact number',
																												'form_element' => 'text-line',
																												'validation' => '#[0-9-\ ]{7,14}#',
																											),
                                  'email'        => array(
																												'title' => 'Email',
																												'form_element' => 'text-line',
																												'validation' => '#^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$#i',
																												'signup' => 1,
																											),
                                  'mailing_list' => array(
																												'title' => 'Mailing List',
																												'help_text' => 'do you want to receive our newsletter and occasional announcements?',
																												'sortable' => 1,
																												'signup' => 1,
																												'form_element' => 'checkbox',
																												'default' => 'Yes',
																												'values'  => array(
																																					'Yes',
																																					'No',
																																					),
																												),
                                  );
      self::$admin_fields = array(
                                  'donations'   => array(
																												'title' => 'Donations Made',
																												'form_element' => 'text-field', 
																											),
                                  'volunteered' => array(
																												'title' => 'Time Volunteered',
																												'form_element' => 'text-field',
																												'help_text' => 'how much time they have volunteered',
																											),
																	
                                  );
      self::$personal_fields = array(
                                  'contact_permission' => array(
																															'title' => 'Contact Permission',
																															'help_text' => 'may we contact you? If so, what is the best way?',
																															'form_element' => 'text-line',
																															),
                                  'resources'   			=> array(
																															'title' => 'Resources Offered',
																															'form_element' => 'text-field',
																															'help_text' => 'how are you willing to help?',
																															),
                                  );
      self::$source_fields = array(
                                  'where'            	=> array( 				
																															'title' => 'Location or Event of Signup',
																															'form_element' => 'text-line',
																															'persistent' => 1,
																														),
                                  'when'              => array( 
																															'title' => 'Signup Date',
																															'form_element' => 'text-line',
																															'persistent' => 1,
																														),
                                  'by'                => array(
																															'title' => 'Signup Gathered By',
																															'form_element' => 'text-line',
																															'persistent' => 1,
																														),
                                  );



    }

    // create the default record
    private function _set_default_record() {

      $default_values = array();
      $fields = array();

      // append all the field groups into one array ($fields) for the insert
      foreach( array_keys( self::$field_groups ) as $group ) {

        $fields = array_merge( $fields, self::${$group.'_fields'} );

      }

      // now build an array of default values to put into the default record
      foreach ( $fields as $name => $field ) {

        $default_values[ $name ] = isset( $field['default'] ) ? $field['default'] : '';

      }

      // insert the record; no id value for the record id forces it to use the default record id
      return Participants_Db::process_form( $default_values, 'insert', true );

    }
		
}