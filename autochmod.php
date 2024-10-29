<?php
/*
  Plugin Name: AutoCHMOD
  Plugin URI: http://e2net.it?autochmod
  Description: Protect folders and files from unhautorized changes managing filesystem permissions.
  Author: Franco Traversaro
  Version: 0.5.2
  Author URI: mailto:franco.traversaro@e2net.it
  Text Domain: autochmod
  Domain Path: /languages/
 */

class AutoCHMOD {

    const RIPRISTINO_AUTOMATICO = 600;

    private $keep_writable = array();
    private $perms = array(
        '+' => array(
            'd' => array( 'u' => 7, 'g' => 7, 'a' => 7 ),
            'f' => array( 'u' => 6, 'g' => 6, 'a' => 6 ) ),
        '-' => array(
            'd' => array( 'u' => 5, 'g' => 5, 'a' => 5 ),
            'f' => array( 'u' => 4, 'g' => 4, 'a' => 4 ) ) );

    private function __construct() {
        $this->keep_writable = $this->get_option( 'autochmod_keep_writable', array() );
        $this->perms = $this->get_option( 'autochmod_perms', $this->perms );

        register_activation_hook( __FILE__, array( $this, 'activation_hook' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivation_hook' ) );

        add_action( is_multisite() ? 'network_admin_menu' : 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
        add_action( 'update_option_auto_updater.lock', array( $this, 'update_option_auto_updater_lock' ) );
        add_action( 'delete_option_auto_updater.lock', array( $this, 'delete_option_auto_updater_lock' ) );
        if ( is_admin() )
            add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 10000 );
        add_action( 'rimuovi_permessi_scrittura', array( $this, 'rimuovi_permessi_scrittura' ) );

        add_filter( 'plugin_action_links_autochmod/autochmod.php', array( $this, 'plugin_action_links' ) );
        add_filter( 'network_admin_plugin_action_links_autochmod/autochmod.php', array( $this, 'plugin_action_links' ) );

        if ( $this->get_option( 'autochmod_protection_active' ) ) {
            add_action( 'in_admin_footer', array( $this, 'in_admin_footer' ) );
        } else {
            if ( ($this->get_option( 'autochmod_safe_again_at' ) - time()) > 0 ) {
                add_action( 'admin_head', array( $this, 'admin_head_countdown_scripts' ) );
            }
        }
    }

    private function get_option( $option ) {
        return is_multisite() ? get_site_option( $option ) : get_option( $option );
    }

    private function update_option( $option, $value ) {
        return is_multisite() ? update_site_option( $option, $value ) : update_option( $option, $value );
    }

    private function delete_option( $option ) {
        return is_multisite() ? delete_site_option( $option ) : delete_option( $option );
    }

    public function update_option_auto_updater_lock( $old_value, $value ) {
        $this->metti_permessi( ABSPATH );
        $when = time() + HOUR_IN_SECONDS;
        wp_schedule_single_event( $when, 'rimuovi_permessi_scrittura' );
        $this->update_option( 'autochmod_safe_again_at', $when );
        $this->update_option( 'autochmod_protection_active', false );
    }

    public function delete_option_auto_updater_lock( $option ) {
        $this->togli_permessi( ABSPATH );
        wp_unschedule_event( get_option( 'autochmod_safe_again_at' ), 'rimuovi_permessi_scrittura' );
        $this->delete_option( 'autochmod_safe_again_at' );
        $this->update_option( 'autochmod_protection_active', true );
    }

    public function admin_head_countdown_scripts() {
        ?>
        <script type="text/javascript">
            jQuery(function($) {
                var minuti = parseInt($('#autochmod_min').text());
                var secondi = parseInt($('#autochmod_sec').text());
                if (minuti || secondi) {
                    window.setInterval(function() {
                        secondi--;
                        if (secondi < 0) {
                            minuti--;
                            secondi = 59;
                        }
                        if (minuti >= 0) {
                            $('#autochmod_min').text(minuti);
                            $('#autochmod_sec').text(secondi > 9 ? secondi : '0' + secondi);
                        } else {
                            $('#autochmod_min').text('0');
                            $('#autochmod_sec').text('00');
                        }
                    }, 1000);
                }
            });
        </script>
        <?php
    }

    public function plugin_action_links( $actions ) {
        $link = add_query_arg( 'page', 'autochmod', is_multisite() ? network_admin_url( 'settings.php' ) : admin_url( 'tools.php' )  );
        array_unshift( $actions, '<a href="' . esc_url( $link ) . '" title="' . esc_attr__( "Manage settings", 'autochmod' ) . '">' . __( "Settings", 'autochmod' ) . '</a>' );
        return $actions;
    }

    public function deactivation_hook() {
        $this->delete_option( 'autochmod_keep_writable' );
        $this->delete_option( 'autochmod_perms' );
        $this->delete_option( 'autochmod_safe_again_at' );
        $this->delete_option( 'autochmod_protection_active' );
        $this->delete_option( 'autochmod_config_verified' );
    }

    public function activation_hook() {
        $kw = $this->get_option( 'autochmod_keep_writable' );
        if ( !is_array( $kw ) ) {
            $dir = wp_upload_dir();
            $kw = array( $dir[ 'basedir' ] );
            if ( $blogs_dir = realpath( WP_CONTENT_DIR . '/blogs.dir' ) and !$this->writable( $blogs_dir ) )
                $kw[] = $blogs_dir;
            $this->update_option( 'autochmod_keep_writable', $kw );
        }
        $this->keep_writable = $kw;
        $this->update_option( 'autochmod_perms', $this->perms );
    }

    public function plugins_loaded() {
        load_plugin_textdomain( 'autochmod', false, basename( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'languages' );
    }

    public function admin_bar_menu( WP_Admin_Bar $wp_admin_bar ) {
        if ( $this->get_option( 'autochmod_config_verified' ) ) {
            if ( !$this->get_option( 'autochmod_protection_active' ) ) {
                $sec = $this->get_option( 'autochmod_safe_again_at' ) - time();
                $style = "background:no-repeat center left url('" . WP_PLUGIN_URL . "/autochmod/graphic/opened.png');";
                $act = 'togli';
                if ( $sec > 0 ) {
                    $tit = sprintf( __( 'Modifications allowed for %s:%s', 'autochmod' ), '<span id="autochmod_min">' . floor( $sec / 60 ) . '</span>', '<span id="autochmod_sec">' . sprintf( '%02d', $sec % 60 ) . '</span>' );
                } else {
                    $tit = __( 'Folders NOT protected', 'autochmod' );
                }
            } else {
                $style = "background:no-repeat center left url('" . WP_PLUGIN_URL . "/autochmod/graphic/closed.png');";
                $tit = __( 'Folders protected', 'autochmod' );
                $act = 'metti';
            }
            $wp_admin_bar->add_node( array( 'id' => 'autochmod',
                'parent' => 'top-secondary',
                'href' => add_query_arg( array( 'chmod' => $act, 'chmodmsg' => false ) ),
                'title' => '<span id="autochmodlockicon" style="padding-left:18px;' . $style . '">' . $tit . '</span>' ) );
        } else {
            $wp_admin_bar->add_node( array( 'id' => 'autochmod',
                'parent' => 'top-secondary',
                'href' => add_query_arg( 'page', 'autochmod', is_multisite() ? network_admin_url( 'settings.php' ) : admin_url( 'tools.php' )  ),
                'title' => '<span id="autochmodlockicon" style="padding-left:18px;background:no-repeat center left url(\'' . WP_PLUGIN_URL . '/autochmod/graphic/opened.png\');">' . __( 'Check permission config', 'autochmod' ) . '</span>' ) );
        }
    }

    public function in_admin_footer() {
        $cs = get_current_screen();
        if ( in_array( $cs->base, array(
                    'plugin-editor',
                    'plugin-install',
                    'plugin-editor-network',
                    'plugin-install-network',
                    'theme-editor',
                    'theme-install',
                    'theme-editor-network',
                    'theme-install-network',
                    'update-core',
                    'update-core-network' ) ) ) {
            ?>
            <script type="text/javascript">
                jQuery(function($) {
                    $('#autochmod_avviso').insertAfter('.wrap>h2:first-child');
                });
            </script>
            <div class="error inline" id="autochmod_avviso" style="background-color:#ffe0e0;">
                <h3><?php _e( 'Beware!', 'autochmod' ); ?></h3>
                <p><?php printf( __( 'At this moment the folders are write protected. In order to make changes you must before <a href="%s">enable writings</a>.', 'autochmod' ), add_query_arg( array( 'chmod' => 'metti', 'chmodmsg' => false ) ) ); ?></p>
            </div>
            <?php
        }
    }

    public function init() {
        if ( is_admin() and isset( $_GET[ 'chmod' ] ) ) {
            $msg = null;
            switch ( $_GET[ 'chmod' ] ) {
                case 'togli':
                    $this->togli_permessi( ABSPATH );
                    wp_unschedule_event( get_option( 'autochmod_safe_again_at' ), 'rimuovi_permessi_scrittura' );
                    $this->delete_option( 'autochmod_safe_again_at' );
                    $this->update_option( 'autochmod_protection_active', true );
                    break;
                case 'metti':
                    $this->metti_permessi( ABSPATH );
                    $when = time() + AutoCHMOD::RIPRISTINO_AUTOMATICO;
                    wp_schedule_single_event( $when, 'rimuovi_permessi_scrittura' );
                    $this->update_option( 'autochmod_safe_again_at', $when );
                    $this->update_option( 'autochmod_protection_active', false );
                    break;
                case 'eterno':
                    $this->metti_permessi( ABSPATH );
                    wp_unschedule_event( get_option( 'autochmod_safe_again_at' ), 'rimuovi_permessi_scrittura' );
                    $this->update_option( 'autochmod_safe_again_at', 0 );
                    $this->update_option( 'autochmod_protection_active', false );
                    $msg = 2;
                    break;
                case 'keep';
                    $this->update_option( 'autochmod_config_verified', true );

                    $save = array();
                    if ( isset( $_POST[ 'folders' ] ) and is_array( $_POST[ 'folders' ] ) )
                        foreach ( $_POST[ 'folders' ] as $path => $act )
                            if ( absint( $act ) and $path = realpath( $path ) )
                                $save[] = $path;
                    sort( $save );
                    $this->update_option( 'autochmod_keep_writable', $save );
                    $this->keep_writable = $save;

                    if ( isset( $_POST[ 'perms' ] ) and is_array( $_POST[ 'perms' ] ) )
                        foreach ( $_POST[ 'perms' ] as $mode => $kind_data )
                            foreach ( $kind_data as $kind => $perm_data )
                                foreach ( $perm_data as $target => $permission )
                                    $this->maybe_set_perm( $mode, $kind, $target, $permission );

                    $this->update_option( 'autochmod_perms', $this->perms );
                    $msg = 1;
                    break;
                default:
                    return;
            }
            wp_safe_redirect( add_query_arg( array( 'chmod' => null, 'chmodmsg' => $msg ) ) );
            die();
        }

        load_plugin_textdomain( 'autochmod', false, 'autochmod/languages' );
    }

    private function maybe_set_perm( $mode, $kind, $target, $permission ) {
        if ( $mode != '+' and $mode != '-' )
            return false;
        if ( $kind != 'd' and $kind != 'f' )
            return false;
        if ( $target != 'u' and $target != 'g' and $target != 'a' )
            return false;
        if ( ( $permission = absint( $permission )) > 7 )
            return false;
        $this->perms[ $mode ][ $kind ][ $target ] = $permission;
    }

    private function message( $code ) {
        $code = absint( $code );
        if ( $code !== ( isset( $_GET[ 'chmodmsg' ] ) ? absint( $_GET[ 'chmodmsg' ] ) : false ) )
            return;
        switch ( $code ) {
            case 1:
                $_ = __( "Folder preferences have been saved, but permissions hasn't been applied yet. In order to apply them you must re-enable the write protection.", 'autochmod' );
                $_ .= '&nbsp;<a class="button-primary" href="' . esc_url( add_query_arg( array( 'chmod' => 'togli', 'chmodmsg' => false ) ) ) . '">' . __( "Apply and protect folders", 'autochmod' ) . '</a>';
                break;
            case 2:
                $_ = __( "The protection is now permanently disabled. Remember to reactivate it when you'll finish working!", 'autochmod' );
                break;
            default: $_ = false;
                break;
        }
        if ( $_ )
            echo '<div class="chmodmsg chmodyellow updated" id="chmodmsg' . $code . '"><p>' . $_ . '</p></div>';
    }

    public function admin_menu() {
        $tit = __( "Write permissions", 'autochmod' );
        $page = add_submenu_page( is_multisite() ? 'settings.php' : 'tools.php', $tit, $tit, 'manage_options', 'autochmod', array( $this, 'pagina_amministrazione' ) );
        add_action( 'admin_print_scripts-' . $page, array( $this, 'enqueue_scripts_optionpage' ) );
        add_action( 'load-' . $page, array( $this, 'help_tab' ) );
    }

    public function help_tab() {
        ob_start();
        ?>            
        <p><?php _e( "For safety reasons, it's good pratice to set the folders on your site as not modifiable, in order to make more difficult attacks by hackers. In this page you can remove write permissions to your site and rehabilitate them temporarily, for example, to make upgrades and installations of new plugins or themes. When you activate this plugin for the first time, the protection isn't automatically turned on. You must follow these steps:", 'autochmod' ); ?></p>
        <ol>
            <li>
                <strong><?php _e( "Ensure that the permission will work nicely with your server configuration", 'autochmod' ); ?></strong><br>
                <em><?php _e( "The default set of permission isn't strong at all, but the site will work for sure. On the other hand, suggested permissions are checked on a real call, so you can trust them.", 'autochmod' ); ?></em>
            </li>
            <li>
                <strong><?php _e( "Choose which directory must been kept writeable", 'autochmod' ); ?></strong><br>
                <em><?php _e( "Tipically only the upload directory must be chosen. If some of your plugins or themes use a cache on disk, you must chose those directory as well. If you don't plan to upload new media too often, you can disable writing on upload directory as well.", 'autochmod' ); ?></em>
            </li>
            <li>
                <strong><?php _e( "Enable folder protection", 'autochmod' ); ?></strong><br>
                <em><?php _e( "Once you enable the protection, your choosen configuration will be applied to ALL files and directory included in your Wordpress installation dir.", 'autochmod' ); ?></em>
            </li>
            <li>
                <strong><?php _e( "Disable the protection when you'll need it", 'autochmod' ); ?></strong><br>
                <em><?php _e( "There's a button on the right of the admin bar: clicking on it you can disable the protection for 10 minutes so you can update plugins, themes or whatever you want. After that amount of time the protection will be automatically restored at the first call to your site.", 'autochmod' ); ?></em>
            </li>
        </ol>
        <?php
        $help = ob_get_clean();
        $screen = get_current_screen();
        $screen->add_help_tab( array(
            'id' => 'autochmod_help',
            'title' => __( "Help", 'autochmod' ),
            'content' => $help
        ) );
    }

    public function enqueue_scripts_optionpage() {
        $plurl = WP_PLUGIN_URL;
        if ( is_ssl() )
            $plurl = str_replace( 'http://', 'https://', $plurl );
        wp_enqueue_style( 'autochmod_optionpage', $plurl . '/autochmod/graphic/configpage.css' );
        wp_register_script( 'autochmod_jstree', $plurl . '/autochmod/jstree/jquery.jstree.js', array( 'jquery' ) );
        wp_register_script( 'autochmod', $plurl . '/autochmod/scripts.js', array( 'autochmod_jstree' ) );
        wp_enqueue_script( 'autochmod' );
    }

    private function keep( $path ) {
        foreach ( $this->keep_writable as $keep )
            if ( 0 === strpos( $path, $keep ) )
                return true;
        return false;
    }

    public function rimuovi_permessi_scrittura() {
        $this->delete_option( 'autochmod_safe_again_at' );
        $this->update_option( 'autochmod_protection_active', true );
        $this->togli_permessi( ABSPATH );
    }

    private function chmod( $mode, $path ) {
        if ( !file_exists( $path ) )
            return;
        $kind = is_dir( $path ) ? 'd' : 'f';
        if ( $mode !== '+' ) {
            $mode = $this->keep( $path ) ? '+' : '-';
        }
        chmod( $path, octdec( $this->perms[ $mode ][ $kind ][ 'u' ] . $this->perms[ $mode ][ $kind ][ 'g' ] . $this->perms[ $mode ][ $kind ][ 'a' ] ) );
    }

    private function togli_permessi( $base_dir ) {
        if ( !is_dir( $base_dir ) )
            return;
        $this->chmod( '-', $base_dir );
        $files = scandir( $base_dir );
        foreach ( $files as $file )
            if ( $file !== '.' and $file !== '..' ) {
                $check = realpath( $base_dir . DIRECTORY_SEPARATOR . $file );
                if ( is_dir( $check ) ) {
                    $this->togli_permessi( $check );
                } else {
                    $this->chmod( '-', $check );
                }
            }
    }

    private function metti_permessi( $base_dir ) {
        if ( !is_dir( $base_dir ) )
            return;
        $this->chmod( '+', $base_dir );
        $files = scandir( $base_dir );
        foreach ( $files as $file )
            if ( $file !== '.' and $file !== '..' ) {
                $check = realpath( $base_dir . DIRECTORY_SEPARATOR . $file );
                if ( is_dir( $check ) ) {
                    $this->metti_permessi( $check );
                } else {
                    $this->chmod( '+', $check );
                }
            }
    }

    private function tree( $path ) {
        if ( !is_dir( $path ) )
            return;
        $path = realpath( $path );
        static $must_open = false;
        if ( $must_open === false ) {
            $must_open = array();
            foreach ( $this->keep_writable as $kp ) {
                $part = '';
                $kp = explode( DIRECTORY_SEPARATOR, $kp );
                foreach ( $kp as $t )
                    if ( $part = realpath( $part . DIRECTORY_SEPARATOR . $t ) )
                        $must_open[] = $part;
            }
        }

        $classes = array();
        if ( in_array( $path, $this->keep_writable ) )
            $classes[] = 'jstree-checked"';
        if ( in_array( $path, $must_open ) )
            $classes[] = 'jstree-open"';
        echo '<li id="' . $path . '" class="' . implode( ' ', $classes ) . '">';
        echo '<a>' . basename( $path ) . '</a>';
        $files = scandir( $path );
        $printed = false;
        foreach ( $files as $file )
            if ( $file !== '.' and $file !== '..' ) {
                $check = realpath( $path . DIRECTORY_SEPARATOR . $file );
                if ( is_dir( $check ) ) {
                    if ( !$printed ) {
                        echo '<ul>';
                        $printed = true;
                    }
                    $this->tree( $check );
                }
            }
        if ( $printed )
            echo '</ul>';
        echo '</li>';
    }

    private function writable( $path = false ) {
        if ( !$path )
            $path = ABSPATH;
        return is_writable( $path );
    }

    public function pagina_amministrazione() {
        $upload_dir = wp_upload_dir();
        ?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2><?php _e( "Write permissions", 'autochmod' ); ?></h2>
            <?php $this->message( 2 ); ?>
            <?php if ( !$this->get_option( 'autochmod_config_verified' ) ) : ?>
                <div class="chmodyellow updated">
                    <h3><?php _e( "It seems you've never changed the options!", 'autochmod' ); ?></h3>
                    <p><?php _e( "Maybe you would learn something about this plugin? There's a nice help for you, if you click the button on the top right of this page.", 'autochmod' ); ?></p>
                </div>
            <?php endif; ?>
            <?php if ( !$this->get_option( 'autochmod_protection_active' ) ) : ?>
                <div class="chmodyellow updated">
                    <h3><?php _e( 'Beware!', 'autochmod' ); ?></h3>
                    <p>
                        <?php _e( "Right now the folders <strong>are not</strong> write-protected: you can update Wordpress and install or edit themes and plugins.", 'autochmod' ); ?>
                        <?php if ( wp_next_scheduled( 'rimuovi_permessi_scrittura' ) ) printf( ' ' . __( "The protection is automatically reactivated at the end of the %d minutes required.", 'autochmod' ), floor( AutoCHMOD::RIPRISTINO_AUTOMATICO / 60 ) ); ?>
                    </p>
                    <p>
                        <a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'chmod' => 'togli', 'chmodmsg' => false ) ) ); ?>"><?php _e( 'Activate now the protection', 'autochmod' ); ?></a>
                    </p>
                </div>
            <?php else: ?>
                <div class="chmodgreen updated">
                    <h3><?php _e( 'Perfect!', 'autochmod' ); ?></h3>
                    <p><?php _e( "Right now the folders <strong>are</strong> write-protected: you can modify only files in folders selected in the box to the left.", 'autochmod' ); ?></p>
                    <p>
                        <a class="button button-primary" href="<?php echo esc_url( add_query_arg( array( 'chmod' => 'metti', 'chmodmsg' => false ) ) ); ?>"><?php printf( __( 'Enable writings for %d minutes', 'autochmod' ), floor( AutoCHMOD::RIPRISTINO_AUTOMATICO / 60 ) ); ?></a>
                        <a class="button" href="<?php echo esc_url( add_query_arg( array( 'chmod' => 'eterno', 'chmodmsg' => false ) ) ); ?>"><?php _e( 'Enable writings forever', 'autochmod' ); ?></a>
                    </p>
                </div>
            <?php endif; ?>
            <h3><?php _e( "Manage options", 'autochmod' ); ?></h3>
            <form method="post" action="<?php echo esc_url( add_query_arg( array( 'chmod' => 'keep', 'chmodmsg' => false ) ) ); ?>">

                <div id="folderlist">
                    <p><?php _e( "Select the folders where you want to keep writing permissions (subfolders will behave accordingly).", 'autochmod' ); ?></p>
                    <?php $this->message( 1 ); ?>
                    <div id="riassunto"><?php echo implode( '<br>', $this->keep_writable ); ?></div>
                    <?php if ( !$this->writable( $upload_dir[ 'basedir' ] ) ): ?>
                        <div class="chmodyellow updated">
                            <p><?php _e( "The wp-content/uploads folder is currently not writeable. Upload of new images and attachments will fail.", 'autochmod' ); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ( $blogs_dir = realpath( WP_CONTENT_DIR . '/blogs.dir' ) and !$this->writable( $blogs_dir ) ): ?>
                        <div class="chmodyellow updated">
                            <p><?php _e( "The wp-content/blogs.dir folder is currently not writeable. Upload of new images and attachments in child blogs will fail.", 'autochmod' ); ?></p>
                        </div>
                    <?php endif; ?>
                    <div id="folderlistscroll">
                        <ul><?php $this->tree( ABSPATH ); ?></ul>
                    </div>
                </div>

                <div id="spostatore">
                    <p><?php _e( "Define the permission set that you want to use on files and folders:", 'autochmod' ); ?></p>
                    <?php
                    $testdir = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'test';
                    $testfile = $testdir . DIRECTORY_SEPARATOR . 'run.php';
                    $testurl = WP_PLUGIN_URL . '/autochmod/test/run.php';
                    if ( is_ssl() )
                        $testurl = str_replace( 'http://', 'https://', $testurl );
                    chmod( $testdir, 0700 );
                    chmod( $testfile, 0600 );
                    if ( PHP_VERSION === @file_get_contents( $testurl ) ) {
                        $perms = array(
                            '+' => array(
                                'd' => array( 'u' => 7, 'g' => 0, 'a' => 0 ),
                                'f' => array( 'u' => 6, 'g' => 0, 'a' => 0 ) ),
                            '-' => array(
                                'd' => array( 'u' => 5, 'g' => 0, 'a' => 0 ),
                                'f' => array( 'u' => 4, 'g' => 0, 'a' => 0 ) ) );
                    } else {
                        chmod( $testdir, 0770 );
                        chmod( $testfile, 0660 );
                        if ( PHP_VERSION === @file_get_contents( $testurl ) ) {
                            $perms = array(
                                '+' => array(
                                    'd' => array( 'u' => 7, 'g' => 7, 'a' => 0 ),
                                    'f' => array( 'u' => 6, 'g' => 6, 'a' => 0 ) ),
                                '-' => array(
                                    'd' => array( 'u' => 5, 'g' => 5, 'a' => 0 ),
                                    'f' => array( 'u' => 4, 'g' => 4, 'a' => 0 ) ) );
                        } else {
                            $perms = array(
                                '+' => array(
                                    'd' => array( 'u' => 7, 'g' => 7, 'a' => 7 ),
                                    'f' => array( 'u' => 6, 'g' => 6, 'a' => 6 ) ),
                                '-' => array(
                                    'd' => array( 'u' => 5, 'g' => 5, 'a' => 5 ),
                                    'f' => array( 'u' => 4, 'g' => 4, 'a' => 4 ) ) );
                        }
                    }
                    ?>
                    <table class="widefat permissions">
                        <thead>
                            <tr>
                                <th><?php _e( "Folder / file", 'autochmod' ); ?></th>
                                <th><?php _e( "User", 'autochmod' ); ?></th>
                                <th><?php _e( "Group", 'autochmod' ); ?></th>
                                <th><?php _e( "All", 'autochmod' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th>
                                    <strong><?php _e( "Folder, writeable", 'autochmod' ); ?></strong><br>
                                    <small><?php printf( __( "Suggested: %d %d %d", 'autochmod' ), $perms[ '+' ][ 'd' ][ 'u' ], $perms[ '+' ][ 'd' ][ 'g' ], $perms[ '+' ][ 'd' ][ 'a' ] ); ?></small>
                                </th>
                                <td><select name="perms[+][d][u]"><?php $this->select_option_perms( $this->perms[ '+' ][ 'd' ][ 'u' ] ); ?></select></td>
                                <td><select name="perms[+][d][g]"><?php $this->select_option_perms( $this->perms[ '+' ][ 'd' ][ 'g' ] ); ?></select></td>
                                <td><select name="perms[+][d][a]"><?php $this->select_option_perms( $this->perms[ '+' ][ 'd' ][ 'a' ] ); ?></select></td>
                            </tr>
                            <tr class="alternate">
                                <th>
                                    <strong><?php _e( "Folder, protected", 'autochmod' ); ?></strong><br>
                                    <small><?php printf( __( "Suggested: %d %d %d", 'autochmod' ), $perms[ '-' ][ 'd' ][ 'u' ], $perms[ '-' ][ 'd' ][ 'g' ], $perms[ '-' ][ 'd' ][ 'a' ] ); ?></small>
                                </th>
                                <td><select name="perms[-][d][u]"><?php $this->select_option_perms( $this->perms[ '-' ][ 'd' ][ 'u' ] ); ?></select></td>
                                <td><select name="perms[-][d][g]"><?php $this->select_option_perms( $this->perms[ '-' ][ 'd' ][ 'g' ] ); ?></select></td>
                                <td><select name="perms[-][d][a]"><?php $this->select_option_perms( $this->perms[ '-' ][ 'd' ][ 'a' ] ); ?></select></td>
                            </tr>
                            <tr>
                                <th>
                                    <strong><?php _e( "File, writeable", 'autochmod' ); ?></strong><br>
                                    <small><?php printf( __( "Suggested: %d %d %d", 'autochmod' ), $perms[ '+' ][ 'f' ][ 'u' ], $perms[ '+' ][ 'f' ][ 'g' ], $perms[ '+' ][ 'f' ][ 'a' ] ); ?></small>
                                </th>
                                <td><select name="perms[+][f][u]"><?php $this->select_option_perms( $this->perms[ '+' ][ 'f' ][ 'u' ] ); ?></select></td>
                                <td><select name="perms[+][f][g]"><?php $this->select_option_perms( $this->perms[ '+' ][ 'f' ][ 'g' ] ); ?></select></td>
                                <td><select name="perms[+][f][a]"><?php $this->select_option_perms( $this->perms[ '+' ][ 'f' ][ 'a' ] ); ?></select></td>
                            </tr>
                            <tr class="alternate">
                                <th>
                                    <strong><?php _e( "File, protected", 'autochmod' ); ?></strong><br>
                                    <small><?php printf( __( "Suggested: %d %d %d", 'autochmod' ), $perms[ '-' ][ 'f' ][ 'u' ], $perms[ '-' ][ 'f' ][ 'g' ], $perms[ '-' ][ 'f' ][ 'a' ] ); ?></small>
                                </th>
                                <td><select name="perms[-][f][u]"><?php $this->select_option_perms( $this->perms[ '-' ][ 'f' ][ 'u' ] ); ?></select></td>
                                <td><select name="perms[-][f][g]"><?php $this->select_option_perms( $this->perms[ '-' ][ 'f' ][ 'g' ] ); ?></select></td>
                                <td><select name="perms[-][f][a]"><?php $this->select_option_perms( $this->perms[ '-' ][ 'f' ][ 'a' ] ); ?></select></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th><?php _e( "Folder / file", 'autochmod' ); ?></th>
                                <th><?php _e( "User", 'autochmod' ); ?></th>
                                <th><?php _e( "Group", 'autochmod' ); ?></th>
                                <th><?php _e( "All", 'autochmod' ); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                    <p><input type="submit" value="<?php esc_attr_e( "Update settings", 'autochmod' ); ?>" class="button-primary"></p>
                </div>
            </form>
        </div>
        <?php
    }

    private function select_option_perms( $selected ) {
        echo '<option value="0"' . selected( 0, $selected, false ) . '>0 [---]</option>';
        echo '<option value="1"' . selected( 1, $selected, false ) . '>1 [--x]</option>';
        echo '<option value="2"' . selected( 2, $selected, false ) . '>2 [-w-]</option>';
        echo '<option value="3"' . selected( 3, $selected, false ) . '>3 [-wx]</option>';
        echo '<option value="4"' . selected( 4, $selected, false ) . '>4 [r--]</option>';
        echo '<option value="5"' . selected( 5, $selected, false ) . '>5 [r-x]</option>';
        echo '<option value="6"' . selected( 6, $selected, false ) . '>6 [rw-]</option>';
        echo '<option value="7"' . selected( 7, $selected, false ) . '>7 [rwx]</option>';
    }

    static public function run_baby_run() {
        static $instance = false;
        if ( $instance !== false )
            return;
        $instance = new self();
    }

}

AutoCHMOD::run_baby_run();
