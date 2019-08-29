<?php
/*
Plugin Name: JBL Audit
Plugin URI: https://joellisenby.com
Author: Joel Lisenby
Version: 1.0.0
*/

class JBLAudit {
  
  public function __construct() {
    add_action('admin_init', array( $this, 'admin_init' ) );
    add_action('admin_menu', array( $this, 'admin_menu' ) );
  }

  public function admin_init() {
    if( ! function_exists('get_plugin_data') ){
      require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }
    $plugin_data = get_plugin_data( __FILE__ );

    wp_enqueue_style( 'jbl_audit_style', plugin_dir_url( __FILE__ ) .'/jbl-audit.css', array(), $plugin_data['Version'] );
  }

  public function admin_menu() {
    add_management_page( 'JBL Audit', 'JBL Audit', 'manage_options', 'jbl-audit', array( $this, 'printAudit' ) );
  }

  public function printAudit() {
    echo '<div class="jbl-audit"><h1>JBL WordPress Audit Results</h1><div class="items">'."\r\n";
    $this->printItems( $this->getAuditItems() );
    echo '</div></div>'."\r\n";
  }

  private function getAuditItems() {
    global $wp_version;

    $audit_items = array(
      'server_os' => array(
        'label' => 'Server OS',
        'content' => ( $this->isWindows() ? 'Windows' : 'Linux / BSD' ).' ('. php_uname() .')'
      ),
      'php_version' => array(
        'label' => 'PHP Version >= 7.2',
        'content' => ( phpversion() >= 7.2 ? 'Yes, '. phpversion() : '<span class="error">No, '. phpversion() .'</span>' )
      ),
      'wordpress_core' => array(
        'label' => 'WordPress Core',
        'content' => $wp_version
      ),
      'wordpress_multisite' => array(
        'label' => 'WordPress Multisite',
        'content' => is_multisite() ? 'Yes' : 'No'
      ),
      'wordpress_plugins' => array(
        'label' => 'WordPress Plugins',
        'content' => ''
      ),
      'wordpress_themes' => array(
        'label' => 'WordPress Themes',
        'content' => ''
      ),
      'sizes' => array(
        'label' => 'File and Database Sizes',
        'content' => ''
      ),
      'ssl' => array(
        'label' => 'SSL Certificate',
        'content' => !empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] != 'off' ? 'Yes' : 'No'
      ),
      'name_servers' => array(
        'label' => 'Name Servers',
        'content' => ''
      )
    );

    // WordPress Core
    $core_version = 
    $core_updates = get_core_updates();
		$core_update_needed = '';
		foreach ( $core_updates as $core => $update ) {
			if ( 'upgrade' === $update->response ) {
				$core_update_needed = $wp_version .' <strong>(Update Available: '. $update->version .')</strong>';
			} else {
				$core_update_needed = $wp_version;
			}
    }
    $audit_items['wordpress_core']['content'] = $core_update_needed;

    // WordPress Plugins
    $plugins = get_plugins();
    $plugin_updates = get_plugin_updates();
    
    if(!empty( $plugins ) ) {
      $audit_items['wordpress_plugins']['content'] .= '<ul>';
      foreach ( $plugins as $plugin_path => $plugin ) {
        $plugin_part = ( is_plugin_active( $plugin_path ) ) ? 'wp-plugins-active' : 'wp-plugins-inactive';
        $plugin_version = $plugin['Version'];
        $plugin_author  = $plugin['Author'];
        $plugin_version_string       = __( 'No version or author information is available.', 'health-check' );
        $plugin_version_string_debug = 'author: (undefined), version: (undefined)';
        if ( ! empty( $plugin_version ) && ! empty( $plugin_author ) ) {
          // translators: 1: Plugin version number. 2: Plugin author name.
          $plugin_version_string       = sprintf( __( '%1$s by %2$s', 'health-check' ), $plugin_version, $plugin_author );
          $plugin_version_string_debug = sprintf( 'version: %s, author: %s', $plugin_version, $plugin_author );
        } else {
          if ( ! empty( $plugin_author ) ) {
            // translators: %s: Plugin author name.
            $plugin_version_string       = sprintf( __( 'By %s', 'health-check' ), $plugin_author );
            $plugin_version_string_debug = sprintf( 'author: %s, version: (undefined)', $plugin_author );
          }
          if ( ! empty( $plugin_version ) ) {
            // translators: %s: Plugin version number.
            $plugin_version_string       = sprintf( __( '%s', 'health-check' ), $plugin_version );
            $plugin_version_string_debug = sprintf( 'author: (undefined), version: %s', $plugin_version );
          }
        }
        if ( array_key_exists( $plugin_path, $plugin_updates ) ) {
          // translators: %s: Latest plugin version number.
          $plugin_version_string       .= ', <strong>' . sprintf( __( 'Update Available: %s', 'health-check' ), $plugin_updates[ $plugin_path ]->update->new_version ).'</strong>';
        }

        $audit_items['wordpress_plugins']['content'] .= '<li>'. sanitize_text_field( $plugin['Name'] ) .' ('. $plugin_version_string .')</li>';
      }
      $audit_items['wordpress_plugins']['content'] .= '</ul>';
    }

