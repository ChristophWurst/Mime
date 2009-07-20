<?php
/**
 * The Horde_Mime_Mail:: class wraps around the various MIME library classes
 * to provide a simple interface for creating and sending MIME messages.
 *
 * Copyright 2007-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Jan Schneider <jan@horde.org>
 * @package Horde_Mime
 */
class Horde_Mime_Mail
{
    /**
     * The message headers.
     *
     * @var Horde_Mime_Headers
     */
    protected $_headers;

    /**
     * The base MIME part.
     *
     * @var Horde_Mime_Part
     */
    protected $_base;

    /**
     * The main body part.
     *
     * @var Horde_Mime_Part
     */
    protected $_body;

    /**
     * The main HTML body part.
     *
     * @var Horde_Mime_Part
     */
    protected $_htmlBody;

    /**
     * The message recipients.
     *
     * @var array
     */
    protected $_recipients = array();

    /**
     * All MIME parts except the main body part.
     *
     * @var array
     */
    protected $_parts = array();

    /**
     * The Mail driver name.
     *
     * @link http://pear.php.net/Mail
     * @var string
     */
    protected $_mailer_driver = 'smtp';

    /**
     * The charset to use for the message.
     *
     * @var string
     */
    protected $_charset;

    /**
     * The Mail driver parameters.
     *
     * @link http://pear.php.net/Mail
     * @var array
     */
    protected $_mailer_params = array();

    /**
     * Constructor.
     *
     * @param string $subject  The message subject.
     * @param string $body     The message body.
     * @param string $to       The message recipient(s).
     * @param string $from     The message sender.
     * @param string $charset  The character set of the message.
     *
     * @throws Horde_Mime_Exception
     */
    public function __construct($subject = null, $body = null, $to = null,
                                $from = null, $charset = null)
    {
        /* Set SERVER_NAME. */
        if (!isset($_SERVER['SERVER_NAME'])) {
            $_SERVER['SERVER_NAME'] = php_uname('n');
        }
        if (!$charset) {
            $charset = 'iso-8859-1';
        }

        $this->_headers = new Horde_Mime_Headers();
        $this->_charset = $charset;

        if ($subject) {
            $this->addHeader('Subject', $subject, $charset);
        }
        if ($to) {
            $this->addHeader('To', $to, $charset);
        }
        if ($from) {
            $this->addHeader('From', $from, $charset);
        }
        if ($body) {
            $this->setBody($body, $charset);
        }
    }

    /**
     * Adds several message headers at once.
     *
     * @param array $header    Hash with header names as keys and header
     *                         contents as values.
     * @param string $charset  The header value's charset.
     *
     * @throws Horde_Mime_Exception
     */
    public function addHeaders($headers = array(), $charset = 'iso-8859-1')
    {
        foreach ($headers as $header => $value) {
            $this->addHeader($header, $value, $charset);
        }
    }

    /**
     * Adds a message header.
     *
     * @param string $header      The header name.
     * @param string $value       The header value.
     * @param string $charset     The header value's charset.
     * @param boolean $overwrite  If true, an existing header of the same name
     *                            is being overwritten; if false, multiple
     *                            headers are added; if null, the correct
     *                            behaviour is automatically chosen depending
     *                            on the header name.
     *
     * @throws Horde_Mime_Exception
     */
    public function addHeader($header, $value, $charset = 'iso-8859-1',
                              $overwrite = null)
    {
        $lc_header = Horde_String::lower($header);

        /* Only encode value if charset is explicitly specified, otherwise
         * the message's charset will be used when building the message. */
        if (!empty($charset)) {
            if (in_array($lc_header, $this->_headers->addressFields())) {
                $value = Horde_Mime::encodeAddress($value, $charset);
            } else {
                $value = Horde_Mime::encode($value, $charset);
            }
        }

        if (is_null($overwrite)) {
            if (in_array($lc_header, $this->_headers->singleFields(true))) {
                $overwrite = true;
            }
        }

        if ($overwrite) {
            $this->_headers->removeHeader($header);
        }

        if ($lc_header !== 'bcc') {
            $this->_headers->addHeader($header, $value);
        }

        if (in_array($lc_header, array('to', 'cc', 'bcc'))) {
            return $this->addRecipients($value);
        }
    }

    /**
     * Removes a message header.
     *
     * @param string $header  The header name.
     */
    public function removeHeader($header)
    {
        $value = $this->_headers->getValue($header);
        $this->_headers->removeHeader($header);
        if (in_array(Horde_String::lower($header), array('to', 'cc', 'bcc'))) {
            try {
                $this->removeRecipients($value);
            } catch (Horde_Mime_Exception $e) {}
        }
    }

    /**
     * Sets the message body text.
     *
     * @param string $body             The message content.
     * @param string $charset          The character set of the message.
     * @param boolean|integer $wrap    If true, wrap the message at column 76;
     *                                 If an integer wrap the message at that
     *                                 column. Don't use wrapping if sending
     *                                 flowed messages.
     */
    public function setBody($body, $charset = 'iso-8859-1', $wrap = false)
    {
        if ($wrap) {
            $body = Horde_String::wrap($body, $wrap === true ? 76 : $wrap, "\n");
        }
        $this->_body = new Horde_Mime_Part();
        $this->_body->setType('text/plain');
        $this->_body->setCharset($charset);
        $this->_body->setContents($body);
    }

