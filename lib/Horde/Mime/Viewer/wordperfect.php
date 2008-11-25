<?php
/**
 * The Horde_Mime_Viewer_wordperfect class renders out WordPerfect documents
 * in HTML format by using the libwpd package.
 *
 * libpwd website: http://libwpd.sourceforge.net/
 *
 * Copyright 2007-2008 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Matt Selsky <selsky@columbia.edu>
 * @package Horde_Mime_Viewer
 */
class Horde_Mime_Viewer_wordperfect extends Horde_Mime_Viewer_Driver
{
    /**
     * Can this driver render various views?
     *
     * @var boolean
     */
    protected $_capability = array(
        'embedded' => false,
        'forceinline' => false,
        'full' => true,
        'info' => false,
        'inline' => false
    );

    /**
     * Return the full rendered version of the Horde_Mime_Part object.
     *
     * @return array  See Horde_Mime_Viewer_Driver::render().
     */
    protected function _render()
    {
        /* Check to make sure the viewer program exists. */
        if (!isset($this->_conf['location']) ||
            !file_exists($this->_conf['location'])) {
            return array();
        }

        $tmp_wpd = Horde::getTempFile('wpd');
        $tmp_output = Horde::getTempFile('wpd');

        file_put_contents($tmp_wpd, $this->_mimepart->getContents());

        exec($this->_conf['location'] . " $tmp_wpd > $tmp_output");

        if (file_exists($tmp_output)) {
            $data = file_get_contents($tmp_output);
        } else {
            $data = _("Unable to translate this WordPerfect document");
        }

        return array(
            $this->_mimepart->getMimeId() => array(
                'data' => $data,
                'status' => array(),
                'type' => 'text/html; charset=' . NLS::getCharset()
            )
        );
    }
}
