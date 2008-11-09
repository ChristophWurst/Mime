<?php
/**
 * The Horde_Mime_Address:: class provides methods for dealing with email
 * address standards (RFC 822/2822/5322).
 *
 * Copyright 2008 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Michael Slusarz <slusarz@horde.org>
 * @package Horde_Mime
 */
class Horde_Mime_Address
{
    /**
     * Builds an RFC compliant email address.
     *
     * @param string $mailbox   Mailbox name.
     * @param string $host      Domain name of mailbox's host.
     * @param string $personal  Personal name phrase.
     *
     * @return string  The correctly escaped and quoted
     *                 "$personal <$mailbox@$host>" string.
     */
    static public function writeAddress($mailbox, $host, $personal = '')
    {
        $address = '';

        if (strlen($personal)) {
            $address .= self::encode($personal, 'personal') . ' <';
        }

        $address .= self::encode($mailbox, 'address') . '@' . ltrim($host, '@');

        if (strlen($personal)) {
            $address .= '>';
        }

        return $address;
    }

    /**
     * Write an RFC compliant group address, given the group name and a list
     * of email addresses.
     *
     * @param string $groupname  The name of the group.
     * @param array $addresses   The component email addresses. These e-mail
     *                           addresses must be in RFC format.
     *
     * @return string  The correctly quoted group string.
     */
    static public function writeGroupAddress($groupname, $addresses = array())
    {
        return self::encode($groupname, 'address') . ':' . (empty($addresses) ? '' : implode(', ', $addresses)) . ';';
    }

    /**
     * If an email address has no personal information, get rid of any angle
     * brackets (<>) around it.
     *
     * @param string $address  The address to trim.
     *
     * @return string  The trimmed address.
     */
    static public function trimAddress($address)
    {
        $address = trim($address);

        if (($address[0] == '<') && (substr($address, -1) == '>')) {
            $address = substr($address, 1, -1);
        }

        return $address;
    }

    /**
     * Explodes an RFC string, ignoring a delimiter if preceded by a "\"
     * character, or if the delimiter is inside single or double quotes.
     *
     * @param string $string      The RFC compliant string.
     * @param string $delimiters  A string containing valid delimiters.
     *                            Defaults to ','.
     *
     * @return array  The exploded string in an array.
     */
    static public function explode($string, $delimiters = ',')
    {
        if (!strlen($string)) {
            return array($string);
        }

        $emails = array();
        $pos = 0;
        $in_group = $in_quote = false;

        for ($i = 0, $iMax = strlen($string); $i < $iMax; ++$i) {
            $char = $string[$i];
            if ($char == '"') {
                if (!$i || ($prev !== '\\')) {
                    $in_quote = !$in_quote;
                }
            } elseif ($in_group) {
                if ($char == ';') {
                    $emails[] = substr($string, $pos, $i - $pos + 1);
                    $pos = $i + 1;
                    $in_group = false;
                }
            } elseif (!$in_quote) {
                if ($char == ':') {
                    $in_group = true;
                } elseif ((strpos($delimiters, $char) !== false) &&
                          (!$i || ($prev !== '\\'))) {
                    $emails[] = $i ? substr($string, $pos, $i - $pos) : '';
                    $pos = $i + 1;
                }
            }
            $prev = $char;
        }

        if ($pos != $i) {
            /* The string ended without a delimiter. */
            $emails[] = substr($string, $pos, $i - $pos);
        }

        return $emails;
    }