    /**
     * Sets the HTML message body text.
     *
     * @param string $body          The message content.
     * @param string $charset       The character set of the message.
     * @param boolean $alternative  If true, a multipart/alternative message is
     *                              created and the text/plain part is
     *                              generated automatically. If false, a
     *                              text/html message is generated.
     */
    public function setHTMLBody($body, $charset = 'iso-8859-1',
                                $alternative = true)
    {
        $this->_htmlBody = new Horde_Mime_Part();
        $this->_htmlBody->setType('text/html');
        $this->_htmlBody->setCharset($charset);
        $this->_htmlBody->setContents($body);
        if ($alternative) {
            $this->setBody(Horde_Text_Filter::filter($body, 'html2text', array('wrap' => false), $charset));
        }
    }

    /**
     * Adds a message part.
     *
     * @param string $mime_type    The content type of the part.
     * @param string $content      The content of the part.
     * @param string $charset      The character set of the part.
     * @param string $disposition  The content disposition of the part.
     *
     * @return integer  The part number.
     */
    public function addPart($mime_type, $content, $charset = 'us-ascii',
                            $disposition = null)
    {
        $part = new Horde_Mime_Part();
        $part->setType($mime_type);
        $part->setCharset($charset);
        $part->setDisposition($disposition);
        $part->setContents($content);
        return $this->addMimePart($part);
    }

    /**
     * Adds a MIME message part.
     *
     * @param Horde_Mime_Part $part  A Horde_Mime_Part object.
     *
     * @return integer  The part number.
     */
    public function addMimePart($part)
    {
        $this->_parts[] = $part;
        return count($this->_parts) - 1;
    }

    /**
     * Sets the base MIME part.
     *
     * If the base part is set, any text bodies will be ignored when building
     * the message.
     *
     * @param Horde_Mime_Part $part  A Horde_Mime_Part object.
     */
    public function setBasePart($part)
    {
        $this->_base = $part;
    }

    /**
     * Adds an attachment.
     *
     * @param string $file     The path to the file.
     * @param string $name     The file name to use for the attachment.
     * @param string $type     The content type of the file.
     * @param string $charset  The character set of the part (only relevant for
     *                         text parts.
     *
     * @return integer  The part number.
     */
    public function addAttachment($file, $name = null, $type = null,
                                  $charset = 'us-ascii')
    {
        if (empty($name)) {
            $name = basename($file);
        }

        if (empty($type)) {
            $type = Horde_Mime_Magic::filenameToMime($file, false);
        }

        $num = $this->addPart($type, file_get_contents($file), $charset, 'attachment');
        $this->_parts[$num]->setName($name);
        return $num;
    }

    /**
     * Removes a message part.
     *
     * @param integer $part  The part number.
     */
    public function removePart($part)
    {
        if (isset($this->_parts[$part])) {
            unset($this->_parts[$part]);
        }
    }

    /**
     * Adds message recipients.
     *
     * Recipients specified by To:, Cc:, or Bcc: headers are added
     * automatically.
     *
     * @param string|array  List of recipients, either as a comma separated
     *                      list or as an array of email addresses.
     *
     * @throws Horde_Mime_Exception
     */
    public function addRecipients($recipients)
    {
        $this->_recipients = array_merge($this->_recipients, $this->_buildRecipients($recipients));
    }

    /**
     * Removes message recipients.
     *
     * @param string|array  List of recipients, either as a comma separated
     *                      list or as an array of email addresses.
     *
     * @throws Horde_Mime_Exception
     */
    public function removeRecipients($recipients)
    {
        $this->_recipients = array_diff($this->_recipients, $this->_buildRecipients($recipients));
    }

    /**
     * Removes all message recipients.
     */
    public function clearRecipients()
    {
        $this->_recipients = array();
    }

    /**
     * Builds a recipients list.
     *
     * @param string|array  List of recipients, either as a comma separated
     *                      list or as an array of email addresses.
     *
     * @return array  Normalized list of recipients.
     * @throws Horde_Mime_Exception
     */
    protected function _buildRecipients($recipients)
    {
        if (is_string($recipients)) {
            $recipients = Horde_Mime_Address::explode($recipients, ',');
        }
        $recipients = array_filter(array_map('trim', $recipients));

        $addrlist = array();
        foreach ($recipients as $email) {
            if (!empty($email)) {
                $unique = Horde_Mime_Address::bareAddress($email);
                if ($unique) {
                    $addrlist[$unique] = $email;
                } else {
                    $addrlist[$email] = $email;
                }
            }
        }

        foreach (Horde_Mime_Address::bareAddress(implode(', ', $addrlist), null, true) as $val) {
            if (Horde_Mime::is8bit($val)) {
                throw new Horde_Mime_Exception(sprintf(_("Invalid character in e-mail address: %s."), $val));
            }
        }

        return $addrlist;
    }

