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
$script = eZScript::instance( array( 'description' => ( "eZ Publish AWS S3 Rename Script\n" .
                                                        "\n" .
                                                        "ezs3rename.php --parent-node=2 --hours=1" ),
                                     'use-session' => false,
                                     'use-modules' => true,
                                     'use-extensions' => true,
                                     'user' => true ) );

$script->startup();

$options = $script->getOptions( "[script-verbose;][script-verbose-level;][parent-node:][hours:]",
                                "[node]",
                                array( 'parent-node' => 'Content Tree Node ID. Example: --parent-node=2',
                                       'hours' => 'Number of hours to search in reverse. Example: --hours=5',
                                       'script-verbose' => 'Use this parameter to display verbose script output without disabling script iteration counting of images created or removed. Example: ' . "'--script-verbose'" . ' is an optional parameter which defaults to false',
                                       'script-verbose-level' => 'Use only with ' . "'--script-verbose'" . ' parameter to see more of execution internals. Example: ' . "'--script-verbose-level=3'" . ' is an optional parameter which defaults to 1'),
                                false,
                                array( 'user' => true ) );
$script->initialize();

/** Script default values **/

$limit = 100;
$offset = 0;
$adminUserID = 14;

$fetchClassIdentifier = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'ClassIdentifier' );
$attributeIdentifier = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'AttributeIdentifier' );
$renameAtributeIdentifier = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'RenameAttributeIdentifier' );
$awsS3Bucket = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'Bucket' );

/** Login script to run as admin user  This is required to see past content tree permissions, sections and other limitations **/

$currentuser = eZUser::currentUser();
$currentuser->logoutCurrent();
$user = eZUser::fetch( $adminUserID );
$user->loginCurrent();

/** Test for required script arguments **/

if ( $options['parent-node'] )
{
    $parentNodeID = $options['parent-node'];
}
else
{
    $cli->error( 'Parent NodeID is required. Specify a content treee node in the command' );
    $script->shutdown( 1 );
}

if ( !is_numeric( $parentNodeID ) )
{
    $cli->error( 'Please specify a numeric node ID' );
    $script->shutdown( 2 );
}

if ( $options['hours'] )
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

$verbose = isset( $options['script-verbose'] ) ? true : false;

$scriptVerboseLevel = isset( $options['script-verbose-level'] ) ? $options['script-verbose-level'] : 1;

$troubleshoot = ( isset( $options['script-verbose-level'] ) && $options['script-verbose-level'] > 0 ) ? true : false;

/** Modified time stamp to search with **/

$modifiedTimeStamp = time() - ( $hoursAgo * 3600 );

/** Fetch total files count from content tree **/

$subTreeCountByNodeIDParams = array( 'ClassFilterType' => 'include',
                                     'ClassFilterArray' => array( $fetchClassIdentifier ),
                                     'AttributeFilter' => array( 'and', array( 'modified','>=', $modifiedTimeStamp ),
                                                                        array( "large_file/$renameAtributeIdentifier",'!=', true ) ),
                                     'Depth', 4,
                                     'MainNodeOnly', true,
                                     'IgnoreVisibility', true );

$totalFileCount = eZContentObjectTreeNode::subTreeCountByNodeID( $subTreeCountByNodeIDParams, $parentNodeID );

/** Debug verbose output **/

if( $verbose )
{
    $cli->output( "Found! Total File Objects To Be Renamed: " . $totalFileCount . "\n" );
}

if ( !$totalFileCount )
{
    $cli->error( "No nodes to rename with ParentNodeID: $parentNodeID" );
    $script->shutdown( 3 );
}

/** Alert user of script process starting **/

$cli->output( "Searching content tree from starting node '$parentNodeID' to find S3 large file objects to be processed ...\n" );

/** Setup script iteration details **/

$script->setIterationData( '.', '.' );
$script->resetIteration( $totalFileCount );

/** Iterate over nodes **/

