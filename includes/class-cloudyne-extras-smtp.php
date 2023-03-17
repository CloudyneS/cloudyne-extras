<?php
use function Env\Env;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
/**
 * Settings class file.
 *
 * @package WordPress Plugin Template/Settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings class.
 */
class Cloudyne_Extras_Smtp {
    public $active = FALSE;

    public $settings = array(
        'SMTP_HOST' => '',
        'SMTP_PORT' => 25,
        'SMTP_USER' => '',
        'SMTP_PASS' => '',
        'SMTP_SECURE' => false,
        'SMTP_AUTOTLS' => false,
        'SMTP_STARTTLS' => false,
        'SMTP_AUTH' => false,
        'SMTP_FROM' => '',
        'SMTP_FROM_NAME' => '',
        'SMTP_ALLOWONLY_DOMAINS' => '',
        'SMTP_ALLOWONLY_EMAILS' => '',
        'SMTP_FORCE_FROM' => '',
        'SMTP_FORCE_FROM_NAME' => '',
        'DEBUG_LOG_LOCATION' => ''
    );


    public function __construct() {
        if (get_option('cldy_disable_email_plugin', False)) {
            return;
        }
        $this->loadSettings(
            get_option('cldy_email_from', ''),
            get_option('cldy_email_from_name', '')
        );

        if ($this->active) {
            // add_filter('pre_wp_mail', 'smtp_mailer_pre_wp_mail', 10, 2);
            add_filter('pre_wp_mail', array($this, 'sendEmail'), 10, 2);
        }
    }

    public function __debuglog($name, $item) {
        if ($this->get_env('DEBUG_LOG_LOCATION', '') !== '') {
            $data = json_encode([$name => $item], JSON_PRETTY_PRINT);
            file_put_contents($this->get_env('DEBUG_LOG_LOCATION', ''), $data, FILE_APPEND);
        }
    }

    public function getAllowedSender($requested) {
        /**
         * 1. Check if SMTP_FORCE_FROM is set. If set, return that email
         * 2. Check if SMTP_ALLOWONLY_DOMAINS is set. If set, check if the requested email is in that domain
         * 3. Check if SMTP_ALLOWONLY_EMAILS is set. If set, check if the requested email is in that list
         * Return email
         */

        if ($this->settings['SMTP_FORCE_FROM'] != '') {
            return $this->settings['SMTP_FORCE_FROM'];
        }

        if ($this->settings['SMTP_ALLOWONLY_DOMAINS'] != '') {
            $domains = explode(',', $this->settings['SMTP_ALLOWONLY_DOMAINS']);
            $split = explode('@', $requested);
            
            if (in_array($split[1], $domains)) {
                return $requested;
            }

            return $this->settings['SMTP_FROM'];
        }

        if ($this->settings['SMTP_ALLOWONLY_EMAILS'] != '') {
            $emails = explode(',', $this->settings['SMTP_ALLOWONLY_EMAILS']);
            if (in_array($requested, $emails)) {
                return $requested;
            }
            return $this->settings['SMTP_FROM'];
        }

        return $requested;
    }

    public function loadSettings($settingFromEmail = '', $settingFromName = '') {
        foreach ($this->settings as $key => $value) {
            $this->settings[$key] = $this->get_env($key, $value);
        }

        if ($this->settings['SMTP_HOST'] != '') {
            if ($this->settings['SMTP_AUTH'] == 'true' && $this->settings['SMTP_USER'] != '' && $this->settings['SMTP_PASS'] != '') {
                $this->active = TRUE;
            } else if ($this->settings['SMTP_AUTH'] == 'false') {
                $this->active = TRUE;
            }
        }

        // Force name if set
        // Otherwise check setting
        if ($settingFromName != "" && $this->settings['SMTP_FORCE_FROM_NAME'] == '') {
            $this->settings['SMTP_FROM_NAME'] = $settingFromName;
        }

        if ($this->settings['SMTP_FORCE_FROM_NAME'] != '') {
            $this->settings['SMTP_FROM_NAME'] = $this->settings['SMTP_FORCE_FROM_NAME'];
        }

        // Force email if set
        if ($settingFromEmail != "" && $this->settings['SMTP_FORCE_FROM'] == '') {
            if ($this->settings['SMTP_ALLOWONLY_DOMAINS'] != "") {
                
            }
            $this->settings['SMTP_FROM'] = $settingFromEmail;
        }


    }

    /**
	 * Get environment value
	 * @param string $key
	 * @param string $default
	 * @return string
	 */
	public function get_env($key, $default = '') {
		$envv = Env::get($key);
		if ($envv !== null) {
			return $envv;
		}
		return $default;
	}