    /**
     * Sends this message.
     *
     * For the possible Mail drivers and parameters see the PEAR Mail
     * documentation.
     * @link http://pear.php.net/Mail
     *
     * @param string $driver   The Mail driver to use.
     * @param array $params    Any parameters necessary for the Mail driver.
     * @param boolean $resend  If true, the message id and date are re-used;
     *                         If false, they will be updated.
     * @param boolean $flowed  Send message in flowed text format.
     *
     * @throws Horde_Mime_Exception
     */
    public function send($driver = null, $params = array(), $resend = false,
                         $flowed = true)
    {
        /* Add mandatory headers if missing. */
        if (!$resend || !$this->_headers->getValue('Message-ID')) {
            $this->_headers->addMessageIdHeader();
        }
        if (!$this->_headers->getValue('User-Agent')) {
            $this->_headers->addUserAgentHeader();
        }
        if (!$resend || !$this->_headers->getValue('Date')) {
            $this->_headers->addHeader('Date', date('r'));
        }

        if (isset($this->_base)) {
            $basepart = $this->_base;
        } else {
            /* Send in flowed format. */
            if ($flowed && !empty($this->_body)) {
                $flowed = new Horde_Text_Flowed($this->_body->getContents(), $this->_body->getCharset());
                $flowed->setDelSp(true);
                $this->_body->setContentTypeParameter('format', 'flowed');
                $this->_body->setContentTypeParameter('DelSp', 'Yes');
                $this->_body->setContents($flowed->toFlowed());
            }

            /* Build mime message. */
            $body = null;
            if (!empty($this->_body) && !empty($this->_htmlBody)) {
                $body = new Horde_Mime_Part();
                $body->setType('multipart/alternative');
                $this->_body->setDescription(_("Plaintext Version of Message"));
                $body->addPart($this->_body);
                $this->_htmlBody->setDescription(_("HTML Version of Message"));
                $body->addPart($this->_htmlBody);
            } elseif (!empty($this->_htmlBody)) {
                $body = $this->_htmlBody;
            } elseif (!empty($this->_body)) {
                $body = $this->_body;
            }
            if (count($this->_parts)) {
                $basepart = new Horde_Mime_Part();
                $basepart->setType('multipart/mixed');
                if ($body) {
                    $basepart->addPart($body);
                }
                foreach ($this->_parts as $mime_part) {
                    $basepart->addPart($mime_part);
                }
            } else {
                $basepart = $body;
            }
        }

        /* Check mailer configuration. */
        if (!empty($driver)) {
            $this->_mailer_driver = $driver;
        }
        if (!empty($params)) {
            $this->_mailer_params = $params;
        }

        /* Send message. */
        $res = $basepart->send(implode(', ', $this->_recipients),
                               $this->_headers, $this->_mailer_driver,
                               $this->_mailer_params);
        if (is_a($res, 'PEAR_Error')) {
            throw new Horde_Mime_Exception($res);
        }

        return $res;
    }

    /**
     * Return error string corresponding to a sendmail error code.
     *
     * @param integer $code  The error code.
     *
     * @return string  The error string, or null if the code is unknown.
     */
    static public function sendmailError($code)
    {
        switch ($code) {
        case 64: // EX_USAGE
            return 'sendmail: ' . _("command line usage error") . ' (64)';

        case 65: // EX_DATAERR
            return 'sendmail: ' . _("data format error") . ' (65)';

        case 66: // EX_NOINPUT
            return 'sendmail: ' . _("cannot open input") . ' (66)';

        case 67: // EX_NOUSER
            return 'sendmail: ' . _("addressee unknown") . ' (67)';

        case 68: // EX_NOHOST
            return 'sendmail: ' . _("host name unknown") . ' (68)';

        case 69: // EX_UNAVAILABLE
            return 'sendmail: ' . _("service unavailable") . ' (69)';

        case 70: // EX_SOFTWARE
            return 'sendmail: ' . _("internal software error") . ' (70)';

        case 71: // EX_OSERR
            return 'sendmail: ' . _("system error") . ' (71)';

        case 72: // EX_OSFILE
            return 'sendmail: ' . _("critical system file missing") . ' (72)';

        case 73: // EX_CANTCREAT
            return 'sendmail: ' . _("cannot create output file") . ' (73)';

        case 74: // EX_IOERR
            return 'sendmail: ' . _("input/output error") . ' (74)';

        case 75: // EX_TEMPFAIL
            return 'sendmail: ' . _("temporary failure") . ' (75)';

        case 76: // EX_PROTOCOL
            return 'sendmail: ' . _("remote error in protocol") . ' (76)';

        case 77: // EX_NOPERM
            return 'sendmail: ' . _("permission denied") . ' (77)';

        case 78: // EX_CONFIG
            return 'sendmail: ' . _("configuration error") . ' (78)';

        case 79: // EX_NOTFOUND
            return 'sendmail: ' . _("entry not found") . ' (79)';

        default:
            return null;
        }
    }
}
