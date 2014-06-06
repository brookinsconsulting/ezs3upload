<?php
/**
 * File containing the ezs3uploadInfo class.
 *
 * @copyright Copyright (C) 1999 - 2014 Brookins Consulting. All rights reserved.
 * @copyright Copyright (C) 2013 - 2014 Think Creative. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2 (or any later version)
 * @version //autogentag//
 * @package ezs3upload
 */
class ezs3uploadInfo
{
    function info()
    {
        return array(
            'Name' => "<a href='http://github.com/brookinsconsulting/ezs3upload'>eZ S3 Upload Solution</a>",
            'Version' => "0.4.7",
            'Copyright' => array( "Copyright (C) 1999 - 2014 <a href='http://brookinsconsulting.com'>Brookins Consulting</a>",
                                  "Copyright (C) 2013 - 2014, <a href='http://thinkcreative.com'>Think Creative</a>",
                                  "Copyright (c) 2008, <a href='http://undesigned.org.za/2007/10/22/amazon-s3-php-class'>Donovan Schönknecht</a>. All rights reserved" ),
            'Author' => "Brookins Consulting",
            'License' => "GNU General Public License",
            'info_url' => "http://github.com/brookinsconsulting/ezs3upload",
            '3rd Party Libraries' => array( "This extension contains the following MIT licensed files:", "License MIT: swfupload.js, jquery.swfupload.js, fileprogress.js" )
        );
    }
}
?>