    public function parseHeaders($headers) {
        $parsedHeaders = array();

        // Iterate through headers
        foreach ( $headers as $header ) {
            
            // If there are no colons, this is not a header.
            if ( strpos( $header, ':' ) === false ) {
                if ( false !== stripos( $header, 'boundary=' ) ) {
                    $parts    = preg_split( '/boundary=/i', trim( $header ) );
                    $parsedHeaders['boundary'] = trim( str_replace( array( "'", '"' ), '', $parts[1] ) );
                }
                continue;
            }

            // Split header into name and content and cleanup
            list( $name, $content ) = explode( ':', trim( $header ), 2 );
            $name    = trim( $name );
            $content = trim( $content );

            // Determine header type
            switch(strtolower($name)) {
                // Process "from" headers for legacy reasons
                case 'from':
                    $bracket_pos = strpos( $content, '<' );
                    if ( $bracket_pos !== false ) {
                        if ( $bracket_pos > 0 ) {
                            $parsedHeaders['from_name'] = substr( $content, 0, $bracket_pos - 1 );
                            $parsedHeaders['from_name'] = str_replace( '"', '', $parsedHeaders['from_name'] );
                            $parsedHeaders['from_name'] = trim( $parsedHeaders['from_name'] ); 
                        }
                        $parsedHeaders['from_email'] = substr( $content, $bracket_pos + 1 );
                        $parsedHeaders['from_email'] = str_replace( '>', '', $parsedHeaders['from_email'] );
                        $parsedHeaders['from_email'] = trim( $parsedHeaders['from_email'] );
                    }
                    elseif ( $content !== '' ) {
                        $parsedHeaders['from_email'] = trim( $content );
                    }
                    break;
                case 'content-type':
                    if ( strpos( $content, ';' ) !== false ) {
                        list( $type, $charset_content ) = explode( ';', $content );
                        $parsedHeaders['content_type'] = trim( $type );
                        
                        if ( false !== stripos( $charset_content, 'charset=' ) ) {
                                $parsedHeaders['charset'] = trim( str_replace( array( 'charset=', '"' ), '', $charset_content ) );
                        } 
                        
                        elseif ( false !== stripos( $charset_content, 'boundary=' ) ) {
                                $parsedHeaders['boundary'] = trim( str_replace( array( 'BOUNDARY=', 'boundary=', '"' ), '', $charset_content ) );
                                $parsedHeaders['charset']  = '';
                        }

                    } 
                    elseif ( $content !== '' ) {
                        $parsedHeaders['content_type'] = trim( $content );
                    }
                    break;
                case 'cc':
                    $parsedHeaders['cc'] = array_merge(
                        $parsedHeaders['cc'] ?? array(),
                        explode( ',', $content )
                    );
                    break;
                case 'bcc':
                    $parsedHeaders['bcc'] = array_merge(
                        $parsedHeaders['bcc'] ?? array(),
                        explode( ',', $content )
                    );
                    break;
                case 'reply-to':
                    $parsedHeaders['reply-to'] = array_merge(
                        $parsedHeaders['reply-to'] ?? array(),
                        explode( ',', $content )
                    );
                    break;
                default:
                    $parsedHeaders['additional_headers'] = array_merge(
                        $parsedHeaders['additional_headers'] ?? array(),
                        array( $name => $content )
                    );
                    break;
            }
        }
        return $parsedHeaders;
    }


    public function convertMailArgs($null, $atts)
    {
        $mailArgs = [];
        
        if (!isset($atts['to'])) {
            return $null;
        }

        $mailArgs['to'] = $atts['to'];
        if (!is_array($mailArgs['to'])) {
            $mailArgs['to'] = explode(',', $mailArgs['to']);
        }

        if (isset($atts['subject'])) {
            $mailArgs['subject'] = $atts['subject'];
        }

        if (isset($atts['message'])) {
            $mailArgs['message'] = $atts['message'];
        }

        $mailArgs['headers'] = [];
        if (isset($atts['headers'])) {
            $mailArgs['headers'] = $atts['headers'];

            if (!is_array($mailArgs['headers'])) {
                $mailArgs['headers'] = explode( "\n", str_replace( "\r\n", "\n", $mailArgs['headers'] ) );
            }

            if (!empty($mailArgs['headers'])) {
                $headers = $this->parseHeaders($mailArgs['headers']);
                $mailArgs['cc'] = $headers['cc'] ?? [];
                $mailArgs['bcc'] = $headers['bcc'] ?? [];
                $mailArgs['reply_to'] = $headers['reply_to'] ?? [];
            }
        }

        if ( isset( $atts['attachments'] ) ) {
            if ( ! is_array( $atts['attachments'] ) ) {
                    $mailArgs['attachments'] = explode( "\n", str_replace( "\r\n", "\n", $atts['attachments'] ) );
            }
        }

        return $mailArgs;
    }

    public function getFirstNonemptyValue(...$values) {
        foreach ($values as $value) {
            if ($value !== null && !empty($value) && $value !== '') {
                return $value;
            }
        }
        return null;
    }

    public function splitEmail($email) {
        $recipient_name = '';

        if ( preg_match( '/(.*)<(.+)>/', $email, $matches ) ) {
                if ( count( $matches ) == 3 ) {
                        $recipient_name = $matches[1];
                        $email        = $matches[2];
                }
        }

        return array( $email, $recipient_name );
    }

