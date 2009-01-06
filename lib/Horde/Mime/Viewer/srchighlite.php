<?php

require_once dirname(__FILE__) . '/source.php';

/**
 * The Horde_Mime_Viewer_srchighlite class renders out various content in HTML
 * format by using Source-highlight.
 *
 * Source-highlight: http://www.gnu.org/software/src-highlite/
 *
 * Copyright 2003-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Mike Cochrane <mike@graftonhall.co.nz>
 * @package Horde_Mime_Viewer
 */
class Horde_Mime_Viewer_srchighlite extends Horde_Mime_Viewer_source
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
        'inline' => true
    );

    /**
     * Return the full rendered version of the Horde_Mime_Part object.
     *
     * @return array  See Horde_Mime_Viewer_Driver::render().
     */
    protected function _render()
    {
        $ret = $this->_renderInline();

        // Need Horde headers for CSS tags.
        reset($ret);
        $ret[key($ret)]['data'] =  Util::bufferOutput('require', $GLOBALS['registry']->get('templates', 'horde') . '/common-header.inc') .
            $ret[key($ret)]['data'] .
            Util::bufferOutput('require', $GLOBALS['registry']->get('templates', 'horde') . '/common-footer.inc');

        return $ret;
    }

    /**
     * Return the rendered inline version of the Horde_Mime_Part object.
     *
     * @return array  See Horde_Mime_Viewer_Driver::render().
     */
    protected function _renderInline()
    {
        /* Check to make sure the viewer program exists. */
        if (!isset($this->_conf['location']) ||
            !file_exists($this->_conf['location'])) {
            return array();
        }

        /* Create temporary files for Webcpp. */
        $tmpin  = Horde::getTempFile('SrcIn');
        $tmpout = Horde::getTempFile('SrcOut', false);

        /* Write the contents of our buffer to the temporary input file. */
        file_put_contents($tmpin, $this->_mimepart->getContents());

        /* Determine the language from the mime type. */
        $lang = $this->_typeToLang($this->_mimepart->getType());

        /* Execute Source-Highlite. */
        exec($this->_conf['location'] . " --src-lang $lang --out-format xhtml --input $tmpin --output $tmpout");
        $results = file_get_contents($tmpout);
        unlink($tmpout);

        return array(
            $this->_mimepart->getMimeId() => array(
                'data' => $this->_lineNumber($results),
                'status' => array(),
                'type' => 'text/html; charset=' . NLS::getCharset()
            )
        );
    }

    /**
     * Attempts to determine what mode to use for the source-highlight
     * program from a MIME type.
     *
     * @param string $type  The MIME type.
     *
     * @return string  The mode to use.
     */
    protected function _typeToLang($type)
    {
        switch ($type) {
        case 'text/x-java':
            return 'java';

        case 'text/x-csrc':
        case 'text/x-c++src':
        case 'text/cpp':
            return 'cpp';

        case 'application/x-perl':
            return 'perl';

        case 'application/x-php':
        case 'x-extension/phps':
        case 'x-extension/php3s':
        case 'application/x-httpd-php':
        case 'application/x-httpd-php3':
        case 'application/x-httpd-phps':
            return 'php3';

        case 'application/x-python':
            return 'python';

        // TODO: 'prolog', 'flex', 'changelog', 'ruby'
        }
    }
}
