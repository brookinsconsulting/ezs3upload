#!/usr/bin/env php
<?php
/**
 * File containing the ezs3rename.php bin script
 *
 * @copyright Copyright (C) 1999 - 2015 Brookins Consulting. All rights reserved.
 * @copyright Copyright (C) 2013 - 2015 Think Creative. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2 (or later)
 * @version 0.1.2
 * @package ezps3upload
 */

/** Add a starting timing point tracking script execution time **/

$srcStartTime = microtime();

/** Disable script php memory limit **/

ini_set("memory_limit", -1);

/** Script autoloads initialization **/

require 'autoload.php';

/** Script startup and initialization **/

$cli = eZCLI::instance();
$script = eZScript::instance( array( 'description' => ( "eZ Publish AWS S3 Upload Rename Script\n" .
                                                        "\n" .
                                                        "ezs3rename.php --parent-node=2 --hours=1 --min=15" ),
                                     'use-session' => false,
                                     'use-modules' => true,
                                     'use-extensions' => true,
                                     'user' => true ) );

$script->startup();

$options = $script->getOptions( "[force;][script-verbose;][script-verbose-level;][parent-node:][hours;][min;]",
                                "[node]",
                                array( 'parent-node' => 'Content Tree Node ID. Example: --parent-node=2',
                                       'force' => 'Force disables delayed startup. Example: ' . "'--force'" . ' is an optional parameter which defaults to false',
                                       'hours' => 'Number of hours to search in reverse. Example: --hours=5',
                                       'min' => 'Number of min instead of hours to search in reverse. Example: --min=15',
                                       'script-verbose' => 'Use this parameter to display verbose script output without disabling script iteration counting of images created or removed. Example: ' . "'--script-verbose'" . ' is an optional parameter which defaults to false',
                                       'script-verbose-level' => 'Use only with ' . "'--script-verbose'" . ' parameter to see more of execution internals. Example: ' . "'--script-verbose-level=3'" . ' is an optional parameter which defaults to 1 and works till 5'),
                                false,
                                array( 'user' => true ) );
$script->initialize();

/** Script default values **/

$limit = 100;
$offset = 0;
$adminUserID = 14;

$s3FileClassIdentifier = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'ClassIdentifier' );
$s3FileAttributeIdentifier = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'AttributeIdentifier' );
$s3FileRenameAttributeIdentifier = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'RenameAttributeIdentifier' );
$awsS3Bucket = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'Bucket' );

/** Login script to run as admin user  This is required to see past content tree permissions, sections and other limitations **/

$currentuser = eZUser::currentUser();
$currentuser->logoutCurrent();
$user = eZUser::fetch( $adminUserID );
$user->loginCurrent();

/** Test for required script arguments **/

if ( isset( $options['parent-node'] ) )
{
    $parentNodeID = $options['parent-node'];
}
else
{
    $cli->error( '--parent-node parameter is required. Specify a content treee node id' );
    $script->shutdown( 1 );
}

if ( !is_numeric( $parentNodeID ) )
{
    $cli->error( 'Please specify a numeric node ID' );
    $script->shutdown( 2 );
}

if ( isset( $options['hours'] ) && $options['hours'] )
{
    $hoursAgo = $options['hours'];
}
else
{
    $hoursAgo = 1;
}

if ( !is_numeric( $hoursAgo ) )
{
    $cli->error( 'Please specify a numeric hour number' );
    $script->shutdown( 2 );
}

if ( isset( $options['min'] ) && $options['min'] )
{
    $minsAgo = $options['min'];
}
else
{
    $minsAgo = 2.5;
}

if ( !is_numeric( $minsAgo ) )
{
    $cli->error( 'Please specify a numeric minute number' );
    $script->shutdown( 2 );
}

$force = isset( $options['force'] ) ? true : false;

$verbose = isset( $options['script-verbose'] ) ? true : false;

$scriptVerboseLevel = isset( $options['script-verbose-level'] ) ? $options['script-verbose-level'] : 1;

$troubleshoot = ( isset( $options['script-verbose-level'] ) && $options['script-verbose-level'] > 0 ) ? true : false;

/** Modified time stamp to search with **/

if( isset( $options['min'] ) && !isset( $options['hours'] ) )
{
    $whileAgo = $minsAgo;
    $whileSpan = 'Minutes';

    /** Optional debug output **/

    if( $troubleshoot && $scriptVerboseLevel >= 5 )
    {
        $cli->output( "Searching: Using minutes to search: " . $minsAgo . "\n");
    }
    $searchTimeStampInSeconds = time() - ( $minsAgo * 60 );
}
elseif( isset( $options['min'] ) && isset( $options['hours'] ) )
{
    $whileAgo = $hoursAgo . "' Hours and '" . $minsAgo;
    $whileSpan = 'Minutes';

    /** Optional debug output **/

    if( $troubleshoot && $scriptVerboseLevel >= 5 )
    {
        $cli->output( "Searching: Using hours and minutes to search: " . $hoursAgo . ' Hours and ' . $minsAgo . " Minutes\n");
    }
    $searchTimeStampInSeconds = time() - ( ( $hoursAgo * 3600 ) + ( $minsAgo * 60 ) );
}
else
{
    $whileAgo = $hoursAgo;
    $whileSpan = 'Hours';

    /** Optional debug output **/

    if( $troubleshoot && $scriptVerboseLevel >= 5 )
    {
        $cli->output( "Searching: Using hours to search: " . $hoursAgo . "\n");
    }
    $searchTimeStampInSeconds = time() - ( $hoursAgo * 3600 );
}