    /**
     * Takes an address object array and formats it as a string.
     *
     * Object array format for the address "John Doe <john_doe@example.com>"
     * is:
     * <pre>
     * 'personal' = Personal name ("John Doe")
     * 'mailbox' = The user's mailbox ("john_doe")
     * 'host' = The host the mailbox is on ("example.com")
     * </pre>
     *
     * @param array $ob      The address object to be turned into a string.
     * @param mixed $filter  A user@example.com style bare address to ignore.
     *                       Either single string or an array of strings.  If
     *                       the address matches $filter, an empty string will
     *                       be returned.
     *
     * @return string  The formatted address.
     */
    static public function addrObject2String($ob, $filter = '')
    {
        /* If the personal name is set, decode it. */
        $ob['personal'] = isset($ob['personal'])
            ? Horde_Mime::decode($ob['personal'])
            : '';

        /* If both the mailbox and the host are empty, return an empty string.
         * If we just let this case fall through, the call to writeAddress()
         * will end up return just a '@', which is undesirable. */
        if (empty($ob['mailbox']) && empty($ob['host'])) {
            return '';
        }

        /* Make sure these two variables have some sort of value. */
        if (!isset($ob['mailbox'])) {
            $ob['mailbox'] = '';
        } elseif ($ob['mailbox'] == 'undisclosed-recipients') {
            return '';
        }
        if (!isset($ob['host'])) {
            $ob['host'] = '';
        }

        /* Filter out unwanted addresses based on the $filter string. */
        if ($filter) {
            if (!is_array($filter)) {
                $filter = array($filter);
            }
            foreach ($filter as $f) {
                if (strcasecmp($f, $ob['mailbox'] . '@' . $ob['host']) == 0) {
                    return '';
                }
            }
        }

        /* Return the formatted email address. */
        return self::writeAddress($ob['mailbox'], $ob['host'], $ob['personal']);
    }

    /**
     * Takes an array of address object arrays and passes each of them through
     * addrObject2String().
     *
     * @param array $addresses  The array of address objects.
     * @param mixed $filter     A user@example.com style bare address to
     *                          ignore.  If any address matches $filter, it
     *                          will not be included in the final string.
     *
     * @return string  All of the addresses in a comma-delimited string.
     *                 Returns the empty string on error/no addresses found.
     */
    static public function addrArray2String($addresses, $filter = '')
    {
        if (!is_array($addresses)) {
            return '';
        }

        $addrList = array();

        foreach ($addresses as $addr) {
            $val = self::addrObject2String($addr, $filter);
            if (!empty($val)) {
                $addrList[String::lower(self::bareAddress($val))] = $val;
            }
        }

        return implode(', ', $addrList);
    }

    /**
     * Return the list of addresses for a header object.
     *
     * @param array $obs  An array of header objects.
     *
     * @return array  An array of address information. Array elements:
     * <pre>
     * 'address' - (string) Full address
     * 'display' - (string) A displayable version of the address
     * 'groupname' - (string) The group name.
     * 'host' - (string) Hostname
     * 'inner' - (string) Trimmed, bare address
     * 'personal' - (string) Personal string
     * </pre>
     */
    static public function getAddressesFromObject($obs)
    {
        $ret = array();

        if (!is_array($obs) || empty($obs)) {
            return $ret;
        }

        foreach ($obs as $ob) {
            if (isset($ob['groupname'])) {
                $ret[] = array(
                    'addresses' => self::getAddressesFromObject($ob['addresses']),
                    'groupname' => $ob['groupname']
                );
                continue;
            }

            $ob = array_merge(array(
                'host' => '',
                'mailbox' => '',
                'personal' => ''
            ), $ob);

            /* Ensure we're working with initialized values. */
            if (!empty($ob['personal'])) {
                $ob['personal'] = stripslashes(trim(Horde_Mime::decode($ob['personal']), '"'));
            }

            $inner = self::writeAddress($ob['mailbox'], $ob['host']);

            /* Generate the new object. */
            $ret[] = array(
                'address' => self::addrObject2String($ob),
                'display' => (empty($ob['personal']) ? '' : $ob['personal'] . ' <') . $inner . (empty($ob['personal']) ? '' : '>'),
                'host' => $ob['host'],
                'inner' => $inner,
                'personal' => $ob['personal']
            );
        }

        return $ret;
    }