    public function sendEmail($null, $atts) {
        $this->__debuglog('Sending email with attributes: ', $atts);

        $mailArgs = $this->convertMailArgs($null, $atts);
        
        $this->__debuglog('Converted mail args: ', $mailArgs);

        if ($mailArgs == $null) {
            $this->__debuglog('Mail args are null, exiting', $mailArgs);
            return $null;
        }
        
        $mailHeaders = $this->parseHeaders($mailArgs['headers']);

        $this->__debuglog("Parsed headers", $mailHeaders);

        $mailer = new PHPMailer(true);
        $mailer->isSMTP();

        // Get settings for email
        // Primary: From env
        // Secondary: From mail headers
        // Tertiary: Defaults
        
        // Set sender name and email
        $fromName = $this->getFirstNonemptyValue(
            $this->get_env('SMTP_FROM_NAME', null),
            $mailHeaders['from_name'] ?? null,
            'WordPress'
        );

        $fromEmail = $this->getFirstNonemptyValue(
            $this->get_env('SMTP_FROM', null),
            $mailHeaders['from_email'] ?? null,
            'noreply@' . $_SERVER['SERVER_NAME']
        );
        
        $mailer->setFrom($fromEmail, $fromName, true);
        
        
 

        // Add other reply-to addresses
        foreach ($mailArgs['reply_to'] as $replyTo) {
            list($email, $name) = $this->splitEmail($replyTo);
            $mailer->addReplyTo($email, $name);
        }

        // Set To, CC and BCC
        foreach ($mailArgs['to'] as $to) {
            list($email, $name) = $this->splitEmail($to);
            $mailer->addAddress($email, $name);
        }

        foreach ($mailArgs['cc'] as $cc) {
            list($email, $name) = $this->splitEmail($cc);
            $mailer->addCC($email, $name);
        }

        foreach ($mailArgs['bcc'] as $bcc) {
            list($email, $name) = $this->splitEmail($bcc);
            $mailer->addBCC($email, $name);
        }

        $mailer->Subject = $mailArgs['subject'];
        $mailer->Body = $mailArgs['message'];

        // Set SMTP settings
        $mailer->Host = $this->get_env('SMTP_HOST', 'localhost');
        $mailer->Port = $this->get_env('SMTP_PORT', 25);
        $mailer->SMTPAuth = $this->get_env('SMTP_AUTH', false);
        $mailer->Username = $this->get_env('SMTP_USER', null);
        $mailer->Password = $this->get_env('SMTP_PASS', null);
        $mailer->SMTPSecure = $this->get_env('SMTP_SMTPSECURE', null);
        $mailer->SMTPAutoTLS = $this->get_env('SMTP_SMTPAUTOTLS', true);
        
        $mailer->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mailer->ContentType = $this->getFirstNonemptyValue($mailHeaders['content_type'] ?? null, 'text/plain');
        if ($mailer->ContentType === 'text/html') {
            $mailer->isHTML(true);
        }

        if (str_contains(substr($mailer->Body, 0, 150), '<html')) {
            $mailer->isHTML(true);
        }


        $mailer->CharSet = apply_filters( 'wp_mail_charset', get_bloginfo('charset') );

        // Set custom headers.
        if ( isset($mailHeaders['additional_headers']) && !empty( $mailHeaders['additional_headers'] ) ) {
            foreach ( (array) $mailHeaders['additional_headers'] as $name => $content ) {

                if ( ! in_array( $name, array( 'MIME-Version', 'X-Mailer' ), true ) ) {
                    $mailer->addCustomHeader( sprintf( '%1$s: %2$s', $name, $content ) );
                }
            }

            if (isset($mailHeaders['content_type'])) {
                if ( stripos( $mailHeaders['content_type'], 'multipart' ) !== false && ! empty( $mailHeaders['boundary'] ) ) {
                    $mailer->addCustomHeader( sprintf( 'Content-Type: %s; boundary="%s"', $mailHeaders['content_type'], $mailHeaders['boundary'] ) );
                }
            }

            
        }

        if (isset($mailArgs['attachments'])) {
            if ( !empty( $mailArgs['attachments'] ?? [] ) ) {
                foreach( $mailArgs['attachments'] as $attachment ) {
                    try {
                        $mailer->addAttachment($attachment);
                    } catch (Exception $e) {
                        continue;
                    }
                }
            }
        }

        $mail_data = [];
        if ( isset( $mailArgs['to'] ) ) {
            $mail_data['to'] = $mailArgs['to'];
        }
        if ( isset( $mailArgs['subject'] ) ) {
            $mail_data['subject'] = $mailArgs['subject'];
        }
        if ( isset( $mailArgs['message'] ) ) {
            $mail_data['message'] = $mailArgs['message'];
        }
        if ( isset( $mailArgs['headers'] ) ) {
            $mail_data['headers'] = $mailArgs['headers'];
        }
        if ( isset( $mailArgs['attachments'] ) ) {
            $mail_data['attachments'] = $mailArgs['attachments'];
        }

        try {
            $send = $mailer->send();
            do_action( 'wp_mail_succeeded', $mail_data );
            return $send;
        } catch ( \PHPMailer\PHPMailer\Exception $e ) {
            $mail_data['phpmailer_exception_code'] = $e->getCode();
            do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', $e->getMessage(), $mail_data ) );
            return false;
    }

    }
}