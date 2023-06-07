<?php
use function Env\env;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use enshrined\svgSanitize\Sanitizer;
use enshrined\svgSanitize\data\AllowedTags;
use enshrined\svgSanitize\data\AllowedAttributes;

/**
 * Settings class file.
 *
 * @package WordPress Plugin Template/Settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function join_paths() {
    $paths = array();

    foreach (func_get_args() as $arg) {
        if ($arg !== '') { $paths[] = $arg; }
    }

    return preg_replace('#/+#','/',join('/', $paths));
}

define('CYWEBP_WP_CONTENT_DIR', realpath(implode('/', array_slice(explode('/', wp_upload_dir()['basedir']), 0, -1))));

class SvgTags extends AllowedTags {
	public static function getTags() {
		return apply_filters('cldy_allowed_svg_tags', parent::getTags());
	}
}

class SvgAttributes extends AllowedAttributes {
	public static function getAttributes() {
		return apply_filters('cldy_allowed_svg_attributes', parent::getAttributes());
	}
}


/**
 * Settings class.
 */
class Cloudyne_Extras_Media {
    private $settings;
    private $sanitizer;

    public function __construct($settings = null) {
        $this->settings = $settings;
        
        if ($this->settings === null) {
            $this->settings = Cloudyne_Extras_Settings::instance(Cloudyne_Extras::instance());
        }

        if (get_option($this->settings->base . 'enable_svg')) {
            $this->sanitizer = new Sanitizer();
            $this->addSvgHooks();
        }
        if (get_option($this->settings->base . 'enable_webp')) {
            $this->addWebpHooks();
        }
    }

    public function addWebpHooks() {
        // Ajax: Convert old images
        add_action('wp_ajax_cywebp_convert_media_library', array($this, 'convertMediaLibrary'));

        // Ajax: List Subdirectories
        add_action('wp_ajax_cywebp_list_directories', array($this, 'listDirectories'));

        // Ajax: Recursively list subdirectories
        add_action('wp_ajax_cywebp_list_subdirectories_recursive', array($this, 'listSubdirectoriesRecursive'));

        // Enqueue scripts for admin
        add_action('admin_enqueue_scripts', array($this, 'enqueueWebpScripts'));

        // Delete webp file when original is deleted
        add_filter('wp_delete_file', array($this, 'placeholderFunction'));

        // When metadata is updated, regenerate webp image
        add_filter('wp_update_attachment_metadata', array($this, 'placeholderFunction'), 98, 2);

        // When image is created, generate webp image
        add_action('fly_image_created', array($this, 'placeholderFunction'), 10, 2);

        // When image is created, generate webp image
        add_action('bis_image_created', array($this, 'placeholderFunction'), 10, 2);
    }

    public function enqueueWebpScripts( $hook ) {
        $tab = $_GET['tab'] ?? '';
        if ($hook === 'toplevel_page_cloudyne_extras_settings' && $tab === 'media') {
            wp_enqueue_style( 'jstree-styles-a', plugin_dir_url( __FILE__ ) . '../assets/css/jstree.min.css', array(), '3.2.1');
            wp_enqueue_script( 'jstree', plugin_dir_url( __FILE__ ) . '../assets/js/jstree.min.js', array('jquery'), '3.2.1' );
            wp_add_inline_script( 
                'jstree', 
                'var transparency_status_message = "' . __( 'Please wait, converting your images is in progress...', 'cloudyne-extras' ) . '",'.
                'error_message = "' . __( 'Error {{ERROR}}, trying to continue with missing images... ', 'cloudyne-extras' ) . '"'
            );
            wp_enqueue_script( 'webp_converter', plugin_dir_url( __FILE__ ) . '../assets/js/webp_convert.js', array('jstree'), 1 );
        }
    }

    private function checkAuthenticatedAdminAjax() {
        if ( !defined('DOING_AJAX') || !DOING_AJAX ) {
            return wp_send_json('Not Ajax');
        }
        if (!current_user_can('administrator')) {
            return wp_send_json('Not Administrator');
        }
        if (!check_ajax_referer('cywebp-convert', 'security')) {
            return wp_send_json("Nonce Check failed");
        }
        return null;
    }
    
