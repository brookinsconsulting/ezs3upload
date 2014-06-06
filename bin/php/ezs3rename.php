#!/usr/bin/env php
<?php
/**
 * File containing the ezs3rename.php bin script
 *
 * @copyright Copyright (C) 1999 - 2015 Brookins Consulting. All rights reserved.
 * @copyright Copyright (C) 2013 - 2015 Think Creative. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2 (or later)
 * @version 0.0.4
 * @package ezps3upload
 */

ini_set("memory_limit", -1);

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

$options = $script->getOptions( "[script-verbose:][parent-node:][hours:]",
                                "[node]",
                                array( 'parent-node' => 'Content Tree Node ID. Example: --parent-node=2',
                                       'days' => 'Number of days to search in reverse. Example: --days=5',
                                       'script-verbose' => 'Use this parameter to display verbose script output without disabling script iteration counting of images created or removed. Example: ' . "'--script-verbose'" . ' is an optional parameter which defaults to false'),
                                false,
                                array( 'user' => true ) );
$script->initialize();

/** Script default values **/

$limit = 100;
$offset = 0;

$fetchClassIdentifier = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'ClassIdentifier' );
$awsS3Bucket = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'Bucket' );
$attributeIdentifier = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'AttributeIdentifier' );
$renameAtributeIdentifier = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'RenameAttributeIdentifier' );

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

if( $verbose )
{
    $cli->output( "Total File Object Count: " . $totalFileCount . "\n" );
}

if ( !$totalFileCount )
{
    $cli->error( "No nodes to rename with ParentNodeID: $parentNodeID" );
    $script->shutdown( 3 );
}

/** Alert user of script process starting **/

$cli->output( "Searching through content subtree from node $parentNodeID to find S3 large file objects to be processed ...\n" );

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

    if( $verbose )
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

            if( $verbose )
            {
                $cli->output( "Current Path Name: " . $nodePathName . "\n" );
                $cli->output( "Past Path Name: " . $nodePastPathName . "\n\n" );
            }

            /** Only iterate over versions of nodes path attributes which do not match **/

            if ( $nodePathName != $nodePastPathName  )
            {
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

                if( $verbose )
                {
                    $cli->output( "Copy Object Responce: ". print_r( $response ) . "\n");
                }

                /** Only delete old s3 file object if copy is a success **/

                if( $response->isOK() )
                {
                    if( $verbose )
                    {
                        $cli->output( "Removing old version file path name on AWS S3:'$nodePastPathName'\n");
                    }

                    $delete = $awsS3->delete_object( $awsS3Bucket, $nodePastPathName );

                    if( $verbose )
                    {
                        $cli->output( "Delete Object Responce: ". print_r( $delete ) . "\n");
                    }

                    if( $delete->isOK() )
                    {
                        if( $verbose )
                        {
                            $cli->output( "Delete Object on AWS S3 Successfull.\n");
                        }

                        /** Only modify object if s3 file has been renamed Publish new object with renamed attribute checked **/

                        $params = array();
                        $attributeList = array( "$renameAtributeIdentifier" => 1 );
                        $params['attributes'] = $attributeList;

                        $result = eZContentFunctions::updateAndPublishObject( $object, $params );

                        if( $verbose )
                        {
                            $cli->output( "Publishing new large_file object version with $renameAtributeIdentifier attribute checked\n");
                        }
                    }
                }
            }
        }

        /** Iterate cli script progress tracker **/
        $script->iterate( $cli, $status );
    }

    /** Iterate fetch function offset and continue **/
    $offset = $offset + count( $subTree );
}

/** Shutdown script **/
$script->shutdown();

?>