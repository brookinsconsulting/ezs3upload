#!/usr/bin/env php
<?php
/**
 * File containing the ezs3uploadfile.php bin script
 *
 * @copyright Copyright (C) 1999 - 2014 Brookins Consulting. All rights reserved.
 * @copyright Copyright (C) 2013 - 2014 Think Creative. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2 (or later)
 * @version 0.4.2
 * @package ezs3upload
 */

require 'autoload.php';
require 'extension/ezs3upload/classes/ezs3upload.php';

/** Script startup and initialization **/

$cli = eZCLI::instance();
$script = eZScript::instance( array( 'description' => ( "eZ Publish Upload Files to S3 Script\n" .
                                                        "\n" .
                                                        "ezs3uploadfile.php --subdirectory=upload/ --file=var/log/error.log --nodeid=43 --permissions=readwrite" ),
                                     'use-session' => false,
                                     'use-modules' => true,
                                     'use-extensions' => true,
                                     'user' => true ) );

$script->startup();

$options = $script->getOptions( "[subdirectory:][file:][nodeid:][permissions:][removetempfile;]",
                                "[node]",
                                array( 'subdirectory' => 'Directory to place uploaded files in. Optional. Example: upload/',
                                       'file' => 'Path to file to upload to S3 bucket. Required. Example: var/log/error.log',
                                       'nodeid' => 'Content tree NodeID to save file within. Optional. Example: 43',
                                       'permissions' => 'S3 file permissions assign when uploading. Optional. Example: read, readwrite, authread, private',
                                       'removetempfile' => 'Remove local file. Optional. Example: --removetempfile' ),
                                false,
                                array( 'user' => true ) );
$script->initialize();

/** Script default values **/

$status = true;

$awsAccessKey = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'Key' );
$awsSecretKey = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'SecretKey' );
$awsBucket = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'Bucket' );

/** Test for required script arguments **/

if ( $options['file'] )
{
    $file = $options['file'];
}
else
{
    $cli->error( 'File is required. Specify a path to file for upload' );
    $script->shutdown( 1 );
}
if ( $options['subdirectory'] )
{
    $subdirectory = $options['subdirectory'];
}
else
{
    $subdirectory = '';
}
if ( $options['nodeid'] )
{
    $nodeID = $options['nodeid'];
}
else
{
    $nodeID = false;
}
if ( $nodeID != false && !is_numeric( $nodeID ) )
{
    $cli->error( 'Specify a numeric node ID' );
    $script->shutdown( 2 );
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

/** Setup script iteration details **/

$script->setIterationData( '.', '.' );
$script->resetIteration( 1 );

if( $verbose )
    $cli->output( "Preparing to upload file $file to Amazon S3 storage ...\n" );

$fileUriArray = array_reverse( explode( '/', $file ) );
$fileUri = $fileUriArray[0];
$uri = $subdirectory . $fileUri;

$upload = new eZS3Upload( $awsAccessKey, $awsSecretKey );
$uploadResult = $upload->cliUpload( $file, $uri, $nodeID, $awsBucket, $awsFilePermissions, $removetempfile, false, $cli, $script, $verbose );

$script->iterate( $cli, $status );

/** Shutdown script **/

$script->shutdown();

?>