#!/usr/bin/env php
<?php
/**
 * File containing the ezs3uploadnewfilesbyurl.php bin script
 *
 * @copyright Copyright (C) 1999 - 2014 Brookins Consulting. All rights reserved.
 * @copyright Copyright (C) 2013 - 2014 Think Creative. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2 (or later)
 * @version 0.4.1
 * @package ezs3upload
 */

require 'autoload.php';
require 'extension/ezs3upload/classes/ezs3upload.php';

/** Script startup and initialization **/

$cli = eZCLI::instance();
$script = eZScript::instance( array( 'description' => ( "eZ Publish Upload Remote Files to Amazon S3 Script\n" .
                                                        "\n" .
                                                        "ezs3uploadnewfilesbyurl.php --subdirectory=upload/ --permissions=readwrite --storage-dir=var/import/ --removetempfile --verbose" ),
                                     'use-session' => false,
                                     'use-modules' => true,
                                     'use-extensions' => true,
                                     'user' => true ) );

$script->startup();

$options = $script->getOptions( "[subdirectory:][storage-dir:][permissions:][removetempfile;]",
                                "[node]",
                                array( 'subdirectory' => 'Directory to place uploaded files in. Optional. Example: upload/',
                                       'storage-dir' => 'Directory to place downloaded files in. Optional. Example: var/import/',
                                       'permissions' => 'S3 file permissions assign when uploading. Optional. Example: read, readwrite, authread, private',
                                       'removetempfile' => 'Remove temp file. Optional. Example: --removetempfile' ),
                                false,
                                array( 'user' => true ) );
$script->initialize();

/** Script default values **/

$status = true;

$awsAccessKey = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'Key' );
$awsSecretKey = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'SecretKey' );
$awsBucket = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'Bucket' );

/** Test for required script arguments **/

if ( $options['subdirectory'] )
{
    $subdirectory = $options['subdirectory'];
}
else
{
    $subdirectory = '';
}
if ( $options['storage-dir'] )
{
    $storagedir = $options['storage-dir'];
}
else
{
    $storagedir = '';
}
if ( $options['permissions'] )
{
    if( $options['permissions'] == 'read' ) {
        $awsFilePermissions = S3::ACL_PUBLIC_READ;
    } elseif( $options['permissions'] == 'readwrite' ) {
        $awsFilePermissions = S3::ACL_PUBLIC_READ_WRITE;
    } elseif( $options['permissions'] == 'authread' ) {
        $awsFilePermissions = S3::ACL_AUTHENTICATED_READ;
    } elseif( $options['permissions'] == 'private' ) {
        $awsFilePermissions = S3::ACL_PRIVATE;
    } else {
        $awsFilePermissions = S3::ACL_PRIVATE;
    }
}
else
{
    $awsFilePermissions = S3::ACL_PRIVATE;
}
if ( $options['verbose'] )
{
    $verbose = true;
}
else
{
    $verbose = false;
}
if ( $options['removetempfile'] )
{
    $removetempfile = true;
}
else
{
    $removetempfile = false;
}

/** Files and related NodeIDs **/

$urlsAndNodes = array( 'http://tc/var/cdev_base/storage/images/media/test-image-001/100076-3-eng-GB/test-image-001_large.jpg' => true,
                       'http://tc/var/cdev_base/storage/images/media/test-image-001/100076-3-eng-GB/test-image-001_medium.jpg' => false,
                       'http://tc/var/cdev_base/storage/images/media/test-image-001/100076-3-eng-GB/test-image-001.jpg' => true );
$urlsAndNodesCount = count( $urlsAndNodes );

/** Setup script iteration details **/

$script->setIterationData( '.', '.' );
$script->resetIteration( $urlsAndNodesCount );

$upload = new eZS3Upload( $awsAccessKey, $awsSecretKey );

/** Iterate over urls and nodes **/

while ( list( $url, $createNode ) = each( $urlsAndNodes ) )
{
    if( $verbose )
    {
        $cli->output( "\nPreparing to download file $url to Amazon S3 storage ...\n" );
    }

    /** Calculate file name, path, upload subdirectory **/
    $fileUriArray = array_reverse( explode( '/', $url ) );
    $fileName = $fileUriArray[0];
    $path = $storagedir . $fileName;

    $fileUriArray = array_reverse( explode( '/', $path ) );
    $fileNameUri = $fileUriArray[0];
    $uri = $subdirectory . $fileNameUri;

    if( $verbose )
    {
        $cli->output( "Downloading file $path to local disk for upload ...\n" );
    }

    /** Download file into temporary storage directory **/
    $downloadResult = $upload->downloadFile( $url, $storagedir );

    /** Upload file from temporary storage directory **/
    $uploadResult = $upload->cliUpload( $path, $uri, false, $awsBucket, $awsFilePermissions, $removetempfile, $createNode, $cli, $script, $verbose );

    $script->iterate( $cli, $status );
}

/** Shutdown script **/

$script->shutdown();

?>