<?php
/**
 * The Horde_Mime_Viewer_msword class renders out Microsoft Word documents
 * in HTML format by using the AbiWord package.
 *
 * Copyright 1999-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Anil Madhavapeddy <anil@recoil.org>
 * @package Horde_Mime_Viewer
 */
class Horde_Mime_Viewer_msword extends Horde_Mime_Viewer_Driver
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

        $tmp_word = Horde::getTempFile('msword');
        $tmp_output = Horde::getTempFile('msword');
        $tmp_file = str_replace(Horde::getTempDir() . '/', '', $tmp_output);

        file_put_contents($tmp_word, $this->_mimepart->getContents());
        $args = ' --to=html --to-name=' . $tmp_output . ' ' . $tmp_word;

        exec($this->_conf['location'] . $args);

        if (file_exists($tmp_output)) {
            $data = file_get_contents($tmp_output);
            $type = 'text/html';
        } else {
            $data = _("Unable to translate this Word document");
            $type = 'text/plain';
        }

        return array(
            $this->_mimepart->getMimeId() => array(
                'data' => $data,
                'status' => array(),
                'type' => $type . '; charset=' . NLS::getCharset()
            )
        );
    }
}
