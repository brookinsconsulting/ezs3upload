#!/usr/bin/env php
<?php
/**
 * File containing the ezs3rename.php bin script
 *
 * @copyright Copyright (C) 1999 - 2015 Brookins Consulting. All rights reserved.
 * @copyright Copyright (C) 2013 - 2015 Think Creative. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2 (or later)
 * @version 0.0.8
 * @package ezps3upload
 */

/** Disable script php memory limit **/

ini_set("memory_limit", -1);

/** Script autoloads initialization **/

require 'autoload.php';

/** Script startup and initialization **/

$cli = eZCLI::instance();
$script = eZScript::instance( array( 'description' => ( "eZ Publish AWS S3 Rename File Script\n" .
                                                        "\n" .
                                                        "ezs3rename.php --old-path=Media/Test/File.jpg --new-path=Media/Test/File.2013.jpg" ),
                                     'use-session' => false,
                                     'use-modules' => true,
                                     'use-extensions' => true,
                                     'user' => true ) );

$script->startup();

$options = $script->getOptions( "[script-verbose;][script-verbose-level;][old-path:][new-path:]",
                                "[node]",
                                array( 'old-path' => 'Path on AWS S3. Example: --old-path=Media/Test/File.jpg',
                                       'new-path' => 'Path on AWS S3. Example: --new-path=Media/Test/File.2013.jpg',
                                       'script-verbose' => 'Use this parameter to display verbose script output without disabling script iteration counting of images created or removed. Example: ' . "'--script-verbose'" . ' is an optional parameter which defaults to false',
                                       'script-verbose-level' => 'Use only with ' . "'--script-verbose'" . ' parameter to see more of execution internals. Example: ' . "'--script-verbose-level=3'" . ' is an optional parameter which defaults to 1 and works till 5'),
                                false,
                                array( 'user' => true ) );
$script->initialize();

/** Script default values **/

$limit = 100;
$offset = 0;
$awsS3Bucket = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'Bucket' );

/** Test for required script arguments **/

if ( isset( $options['old-path'] ) )
{
    $oldPath = $options['old-path'];
}
else
{
    $cli->error( 'Old path is required. Specify a content treee node path to file the command' );
    $script->shutdown( 1 );
}

if ( isset( $options['new-path'] ) )
{
    $newPath = $options['new-path'];
}
else
{
    $cli->error( 'New path is required. Specify a content treee node path to file the command' );
    $script->shutdown( 1 );
}

$verbose = isset( $options['script-verbose'] ) ? true : false;

$scriptVerboseLevel = isset( $options['script-verbose-level'] ) ? $options['script-verbose-level'] : 1;

$troubleshoot = ( isset( $options['script-verbose-level'] ) && $options['script-verbose-level'] > 0 ) ? true : false;

/** Debug verbose output **/

if( $troubleshoot && $scriptVerboseLevel >= 3 )
{
    $cli->output( "Old path: " . $oldPath . "\n" );
    $cli->output( "New path: " . $oldPath . "\n" );
}

/** Connect to aws s3 service **/

$awsS3 = new AmazonS3();

/** Copy object path from old version to new version **/

$response = $awsS3->copy_object(
            array(// Source
                  'bucket' => $awsS3Bucket,
                  'filename' => $oldPath ),
            array(// Destination
                  'bucket' => $awsS3Bucket,
                  'filename' => $newPath ) );

/** Only delete old s3 file object if copy is a success **/

if( $response->isOK() )
{
    /** Debug verbose output **/

    if( $verbose )
    {
        $cli->output( "Copy file path name on AWS S3 successfull! New path: '$nodePathName'\n");
    }

    $delete = $awsS3->delete_object( $awsS3Bucket, $oldPath );

    /** Optional debug output **/

    if( $troubleshoot && $scriptVerboseLevel >= 3 )
    {
        $cli->output( "Delete Object Responce: \n". print_r( $delete ) . "\n");
    }

    if( $delete->isOK() )
    {
        /** Debug verbose output **/

        if( $verbose )
        {
            $cli->output( "Success: Deletion of AWS S3 File object. Path: '$oldPath'\n");
        }
    }
}
else
{
    /** Debug verbose output **/

    if( $verbose )
    {
        /** Catch error, 404 file not found **/

        if( $response->status == 404 )
        {
            $cli->output( "Reason: S3 File object copy failed because " . $oldPath . " no longer exists\n" );
        }
    }

    /** Optional debug output **/

    if( $troubleshoot && $scriptVerboseLevel >= 3 )
    {
        $cli->output( "Copy S3 File object response: \n" . print_r( $response ) );
    }
}

/** Shutdown script **/
$script->shutdown();

?>