    private function is_wp_cli() {
        return defined('WP_CLI') && WP_CLI;
    }

    private function format_path($mPath) {
        $path = str_replace(':\\\\', ':/', $mPath);
        $path = str_replace('\\\\', '/', $path);
        $path = preg_replace('#^' . CYWEBP_WP_CONTENT_DIR . '#', '', $path);
        $path = realpath(join_paths(CYWEBP_WP_CONTENT_DIR, $path));

        return $path;
    }

    public function convertMediaLibrary($cli_missing_only = 1, $cli_folder = null)
    {
        $missing_only = intval($_POST['only_missing'] ?? 1);
        $convertExtensions = array_map(function ($s) {return trim(trim(strtolower($s), ' .'));}, $this->convertExtensions());

        if ($this->is_wp_cli()) {
            $path = $cli_folder;
            $missing_only = $cli_missing_only;
        }
        else {
            $check = $this->checkAuthenticatedAdminAjax();
            if ($check !== null) {
                if (!defined('WP_CLI') && $cli_folder == null) {
                    return $check;
                }
                $missing_only = $cli_missing_only;
            }
            $path = $this->format_path($_POST['folder'] ?? $cli_folder ?? false);
        }

        if (is_dir($path)) {
            $secure_path_len = strlen(CYWEBP_WP_CONTENT_DIR);
            if ( substr( $path, 0, $secure_path_len) === CYWEBP_WP_CONTENT_DIR ) {
                $files = scandir($path);
                $converted = 0;
                foreach ($files as $file) {
                    $fullPath = join_paths($path, $file);

                    if ( !$missing_only || !file_exists( $fullPath . '.webp')) {
                        if ($this->convertImage($fullPath, $convertExtensions)) {
                            $converted++;
                        }
                    }
                }
                print("{$converted} converted\n");
            }
        }
    }

    private function convertExtensions()
    {
        $ext = get_option($this->settings->base . 'webp_extensions', array('jpg', 'jpeg', 'png', 'gif'));
        if (is_array($ext)) {
            return $ext;
        }
        if (is_string($ext)) {
            if (strpos($ext, ',') !== false) {
                return explode(',', $ext);
            }
            if (strpos($ext, ';') !== false) {
                return explode(';', $ext);
            }
            if (strpos($ext, PHP_EOL) !== false) {
                return explode(PHP_EOL, $ext);
            }
            if (strpos($ext, '\r\n') !== false) {
                return explode('\r\n', $ext);
            }
            if (strpos($ext, '\r') !== false) {
                return explode('\r', $ext);
            }
            if (strpos($ext, '\n') !== false) {
                return explode('\n', $ext);
            }
        }
        return array('jpg', 'jpeg', 'png', 'gif');
    }

    public function convertImageGD( $path, $quality ){
		ini_set( 'memory_limit', '1G' );
		set_time_limit( 120 );

		$output = $path . '.webp';
		$image_extension = pathinfo( $path, PATHINFO_EXTENSION );
		$methods = array(
			'jpg' => 'imagecreatefromjpeg',
			'jpeg' => 'imagecreatefromjpeg',
			'png' => 'imagecreatefrompng',
			'gif' => 'imagecreatefromgif'
		);

		try{
			$image = @$methods[ $image_extension ]( $path );
			imageistruecolor( $image );
			imagepalettetotruecolor( $image );
			imagewebp( $image, $output, $quality );
		}catch( \Throwable $e ){
			error_log( print_r( $e, 1 ) );
			return false;
		}

		return array(
			'path' => $output,
			'size' => array(
				'before' => filesize( $path ),
				'after' => filesize( $output )
			)
		);
	}

    public function ConvertImageImagick( $path, $quality ){
		if ( !class_exists( 'Imagick' ) ) {
            return $this->ConvertImageGD( $path, $quality );
        }
        
        ini_set( 'memory_limit', '1G' );
		set_time_limit( 120 );

		$output = $path . '.webp';

		try{
			$image = new Imagick( $path );
			$image->setImageFormat('WEBP');
			$image->stripImage();
			$image->setImageCompressionQuality( $quality );
			$blob = $image->getImageBlob();
			$success = file_put_contents( $output, $blob );
		}catch( \Throwable $e ){
			error_log( print_r( $e, 1 ) );
			return false;
		}

		return array(
			'path' => $output,
			'size' => array(
				'before' => filesize( $path ),
				'after' => filesize( $output )
			)
		);
	}