    $plugins_info = $this->getPluginsInfo();
    if( !empty( $plugins_info['disallowed'] ) ) {
      $audit_items['wordpress_plugins']['content'] .= '<br /><strong>Disallowed Plugins Installed:</strong><br /><ul>';
      foreach( $plugins_info['disallowed'] as $plugin ) {
        $audit_items['wordpress_plugins']['content'] .= '<li><a target="_blank" href="https://wordpress.org/plugins/'. $plugin .'/">'. $plugin .'</a></li>';
      }
      $audit_items['wordpress_plugins']['content'] .= '</ul>';
    } else {
      $audit_items['wordpress_plugins']['content'] .= '<br /><strong>No disallowed plugins installed</strong><br /><ul>';
    }

    // WordPress Themes
    $active_theme  = wp_get_theme();
    $theme_updates = get_theme_updates();

    $audit_items['wordpress_themes']['content'] = '<ul><li><strong>Active Theme:</strong> '. $active_theme->stylesheet;

    // active theme
    if ( array_key_exists( $active_theme->stylesheet, $theme_updates ) ) {
			$theme_update_new_version = $theme_updates[ $active_theme->stylesheet ]->update['new_version'];
			$active_theme_version = $theme_update_new_version;

      $audit_items['wordpress_themes']['content'] .= ' ('. $active_theme->Version .' <strong>(Update Available: '. $active_theme_version .')</strong>';
    } else {
      $audit_items['wordpress_themes']['content'] .= ' ('. $active_theme->Version .')';
    }
    $audit_items['wordpress_themes']['content'] .= '</li>';
     
    
    // parent theme
    $parent_theme = $active_theme->parent();
    if ( $parent_theme ) {
			$parent_theme_version       = $parent_theme->Version;
      $parent_theme_version_debug = $parent_theme_version;
      
      $audit_items['wordpress_themes']['content'] .= '<li><strong>Parent Theme:</strong> '. $parent_theme->stylesheet;

			if ( array_key_exists( $parent_theme->stylesheet, $theme_updates ) ) {
				$parent_theme_update_new_version = $theme_updates[ $parent_theme->stylesheet ]->update['new_version'];
				$parent_theme_version = $parent_theme_update_new_version;

        $audit_items['wordpress_themes']['content'] .= ' ('. $parent_theme->Version .', <strong>Update Available: '. $parent_theme_version .'</strong>)';
      } else {
        $audit_items['wordpress_themes']['content'] .= ' ('. $parent_theme->Version .')';
      }

      $audit_items['wordpress_themes']['content'] .= '</li>';
    }

    // other themes
    $all_themes = wp_get_themes();
    foreach ( $all_themes as $theme_slug => $theme ) {
			// Ignore the currently active theme from the list of all themes.
			if ( $active_theme->stylesheet === $theme_slug ) {
				continue;
      }

      // Ignore the currently active parent theme from the list of all themes.
			if ( ! empty( $parent_theme ) && $parent_theme->stylesheet === $theme_slug ) {
				continue;
      }

			$theme_version = $theme->Version;
      $theme_author = $theme->Author;
      
      $audit_items['wordpress_themes']['content'] .= '<li><strong>Other Installed Theme:</strong> '. $theme_slug;
      
      if ( array_key_exists( $theme_slug, $theme_updates ) ) {
				// translators: %s: Latest theme version number.
				$theme_version_string = $theme_updates[ $theme_slug ]->update['new_version'];
        
        $audit_items['wordpress_themes']['content'] .= ' ('. $theme_version .', <strong>Update Available: '. $theme_version_string .'</strong>)';
      } else {
        $audit_items['wordpress_themes']['content'] .= ' ('. $theme->Version .')';
      }
      
      $audit_items['wordpress_themes']['content'] .= '</li>';

    }

    $audit_items['wordpress_themes']['content'] .= '</ul>';

