<?php
/**
 * The Horde_Mime_Viewer_Tgz class renders out plain or gzipped tarballs in
 * HTML.
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Anil Madhavapeddy <anil@recoil.org>
 * @author  Michael Cochrane <mike@graftonhall.co.nz>
 * @package Horde_Mime_Viewer
 */
class Horde_Mime_Viewer_Tgz extends Horde_Mime_Viewer_Driver
{
    /**
     * Can this driver render various views?
     *
     * @var boolean
     */
    protected $_capability = array(
        'embedded' => false,
        'forceinline' => true,
        'full' => false,
        'info' => true,
        'inline' => true
    );

    /**
     * The list of compressed subtypes.
     *
     * @var array
     */
    protected $_gzipSubtypes = array(
        'x-compressed-tar', 'tgz', 'x-tgz', 'gzip', 'x-gzip',
        'x-gzip-compressed', 'x-gtar'
    );

    /**
     * Return the rendered inline version of the Horde_Mime_Part object.
     *
     * @return array  See Horde_Mime_Viewer_Driver::render().
     */
    protected function _renderInline()
    {
        /* Currently, can't do anything without tar file. */
        $subtype = $this->_mimepart->getSubType();
        if (in_array($subtype, array('gzip', 'x-gzip', 'x-gzip-compressed'))) {
            return array();
        }

        $contents = $this->_mimepart->getContents();

        /* Decompress gzipped files. */
        if (in_array($subtype, $this->_gzipSubtypes)) {
            $gzip = &Horde_Compress::singleton('gzip');
            $contents = $gzip->decompress($contents);
            if (is_a($contents, 'PEAR_Error') || empty($contents)) {
                return array();
            }
        }

        /* Obtain the list of files/data in the tar file. */
        $tar = &Horde_Compress::singleton('tar');
        $tarData = $tar->decompress($contents);
        if (is_a($tarData, 'PEAR_Error')) {
            return array();
        }

        $charset = Horde_Nls::getCharset();
        $fileCount = count($tarData);

        $name = $this->_mimepart->getName(true);
        if (empty($name)) {
            $name = _("unnamed");
        }

        $text = '<strong>' . htmlspecialchars(sprintf(_("Contents of \"%s\""), $name)) . ':</strong>' . "\n" .
            '<table><tr><td align="left"><tt><span class="fixed">' .
            Horde_Text_Filter::filter(_("Archive Name") . ':  ' . $name, 'space2html', array('charset' => $charset, 'encode' => true, 'encode_all' => true)) . "\n" .
            Horde_Text_Filter::filter(_("Archive File Size") . ': ' . strlen($contents) . ' bytes', 'space2html', array('charset' => $charset, 'encode' => true, 'encode_all' => true)) . "\n" .
            Horde_Text_Filter::filter(sprintf(ngettext("File Count: %d file", "File Count: %d files", $fileCount), $fileCount), 'space2html', array('charset' => $charset, 'encode' => true, 'encode_all' => true)) .
            "\n\n" .
            Horde_Text_Filter::filter(
                str_pad(_("File Name"), 62, ' ', STR_PAD_RIGHT) .
                str_pad(_("Attributes"), 15, ' ', STR_PAD_LEFT) .
                str_pad(_("Size"), 10, ' ', STR_PAD_LEFT) .
                str_pad(_("Modified Date"), 19, ' ', STR_PAD_LEFT),
                'space2html',
                array('charset' => $charset, 'encode' => true, 'encode_all' => true)
            ) . "\n" .
            str_repeat('-', 106) . "\n";

        foreach ($tarData as $val) {
            $text .= Horde_Text_Filter::filter(
                str_pad($val['name'], 62, ' ', STR_PAD_RIGHT) .
                str_pad($val['attr'], 15, ' ', STR_PAD_LEFT) .
                str_pad($val['size'], 10, ' ', STR_PAD_LEFT) .
                str_pad(strftime("%d-%b-%Y %H:%M", $val['date']), 19, ' ', STR_PAD_LEFT),
                'space2html',
                array('charset' => $charset, 'encode' => true, 'encode_all' => true)
            ) . "\n";
        }

        return array(
            $this->_mimepart->getMimeId() => array(
                'data' => nl2br($text . str_repeat('-', 106) . "\n" . '</span></tt></td></tr></table>'),
                'status' => array(),
                'type' => 'text/html; charset=' . $charset
            )
        );
    }

    /**
     * Return the rendered information about the Horde_Mime_Part object.
     *
     * @return array  See Horde_Mime_Viewer_Driver::render().
     */
    protected function _renderInfo()
    {
        return $this->_renderInline();
    }
}