    private function convertImage($image, $convertExtensions)
    {
        $converter = function($image, $quality) { return $this->convertImageGD($image, $quality); };
        $quality = intval(get_option($this->settings->base . 'webp_quality', 80));
        if (get_option($this->settings->base . 'convert_with_imagick', 0)) {
            $converter = function($image, $quality) { return $this->convertImageImagick($image, $quality); };
        }

        if (is_file($image)) {
            $extension = pathinfo($image, PATHINFO_EXTENSION);
            
            if ( in_array(strtolower($extension), $convertExtensions) ) {
                $response = $converter($image, $quality);
                if ($response) {
                    if ($response['size']['after'] <= 0 || $response['size']['after'] >= $response['size']['before']) {
                        unlink($response['path']);
                        return false;
                    } else {
                        if (get_option($this->settings->base . 'webp_delete_originals', false)) {
                            unlink($image);
                        }
                        return true;
                    }
                }
                return true;
            }
        }
        return false;
    }

    public function recursiveDirectoryList($folders, $base = CYWEBP_WP_CONTENT_DIR)
    {
        $list = array();
        if (is_array($folders)) {
            foreach ($folders as $folder) {
                if (!in_array($folder, ['.', '..'])) {
                    $folder = sanitize_text_field($folder);
                    $folder = realpath(path_join($base, $folder));
                    if (is_dir($folder)) {
                        $list[] = $folder;
                        $subfolders = scandir($folder);
                        $subfolders = $this->recursiveDirectoryList($subfolders, $folder);
                        $list = array_merge($list, $subfolders);
                    }
                }
            }
        }
        else {
            if (!in_array($folders, ['.', '..'])) {
                $folders = sanitize_text_field($folders);
                $folders = realpath(path_join($base, $folders));

                if (is_dir($folders)) {
                    $list[] = $folders;
                    $subfolders = scandir($folders);
                    $subfolders = $this->recursiveDirectoryList($subfolders, $folders);
                    $list = array_merge($list, $subfolders);
                }
            }
        }

        return $list;
    }

    public function listSubdirectoriesRecursive()
    {
        $check = $this->checkAuthenticatedAdminAjax();
        if ($check !== null) {
            return $check;
        }
        wp_send_json(array_unique($this->recursiveDirectoryList($_REQUEST['folders'])));
    }

    public function listDirectories()
    {
        $check = $this->checkAuthenticatedAdminAjax();
        if ($check !== null) {
            return $check;
        }

        if ($_REQUEST['folder'] == '#') {
            $dir = CYWEBP_WP_CONTENT_DIR;
        } else {
            $dir = join_paths(CYWEBP_WP_CONTENT_DIR, $_REQUEST['folder']);
        }

        $response = array_filter(array_map(function($directory) use ($dir) {
            if (!in_array($directory, [".", ".."]) && is_dir(join_paths($dir, $directory))) {
                $dirname = explode(CYWEBP_WP_CONTENT_DIR, join_paths($dir, $directory));
                if (isset($dirname[1])) {
                    return array(
                        'id' => trim(esc_attr($dirname[1]), '/'),
                        'parent' => esc_attr($_REQUEST['folder']),
                        'text' => esc_html($directory),
                        'children' => true
                    );
                }
            }
        }, scandir($dir)), function($item) {
            return $item !== null;
        });
        sort($response);
        return wp_send_json($response);
    }

    public function placeholderFunction(...$args) {
        file_put_contents('/srv/bedrock/web/app/plugins/cloudyne-extras/debug.log', print_r($args, true), FILE_APPEND);
    }