    /**
     * Returns the bare address.
     *
     * @param string $address    The address string.
     * @param string $defserver  The default domain to append to mailboxes.
     * @param boolean $multiple  Should we return multiple results?
     *
     * @return mixed  If $multiple is false, returns the mailbox@host e-mail
     *                address.  If $multiple is true, returns an array of
     *                these addresses.
     */
    static public function bareAddress($address, $defserver = null,
                                       $multiple = false)
    {
        $addressList = array();

        $from = self::parseAddressList($address, array('defserver' => $defserver));
        if (is_a($from, 'PEAR_Error')) {
            return $multiple ? array() : '';
        }

        foreach ($from as $entry) {
            if (!empty($entry['mailbox'])) {
                $addressList[] = $entry['mailbox'] . (isset($entry['host']) ? '@' . $entry['host'] : '');
            }
        }

        return $multiple ? $addressList : array_pop($addressList);
    }

    /**
     * Parses a list of email addresses into its parts. Handles distribution
     * lists.
     *
     * @param string $address  The address string.
     * @param array $options   Additional options:
     * <pre>
     * 'defserver' - (string) The default domain to append to mailboxes.
     *               DEFAULT: No domain appended.
     * 'nestgroups' - (boolean) Nest the groups? (Will appear under the
     *                'groupname' key)
     *                DEFAULT: No.
     * 'reterror' - (boolean) Return a PEAR_Error object on error?
     *              DEFAULT: Returns an empty array on error.
     * 'validate' - (boolean) Validate the address(es)?
     *              DEFAULT: No.
     * </pre>
     *
     * @return mixed  If 'reterror' is true, returns a PEAR_Error object on
     *                error.  Otherwise, a list of arrays with the possible
     *                keys: 'mailbox', 'host', 'personal', 'adl', 'groupname',
     *                and 'comment'.
     */
    static public function parseAddressList($address, $options = array())
    {
        if (preg_match('/undisclosed-recipients:\s*;/i', trim($address))) {
            return array();
        }

        $options = array_merge(array(
            'defserver' => null,
            'nestgroups' => false,
            'reterror' => false,
            'validate' => false
        ), $options);

        static $parser;
        if (!isset($parser)) {
            require_once 'Mail/RFC822.php';
            $parser = new Mail_RFC822();
        }

        $ret = $parser->parseAddressList($address, $options['defserver'], $options['nestgroups'], $options['validate']);
        if (is_a($ret, 'PEAR_Error')) {
            return empty($options['reterror']) ? array() : $ret;
        }

        /* Convert objects to arrays. */
        foreach (array_keys($ret) as $key) {
            $ret[$key] = (array) $ret[$key];
        }

        return $ret;
    }

    /**
     * Quotes and escapes the given string if necessary using rules contained
     * in RFC 2822 [3.2.5].
     *
     * @param string $str   The string to be quoted and escaped.
     * @param string $type  Either 'address' or 'personal'.
     *
     * @return string  The correctly quoted and escaped string.
     */
    static public function encode($str, $type = 'address')
    {
        // Excluded (in ASCII): 0-8, 10-31, 34, 40-41, 44, 58-60, 62, 64,
        // 91-93, 127
        $filter = "\0\1\2\3\4\5\6\7\10\12\13\14\15\16\17\20\21\22\23\24\25\26\27\30\31\32\33\34\35\36\37\"(),:;<>@[\\]\177";

        switch ($type) {
        case 'address':
            // RFC 2822 [3.4.1]: (HTAB, SPACE) not allowed in address
            $filter .= "\11\40";
            break;

        case 'personal':
            // RFC 2822 [3.4]: Period not allowed in display name
            $filter .= '.';
            break;
        }

        // Strip double quotes if they are around the string already.
        // If quoted, we know that the contents are already escaped, so
        // unescape now.
        $str = trim($str);
        if ($str && ($str[0] == '"') && (substr($str, -1) == '"')) {
            $str = stripslashes(substr($str, 1, -1));
        }

        return (strcspn($str, $filter) != strlen($str))
            ? '"' . addcslashes($str, '\\"') . '"'
            : $str;
    }
}
