<?php
/*
Plugin Name: Linkwash
Plugin URI: http://www.linkwash.de/
Description: Linkwash wandelt die Links auf Ihrer Website automatisch und in Echtzeit in Affiliate-Links um. Mit nur einer Anmeldung können Sie so Tausende von Affiliate-Programmen bewerben und effizient Umsätze generieren.
Version: 0.5.2
Author: Linkwash
Author URI: http://www.linkwash.de/
*/
class LinkwashPlugin
{
    private $_linkwashId = null;
    private $_linkwashIdLast = null;
    
    const VERSION = '0.5.1';
    
    /**
     * Initially read the current linkwashId
     */
    public function __construct()
    {
        $this->_linkwashId = get_option('linkwashId');
        if ($this->_linkwashId === false) {
            //Add the option
            add_option('linkwashId', '');
        }
    }
    
    /**
     * Called on activation of plugin
     */
    public function activate()
    {
        //Add empty linkwashId Option
        add_option('linkwashId', '');
    }
    
    /**
     * Called on deactivation of plugin
     */
    public function deactivate()
    {
        //Remove the formerly added linkwashId Option
        delete_option('linkwashId');
    }
    
    /**
     * Adds the java-script to the footer of wordpress
     */
    public function linkwashFooter()
    {
        if ($this->_linkwashId != '') {
            include 'script.phtml';
        }
    }
    
    /**
     * Adds the settings link to the plugin overview
     */
    public function addSettingsLink($links, $file)
    {
        static $thisPlugin;
        if (!$thisPlugin) {
            $thisPlugin = plugin_basename(dirname(__FILE__).'/linkwash.php');
        }
        
        if ($file == $thisPlugin) {
            $settingsLink = '<a href="plugins.php?page=linkwash-key-config">'
                            .__("Einstellungen").'</a>';
            $links[] = $settingsLink;
        }
        return $links;
    }
    
    /**
     * Adds the configuration submenu item to plugin submenu
     */
    public function addLinkwashConfigMenu()
    {
        if (function_exists('add_submenu_page')) {
            add_submenu_page(
                'plugins.php',
                __('Linkwash Konfiguration'),
                __('Linkwash Konfiguration'),
                'manage_options',
                'linkwash-key-config',
                array(&$this, 'linkwashConfig')
            );
        }
    }
    
    /**
     * Get the warning message that linkwashId is not yet set
     */
    public function getWarning()
    {
        echo '<div class="updated fade"><p><strong>'
             .__('Linkwash ist fast bereit.').'</strong> '
             .sprintf(
                 __(
                     'Sie müssen <a href="%1$s">ihre Webseiten-ID eingeben</a>'
                     .', damit es funktioniert.'
                 ),
                 'plugins.php?page=linkwash-key-config'
             ).'</p></div>';
    }
    
    /**
     * If linkwashId is not set this calls getWarning() to display a warning 
     */
    public function adminWarnings()
    {
        if ($this->_linkwashId == '') {
            add_action('admin_notices', array(&$this, 'getWarning'));
        }
        return;
    }
    
    /**
     * Checks whether the entered website Id is valid
     * @param string $id The Id to be checked
     */
    private function _checkWebsiteId($id)
    {
        global $wp_version;
        
        $userAgent = 'WordPress/'.$wp_version.' | Linkwash/'.self::VERSION;
        
        $request = 'websiteId='.$id;
        
        $host = 'www.linkwash.de';
        $path = '/rest/verifywebsiteid/';
        $contentLength = strlen($request);
        
        if (function_exists('wp_remote_post')) {
            $httpArgs = array(
                'body' => $request,
                'headers' => array(
                    'Content-Type'  => 'application/x-www-form-urlencoded; ' .
                    'charset=' . get_option('blog_charset'),
                    'Host' => $host,
                    'User-Agent' => $userAgent
                ),
                'httpversion' => '1.0',
                'timeout' => 15
            );
            
            $url = 'http://'.$host.$path;
            $response = wp_remote_post($url, $httpArgs);
            if (is_wp_error($response)) {
                return false;
            }
            
            if (trim($response['body']) != '1') {
                return false;
            }
            
            return true;
            
        } else {
            $httpRequest  = "POST $path HTTP/1.0\r\n";
            $httpRequest .= "Host: $host\r\n";
            $httpRequest .= 'Content-Type: application/x-www-form-urlencoded; charset=' . get_option('blog_charset') . "\r\n";
            $httpRequest .= "Content-Length: $contentLength\r\n";
            $httpRequest .= "User-Agent: $userAgent\r\n";
            $httpRequest .= "\r\n";
            $httpRequest .= $request;
            
            $response = '';
            
            if(false != ($fs = @fsockopen( $host, 80, $errno, $errstr, 10))) {
                fwrite( $fs, $httpRequest );
                
                while (!feof($fs)) {
                    $response .= fgets( $fs, 1160 ); // One TCP-IP packet
                }
                fclose( $fs );
                $response = explode( "\r\n\r\n", $response, 2 );
            }
            if (!is_array($response) ||
                !isset($response[1]) ||
                trim($response[1]) != '1'
            ) {
                return false;
            }
            
            return true;
        }
    }
    
    /**
     * Does the plugin configuration, i.e. Setting the linkwashId
     */
    public function linkwashConfig()
    {
        $this->error = '';
        $this->info = '';
        $this->success = '';
        
        if (isset($_POST['linkwashId']) &&
                  $_POST['linkwashId'] == ''
        ) {
            $this->info = __('Ihre Webseiten-ID wurde entfernt');
            update_option('linkwashId', $_POST['linkwashId']);
        } elseif (isset($_POST['linkwashIdLast']) &&
             $_POST['linkwashId'] != $_POST['linkwashIdLast'] &&
            ((isset($_POST['linkwashId']) &&
            !is_numeric($_POST['linkwashId'])) ||
            (isset($_POST['linkwashId']) &&
            !$this->_checkWebsiteId($_POST['linkwashId'])))
        ) {
            $this->error = __('Die Webseiten-ID is nicht gültig.<br />Wenn Sie trotzdem gespeichert werden soll, dann klicken Sie bitte erneut auf "Speichern"');
        } elseif (isset($_POST['linkwashId'])) {
            
            $this->success = __('Ihre Webseiten-ID wurde gespeichert');
            update_option('linkwashId', $_POST['linkwashId']);
        }
        if (isset($_POST['linkwashId'])) {
            $this->_linkwashId = $_POST['linkwashId'];
            $this->_linkwashIdLast = $_POST['linkwashId'];
        }
        include 'config.phtml';
    }
}

$lwPlugin = new LinkwashPlugin();

add_action('wp_footer', array(&$lwPlugin, 'linkwashFooter'));
add_action('admin_menu', array(&$lwPlugin, 'addLinkwashConfigMenu'));
add_filter('plugin_action_links', array(&$lwPlugin, 'addSettingsLink'), 10, 2);

register_activation_hook(__FILE__, array(&$lwPlugin, 'activate'));
register_deactivation_hook(__FILE__, array(&$lwPlugin, 'deactivate'));

$lwPlugin->adminWarnings();
