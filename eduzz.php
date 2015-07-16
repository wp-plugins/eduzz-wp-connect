<?php

/*
Plugin Name: Eduzz WP Connect
Description: Plugin de integração de login com a Eduzz.com
Author: Eduzz
Author URI: http://www.eduzz.com/
Version: 1.0.0
*/

class WP_Eduzz_Plugin {
	private $EduzzURL = 'http://eduzz.com/';
	private $PluginFolder = NULL;
	private $PluginDIR = NULL;
	private $PluginURL = NULL;
	public $version = '1.0.0';
	
	public function __construct() {
		$this->_define_constants();
		$this->_init_add();
	}
	
	// Init Adds on WP
	private function _init_add() {
		
		// Check this plugin is installed
		$this->checkThisPluginInstalled();
		
		// Add Actions
		add_action('wp_authenticate', array($this, 'eduzz_check_user_authentication'));
		add_action('ws_plugin__optimizemember_pro_login_widget_before_display', array($this,'eduzz_check_user_authentication'));
		
		// Add Hooks
		add_filter("plugin_action_links_" . plugin_basename(__FILE__), array($this, 'eduzz_action_links') );
		add_action('admin_menu', array($this, 'eduzz_admin_menu'));
	}
	
	// Declare Eduzz Contants
	private function _define_constants() {
		$this->PluginFolder = basename(dirname(__FILE__));
		$this->PluginDIR = plugin_dir_path(__FILE__);
		$this->PluginURL = plugin_dir_url($this->PluginFolder).$this->PluginFolder.'/';
	}
	
	// Check this plugin is installed
	private function checkThisPluginInstalled() {
		if(isset($_GET['eduzz_wp_connect_plugin_is_installed']) && $_GET['eduzz_wp_connect_plugin_is_installed']=='installed') {
			echo 'YES';
			exit();
		}
	}
	
	// Check Authentication from Eduzz
	public function eduzz_check_user_authentication() {
		// Check is a Redirect URL Login
		$this->checkRedirectLogin();
		
		// Verificar se foi passado algum Hash da Eduzz
		if(!isset($_GET['eduzz_client'])
		|| !isset($_GET['content'])
		|| !isset($_GET['hash']))
			return NULL;
		
		// Capturar o Email do Cliente
		$email = $this->getClientEmail();
		
		// Verificar se usuario Existe
		$user_id = email_exists($email);
		
		// Verificar se pode logar
		if($user_id) {
			// Setar Cookie do Navegador
			wp_set_auth_cookie($user_id);
			
			// Setar o usuario Atual
			if(function_exists('wp_set_current_user'))
				wp_set_current_user($user_id);
			
			// Pegar os dados do usuario
			$user = get_user_by('id', $user_id);
			
			// Chamar ações do OptmizePress
			do_action( 'wp_login', $user->user_login, $user );
			
			// Redirecionar para a HOME
			$getRedirURL = (isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : get_home_url());
			header("location: " . apply_filters('login_redirect', $getRedirURL, $getRedirURL, $user));
			exit();
		} else {
			header('location: ' . array_shift(explode('?', $_SERVER['REQUEST_URI'])));
			exit();
		}
	}
	
	// Check is a Redirect URL Login
	private function checkRedirectLogin() {
		// Verificar se esta sendo um redirect de outra pagina
		if(!isset($_GET['redirect_to'])) return NULL;
		
		// Verificar se não tem nenhum paramento de login da Eduzz
		if(!isset($_GET['eduzz_client']) || !isset($_GET['content']) || !isset($_GET['hash'])) {
			$redirectArgs = explode('?', $_GET['redirect_to']);
			parse_str($redirectArgs[count($redirectArgs)-1], $AUX_GET);
			
			// Verificar se tem parametros de Login da Eduzz na URL de Redirect
			if(isset($AUX_GET['eduzz_client']) && isset($AUX_GET['content']) && isset($AUX_GET['hash'])) {
				$NewURL = explode('?', $_SERVER['REQUEST_URI']);
				header('location: ' . $NewURL[0] . '?' . http_build_query($AUX_GET));
			}
		}
	}
	
	// Capture Client Email
	private function getClientEmail() {
		$email = wp_remote_retrieve_body(wp_remote_get($this->EduzzURL . 'myeduzz/login/check/' . $_GET['content'] . '/' . $_GET['eduzz_client'] . '/' . $_GET['hash']));
		if($email == 'ERROR' || $email == '') {
			header('location: ' . array_shift(explode('?', $_SERVER['REQUEST_URI'])));
			exit();
		}
		
		return $email;
	}
	
	// Add Action Link to Plugin Page
	public function eduzz_action_links($links) {
		array_unshift($links, '<a href="'.add_query_arg(array('page' => 'wp-eduzz'), admin_url('admin.php')).'">Informações</a>');
		return $links;
	}
	
	// Add Eduzz on Admin Menu
	public function eduzz_admin_menu() {
		add_menu_page('Eduzz', 'Eduzz', 'manage_options', 'wp-eduzz', array($this, 'eduzz_page'), $this->PluginURL . 'images/eduzz-icon-20x20.png');
	}
	
	// Eduzz Page
	public function eduzz_page() {
		include(dirname(__FILE__) . '/view/admin.php');
	}
}

/**
 * Initialize
 */
function wp_eduzz_plugin_init() {
	global $Eduzz_Plugin;
	$Eduzz_Plugin = new WP_Eduzz_Plugin();
}
add_action('init', 'wp_eduzz_plugin_init');