    // Sizes
    $sizes = self::get_sizes();
    if( !empty( $sizes ) ) {
      $audit_items['sizes']['content'] = '<ul>';
      foreach( $sizes as $area => $info ) {
        $audit_items['sizes']['content'] .= '<li><strong>'. $area .':</strong> '. $info['size'] .'</li>';
      }
      $audit_items['sizes']['content'] .= '</ul>';
    }

    // Name Servers
    $name_servers = dns_get_record( $_SERVER['SERVER_NAME'], DNS_NS );
    if( !empty( $name_servers ) ) {
      foreach($name_servers as $ns ) {
        $audit_items['name_servers']['content'] .= '['. $ns['target'] .']';
      }
    }

    return $audit_items;
  }

  private function printItems( $items ) {
    foreach( $items as $item ) {
      echo '<div class="item">';
      echo '<div class="label">'. $item['label'] .'</div>';
      echo '<div class="content">'. $item['content'] .'</div>';
      echo '</div>';
    }
  }

  private function isWindows() {
    return strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN' ? true : false;
  }

  private function getPluginsInfo() {
    $disallowed_plugins_slugs = file( plugin_dir_path( __FILE__ ) .'/disallowed_plugins.txt', FILE_IGNORE_NEW_LINES );
    $installed_plugins = get_plugins();
    $installed_plugins_slugs = array();

    foreach( $installed_plugins as $dir => $plugin ) {
      $installed_plugins_slugs[] = substr( $dir, 0, strpos( $dir, '/'));
    }
    
    $installed_disallowed_plugins = array();
    if( !empty( $installed_plugins_slugs ) && is_array( $installed_plugins_slugs ) ) {
      $installed_disallowed_plugins = array_intersect( $disallowed_plugins_slugs, $installed_plugins_slugs );
    }

    return array(
      'installed' => $installed_plugins_slugs,
      'disallowed' => $installed_disallowed_plugins
    );
  }

  /* function get_sizes()
  * This function is directly pulled from the WordPress health check feature with slight modifications.
  * See: https://github.com/WordPress/health-check/blob/master/src/includes/class-health-check-debug-data.php
  */
  public static function get_sizes() {
    $size_db    = self::get_database_size();
    $upload_dir = wp_get_upload_dir();

    if ( ! defined( 'WP_START_TIMESTAMP' ) ) {
			global $timestart;
			if ( version_compare( phpversion(), '5.4.0', '>=' ) && isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ) {
				define( 'WP_START_TIMESTAMP', $_SERVER['REQUEST_TIME_FLOAT'] );
			} else {
				define( 'WP_START_TIMESTAMP', $timestart );
			}
		}
 
    /*
     * We will be using the PHP max execution time to prevent the size calculations
     * from causing a timeout. The default value is 30 seconds, and some
     * hosts do not allow you to read configuration values.
     */
    if ( function_exists( 'ini_get' ) ) {
        $max_execution_time = ini_get( 'max_execution_time' );
    }
 
    // The max_execution_time defaults to 0 when PHP runs from cli.
    // We still want to limit it below.
    if ( empty( $max_execution_time ) ) {
        $max_execution_time = 30;
    }
 
    if ( $max_execution_time > 20 ) {
        // If the max_execution_time is set to lower than 20 seconds, reduce it a bit to prevent
        // edge-case timeouts that may happen after the size loop has finished running.
        $max_execution_time -= 2;
    }
 
    // Go through the various installation directories and calculate their sizes.
    // No trailing slashes.
    $paths = array(
        'wordpress_size' => untrailingslashit( ABSPATH ),
        'themes_size'    => get_theme_root(),
        'plugins_size'   => WP_PLUGIN_DIR,
        'uploads_size'   => $upload_dir['basedir'],
    );
 
    $exclude = $paths;
    unset( $exclude['wordpress_size'] );
    $exclude = array_values( $exclude );
 
    $size_total = 0;
    $all_sizes  = array();
 
    // Loop over all the directories we want to gather the sizes for.
    foreach ( $paths as $name => $path ) {
        $dir_size = null; // Default to timeout.
        $results  = array(
            'path' => $path,
            'raw'  => 0,
        );
 
        if ( microtime( true ) - WP_START_TIMESTAMP < $max_execution_time ) {
            if ( 'wordpress_size' === $name ) {
                $dir_size = Self::recurse_dirsize( $path, $exclude, $max_execution_time );
            } else {
                $dir_size = Self::recurse_dirsize( $path, null, $max_execution_time );
            }
        }
 
        if ( false === $dir_size ) {
            // Error reading.
            $results['size']  = __( 'The size cannot be calculated. The directory is not accessible. Usually caused by invalid permissions.' );
            $results['debug'] = 'not accessible';
 
            // Stop total size calculation.
            $size_total = null;
        } elseif ( null === $dir_size ) {
            // Timeout.
            $results['size']  = __( 'The directory size calculation has timed out. Usually caused by a very large number of sub-directories and files.' );
            $results['debug'] = 'timeout while calculating size';
 
            // Stop total size calculation.
            $size_total = null;
        } else {
            if ( null !== $size_total ) {
                $size_total += $dir_size;
            }
 
            $results['raw']   = $dir_size;
            $results['size']  = size_format( $dir_size, 2 );
            $results['debug'] = $results['size'] . " ({$dir_size} bytes)";
        }
  
          $all_sizes[ $name ] = $results;
      }
  
      if ( $size_db > 0 ) {
          $database_size = size_format( $size_db, 2 );
  
          $all_sizes['database_size'] = array(
              'raw'   => $size_db,
              'size'  => $database_size,
              'debug' => $database_size . " ({$size_db} bytes)",
          );
      } else {
          $all_sizes['database_size'] = array(
              'size'  => __( 'Not available' ),
              'debug' => 'not available',
          );
      }
  
      if ( null !== $size_total && $size_db > 0 ) {
          $total_size    = $size_total + $size_db;
          $total_size_mb = size_format( $total_size, 2 );
  
          $all_sizes['total_size'] = array(
              'raw'   => $total_size,
              'size'  => $total_size_mb,
              'debug' => $total_size_mb . " ({$total_size} bytes)",
          );
      } else {
          $all_sizes['total_size'] = array(
              'size'  => __( 'Total size is not available. Some errors were encountered when determining the size of your installation.' ),
              'debug' => 'not available',
          );
      }
  
      return $all_sizes;
  }

  /* function get_database_size()
  * This function is directly pulled from the WordPress health check feature.
  * See: https://github.com/WordPress/health-check/blob/master/src/includes/class-health-check-debug-data.php
  */
  public static function get_database_size() {
		global $wpdb;
		$size = 0;
		$rows = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );

		if ( $wpdb->num_rows > 0 ) {
			foreach ( $rows as $row ) {
				$size += $row['Data_length'] + $row['Index_length'];
			}
		}

		return (int) $size;
  }
  
  /* function recurse_dirsize( ... )
  * This function is directly pulled from the WordPress health check feature with slight modifications.
  * See: https://github.com/WordPress/health-check/blob/master/src/includes/class-health-check-debug-data.php
  */
  public static function recurse_dirsize( $directory, $exclude = null, $max_execution_time = null ) {
		$size = 0;
		$directory = untrailingslashit( $directory );
		if ( ! file_exists( $directory ) || ! is_dir( $directory ) || ! is_readable( $directory ) ) {
			return false;
		}
		if (
			( is_string( $exclude ) && $directory === $exclude ) ||
			( is_array( $exclude ) && in_array( $directory, $exclude, true ) )
		) {
			return false;
		}
		if ( null === $max_execution_time ) {
			// Keep the previous behavior but attempt to prevent fatal errors from timeout if possible.
			if ( function_exists( 'ini_get' ) ) {
				$max_execution_time = ini_get( 'max_execution_time' );
			} else {
				// Disable...
				$max_execution_time = 0;
			}
			// Leave 1 second "buffer" for other operations if $max_execution_time has reasonable value.
			if ( $max_execution_time > 10 ) {
				$max_execution_time -= 1;
			}
		}
		$handle = opendir( $directory );
		if ( $handle ) {
			while ( ( $file = readdir( $handle ) ) !== false ) {
				$path = $directory . '/' . $file;
				if ( '.' != $file && '..' != $file ) {
					if ( is_file( $path ) ) {
						$size += filesize( $path );
					} elseif ( is_dir( $path ) ) {
						$handlesize = Self::recurse_dirsize( $path, $exclude, $max_execution_time );
						if ( $handlesize > 0 ) {
							$size += $handlesize;
						}
					}
					if ( $max_execution_time > 0 && microtime( true ) - WP_START_TIMESTAMP > $max_execution_time ) {
						// Time exceeded. Give up instead of risking a fatal timeout.
						$size = null;
						break;
					}
				}
			}
			closedir( $handle );
		}
		return $size;
	}

}

new JBLAudit();

?>