/** Fetch total files count from content tree **/

$totalFileCountParams = array( 'ClassFilterType' => 'include',
                               'ClassFilterArray' => array( $s3FileClassIdentifier ),
                               'AttributeFilter' => array( 'and', array( 'modified','>=', $searchTimeStampInSeconds ),
                                                                  array( "large_file/$s3FileRenameAttributeIdentifier",'!=', true ) ),
                               'Depth', 5,
                               'MainNodeOnly', true,
                               'IgnoreVisibility', true );

/** Optional debug output **/

if( $troubleshoot && $scriptVerboseLevel >= 5 )
{
    $cli->output( "S3 File object search params: \n");
    $cli->output( print_r( $totalFileCountParams ) );
}

/** Fetch total count for recently modified AWS S3 File content objects **/

$totalFileCount = eZContentObjectTreeNode::subTreeCountByNodeID( $totalFileCountParams, $parentNodeID );

/** Debug verbose output **/

if ( !$totalFileCount )
{
    $cli->error( "No S3 File objects found needing rename" );

    /** Call for display of execution time **/
    executionTimeDisplay( $srcStartTime, $cli );

    $script->shutdown( 3 );
}
elseif( $verbose && $totalFileCount > 0 )
{
    $cli->warning( "Found! Modified S3 File objects to be checked: " . $totalFileCount . "\n" );
}

/** Alert user of script process starting **/

if( $verbose )
{
    $cli->output( "Querying content tree for S3 large file objects\nwith parent node of '$parentNodeID' modified in the last\n'$whileAgo' $whileSpan ...\n" );
}

if( $verbose && !$force )
{
    $cli->warning( "You can run this script with --force parameter to skip this script startup delay and execute immediately.\n" );
    $cli->warning( "You have 10 seconds to stop the script execution before it starts (press Ctrl-C)." );

    sleep( 10 );
    $cli->output();
}

/** Setup script iteration details **/

$script->setIterationData( '.', '.' );
$script->resetIteration( $totalFileCount );

/** Iterate over nodes **/