    public function addSvgHooks() {
        add_filter('wp_handle_upload_prefilter', array($this, 'sanitizeSvgFilter'));
        
        if (!function_exists('cldy_add_svg_support')) {
            add_filter('upload_mimes', array($this, 'addSvgSupport'));
        }

        if (!function_exists('cldy_add_upload_check')) {
            add_filter('wp_check_filetype_and_ext', array($this, 'checkSvgUpload'), 10, 4);
        }

        if (!function_exists('cldy_display_svg_admin')) {
            add_action('wp_AJAX_svg_get_attachment_url', array($this, 'displaySvgAdmin'));
        }

        if (!function_exists('cldy_display_svg_media')) {
            add_filter('wp_prepare_attachment_for_js', array($this, 'displaySvgMedia'), 10, 3);
        }

        if (!function_exists('cldy_style_svg')) {
            add_action('admin_head', array($this, 'styleSvg'));
        }
    }

    public function styleSvg() {
        echo "<style>
                /* Media LIB */
                table.media .column-title .media-icon img[src*='.svg']{
                    width: 100%;
                    height: auto;
                }
    
                /* Gutenberg Support */
                .components-responsive-wrapper__content[src*='.svg'] {
                    position: relative;
                }
    
            </style>";
    }

    public function displaySvgAdmin() {
        $url = '';
        $attachmentID = $_REQUEST['attachmentID'] ?? '';

        if ($attachmentID) {
            $url = wp_get_attachment_url($attachmentID);
        }
        echo $url;
        die();
    }

    public function displaySvgMedia($response, $attachment, $meta) {
        if($response['type'] === 'image' && $response['subtype'] === 'svg+xml' && class_exists('SimpleXMLElement')){
            try {
                $path = get_attached_file($attachment->ID);

                if(@file_exists($path)){
                    $svg                = new SimpleXMLElement(@file_get_contents($path));
                    $src                = $response['url'];
                    $width              = (int) $svg['width'];
                    $height             = (int) $svg['height'];
                    $response['image']  = compact( 'src', 'width', 'height' );
                    $response['thumb']  = compact( 'src', 'width', 'height' );

                    $response['sizes']['full'] = array(
                        'height'        => $height,
                        'width'         => $width,
                        'url'           => $src,
                        'orientation'   => $height > $width ? 'portrait' : 'landscape',
                    );
                }
            }
            catch(Exception $e){}
        }

        return $response;
    }

    public function checkSvgUpload($info, $file, $filename, $mimes) {
        file_put_contents('/srv/bedrock/web/app/plugins/cloudyne-extras/debug.log', print_r(["CheckSvgUpload", $info, $file, $filename, $mimes], true), FILE_APPEND);
        if (!$info['type']) {
            file_put_contents('/srv/bedrock/web/app/plugins/cloudyne-extras/debug.log', print_r(["Not Typed"], true), FILE_APPEND);

            $filetype = wp_check_filetype( $filename, $mimes );
            $extension = $filetype['ext'];
            $type = $filetype['type'];
            
            if ($type && strpos($type, 'image/') === 0 && $extension !== 'svg') {
                $extension = $type = False;
            }
            $checked = compact('extension', 'type', 'filename');
        }
        if ($info['type'] === 'image/svg+xml') {
            $checked = array(
                'ext' => 'svg',
                'type' => 'image/svg+xml',
                'proper_filename' => $filename,
            );
        }

        return $checked;
    }



    public function addSvgSupport($mime_types) {
        $mime_types['svg'] = 'image/svg+xml';
        return $mime_types;
    }

    public function sanitizeSvgFilter( $upload ) {
        if ( $upload['type'] === 'image/svg+xml' ) {
            if ( ! $this->sanitizeSvgFile( $upload['tmp_name'] ) ) {
                $upload['error'] = __( "Sorry, please check your file", 'cloudyne-extras' );
            }
        }
    
        return $upload;
    }

    public function sanitizeSvgFile($file) {
        $this->sanitizer->setAllowedTags(new SvgTags());
        $this->sanitizer->setAllowedAttrs(new SvgAttributes());

        $dangerous = file_get_contents($file);

        if ($dangerous === false ) {
            return false;
        }

        $safe = $this->sanitizer->sanitize($dangerous);
        if ($safe === false) {
            return false;
        }

        file_put_contents($file, $safe);

        return true;
    }
}