while ( $offset < $totalFileCount )
{
    /** Fetch nodes under starting node in content tree **/
    $subTreeByNodeIDParams = array( 'ClassFilterType' => 'include',
                                    'ClassFilterArray' => array( $fetchClassIdentifier ),
                                    'AttributeFilter' => array( 'and', array( 'modified','>=', $modifiedTimeStamp ),
                                                                       array( "large_file/$renameAtributeIdentifier",'!=', true ) ),
                                    'Limit', $limit,
                                    'Offset', $offset,
                                    'SortBy', array( 'modified', false ),
                                    'Depth', 4,
                                    'MainNodeOnly', true,
                                    'IgnoreVisibility', true );

    $subTree = eZContentObjectTreeNode::subTreeByNodeID( $subTreeByNodeIDParams, $parentNodeID );

    /** Optional debug output **/

    if( $troubleshoot && $scriptVerboseLevel >= 3 )
    {
        $cli->output( "Subtree Count: ". count( $subTree ) ."\n" );
        $cli->output( print_r( $subTree ) );
    }

    /** Iterate over nodes **/

    while ( list( $key, $childNode ) = each( $subTree ) )
    {
        $status = true;

        /** Fetch object details **/

        $object = $childNode->attribute( 'object' );
        $objectID = $object->attribute( 'id' );
        $classIdentifier = $object->attribute( 'class_identifier' );
        $nodeDataMap = $object->dataMap();

        $childNodeID = $childNode->attribute('node_id');
        $nodeFullName = $childNode->attribute('name');

        $nodeCurrentVersion = $object->attribute( 'current_version' );
        $nodeLastVersion = $nodeCurrentVersion - 1;

        /** Only iterate over versions of nodes greater than zero **/

        if( $nodeLastVersion > 0 )
        {
            $nodePastDataMap = $object->fetchDataMap( $nodeLastVersion );

            $nodePathName = $nodeDataMap[ $attributeIdentifier ]->content();
            $nodePastPathName = $nodePastDataMap[ $attributeIdentifier ]->content();

            /** Debug verbose output **/

            if( $verbose )
            {
                $cli->output( "Found! S3 File needing to be renamed: " . $object->name() . "\n" );
                $cli->output( "Current Path Name: " . $nodePathName . "\n" );
                $cli->output( "Past Path Name: " . $nodePastPathName . "\n\n" );
            }

            /** Only iterate over versions of nodes path attributes which do not match **/

            if ( $nodePathName != $nodePastPathName  )
            {
                /** Connect to aws s3 service **/

                $awsS3 = new AmazonS3();

                /** Copy object path from old version to new version **/

                $response = $awsS3->copy_object(
                            array(// Source
                                  'bucket' => $awsS3Bucket,
                                  'filename' => $nodePastPathName ),
                            array(// Destination
                                  'bucket' => $awsS3Bucket,
                                  'filename' => $nodePathName )
                );

                /** Only delete old s3 file object if copy is a success **/

                if( $response->isOK() )
                {
                    /** Debug verbose output **/

                    if( $verbose )
                    {
                        $cli->output( "Copy file path name on AWS S3 successfull! New path: '$nodePathName'\n");
                    }

                    $delete = $awsS3->delete_object( $awsS3Bucket, $nodePastPathName );

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
                            $cli->output( "Delete Object on AWS S3 Successfull. '$nodePastPathName'\n");
                        }

                        /** Only modify object if s3 file has been renamed Publish new object with renamed attribute checked **/

                        $params = array();
                        $attributeList = array( "$renameAtributeIdentifier" => 1 );
                        $params['attributes'] = $attributeList;

                        $result = eZContentFunctions::updateAndPublishObject( $object, $params );

                        /** Debug verbose output **/

                        if( $verbose )
                        {
                            $cli->output( "Publishing new large_file object version with $renameAtributeIdentifier attribute checked\n");
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
                        $cli->output( "Failure! S3 File failed to be renamed: " . $object->name() . "\n" );
                        $cli->output( "Current Path Name: " . $nodePathName . "\n" );
                        $cli->output( "Past Path Name: " . $nodePastPathName . "\n\n" );
                    }

                    /** Optional debug output **/

                    if( $troubleshoot && $scriptVerboseLevel >= 3 )
                    {
                        $cli->output( "Copy Object Responce: \n" . print_r( $response ) );
                    }

                }
            }
        }
    }

    /** Iterate fetch function offset and continue **/
    $offset = $offset + count( $subTree );
}

/** Shutdown script **/
$script->shutdown();

?>