while ( $offset < $totalFileCount )
{
    /** Fetch nodes under starting node in content tree **/

    $subTreeParams = array( 'ClassFilterType' => 'include',
                            'ClassFilterArray' => array( $s3FileClassIdentifier ),
                            'AttributeFilter' => array( 'and', array( 'modified','>=', $searchTimeStampInSeconds ),
                                                               array( "large_file/$s3FileRenameAttributeIdentifier",'!=', true ) ),
                            'Limit', $limit,
                            'Offset', $offset,
                            'SortBy', array( 'modified', false ),
                            'Depth', 5,
                            'MainNodeOnly', true,
                            'IgnoreVisibility', true );

    /** Optional debug output **/

    if( $troubleshoot && $scriptVerboseLevel >= 5 )
    {
        $cli->output( "S3 File object fetch params: \n");
        $cli->output( print_r( $subTreeParams ) );
    }

    /** Fetch nodes with limit and offset **/

    $subTree = eZContentObjectTreeNode::subTreeByNodeID( $subTreeParams, $parentNodeID );
    $subTreeCount = count( $subTree );

    /** Optional debug output **/

    if( $troubleshoot && $scriptVerboseLevel >= 4 )
    {
        $cli->output( "S3 File objects fetched: ". $subTreeCount ."\n" );

        if( $troubleshoot && $scriptVerboseLevel >= 6 )
        {
            $cli->output( print_r( $subTree ) );
        }
    }

    /** Iterate over nodes **/

    while ( list( $key, $childNode ) = each( $subTree ) )
    {
        $status = true;

        /** Fetch object details **/

        $object = $childNode->attribute( 'object' );
        $objectID = $object->attribute( 'id' );
        $objectCurrentVersion = $object->attribute( 'current_version' );
        $objectLastVersion = $objectCurrentVersion - 1;
        $objectDataMap = $object->dataMap();
        $objectPathName = $objectDataMap[ $s3FileAttributeIdentifier ]->content();

        $nodeID = $childNode->attribute('node_id');
        $nodeUrl = $childNode->attribute('url');

        /** Only iterate over versions of nodes greater than zero **/

        if( $objectLastVersion > 0 )
        {
            $objectPastDataMap = $object->fetchDataMap( $objectLastVersion );
            $objectPastPathName = $objectPastDataMap[ $s3FileAttributeIdentifier ]->content();

            /** Debug verbose output **/

            if( $troubleshoot && $scriptVerboseLevel >= 3 )
            {
                $cli->warning( "Found! S3 File object pending rename: " . $nodeUrl . ", NodeID " . $nodeID . "\n" );

                if( $troubleshoot && $scriptVerboseLevel >= 5 )
                {
                    $cli->output( "Past Version Path: " . $objectPastPathName . "" );
                    $cli->output( "New Path: " . $objectPathName . "\n" );
                }
                else
                {
                    $cli->output( "Attempting S3 File object copy. From: " . $objectPastPathName . " To: " . $objectPathName ."\n" );
                }
            }

            /** Only iterate over versions of nodes path attributes which do not match **/

            if ( $objectPathName != $objectPastPathName  )
            {
                /** Connect to aws s3 service **/

                $awsS3 = new AmazonS3();

                /** Copy object path from old version to new version **/

                $response = $awsS3->copy_object(
                            array(// Source
                                  'bucket' => $awsS3Bucket,
                                  'filename' => $objectPastPathName ),
                            array(// Destination
                                  'bucket' => $awsS3Bucket,
                                  'filename' => $objectPathName ) );

                /** Only delete old s3 file object if copy is a success **/

                if( $response->isOK() )
                {
                    /** Debug verbose output **/

                    if( $verbose )
                    {
                        $cli->output( "Success: Copy of AWS S3 File object path. New path: '$objectPathName'\n");
                    }

                    $delete = $awsS3->delete_object( $awsS3Bucket, $objectPastPathName );

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
                            $cli->output( "Success: Deletion of AWS S3 File object. Deleted Path: '$objectPastPathName'\n");
                        }

                        /** Only modify object if s3 file has been renamed Publish new object with renamed attribute checked **/

                        $updateParams = array();
                        $updateAttributeList = array( "$s3FileRenameAttributeIdentifier" => 1 );
                        $updateParams['attributes'] = $updateAttributeList;

                        $updateResult = eZContentFunctions::updateAndPublishObject( $object, $updateParams );

                        /** Debug verbose output **/

                        if( $verbose )
                        {
                            $cli->output( "Publishing new S3 File object version with $s3FileRenameAttributeIdentifier attribute checked\n");
                        }

                        /** Iterate cli script progress tracker **/
                        $script->iterate( $cli, $status );
                    }
                }
                else
                {
                    /** Debug verbose output **/

                    if( $verbose )
                    {
                        $errorMsgAlert = "Failure! S3 File object failed to be renamed: " . $nodeUrl . ", NodeID " . $nodeID . "\n";
                        $cli->error( $errorMsgAlert );
                        eZDebug::writeError( $errorMsgAlert );

                        $errorMsg = "S3 File object copy from: " . $objectPastPathName . " to " . $objectPathName .  " failed\n";
                        $cli->error( $errorMsg );
                        eZDebug::writeError( $errorMsg );

                        /** Catch error, 404 file not found **/

                        if( $response->status == 404 )
                        {
                            $warningMsg = "Reason: S3 File object copy failed because file path " . $objectPastPathName . " no longer exists\n";
                            $cli->warning( $warningMsg );
                            eZDebug::writeError( $warningMsg );
                        }
                    }
                    else
                    {
                        $errorMsgAlert = "Failure! S3 File object failed to be renamed: " . $nodeUrl . ", NodeID " . $nodeID . "\n";
                        eZDebug::writeError( $errorMsgAlert );

                        $errorMsg = "S3 File object copy from: " . $objectPastPathName . " to " . $objectPathName .  " failed\n";
                        eZDebug::writeError( $errorMsg );

                        /** Catch error, 404 file not found **/

                        if( $response->status == 404 )
                        {
                            $warningMsg = "Reason: S3 File object copy failed because file path " . $objectPastPathName . " no longer exists\n";
                            eZDebug::writeError( $warningMsg );
                        }
                    }

                    /** Optional debug output **/

                    if( $troubleshoot && $scriptVerboseLevel >= 3 )
                    {
                        $cli->output( "Copy S3 File object response: \n" . print_r( $response ) );
                    }

                }
            }
            else
            {
                /** Iterate cli script progress tracker **/
                $script->iterate( $cli, $status );
            }
        }
    }

    /** Iterate fetch function offset and continue **/
    $offset = $offset + $subTreeCount;
}

/** Display of execution time **/
function executionTimeDisplay( $srcStartTime, $cli )
{
    /** Add a stoping timing point tracking and calculating total script execution time **/
    $srcStopTime = microtime();
    $startTime = next( explode( " ", $srcStartTime ) ) + current( explode( " ", $srcStartTime ) );
    $stopTime = next( explode( " ", $srcStopTime ) ) + current( explode( " ", $srcStopTime ) );
    $executionTime = round( $stopTime - $startTime, 2 );

    /** Alert the user to how long the script execution took place **/
    $cli->output( "This script execution completed in " . $executionTime . " seconds" . ".\n" );
}

/** Call for display of execution time **/
executionTimeDisplay( $srcStartTime, $cli );

/** Shutdown script **/
$script->shutdown();

?>