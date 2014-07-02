<?php
/**
 * File containing the eZS3UploadRenameType class.
 *
 * @copyright Copyright (C) 1999 - 2015 Brookins Consulting. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2 (or any later version)
 * @version //autogentag//
 * @package bcimagealias
 */

class eZS3UploadRenameType extends eZWorkflowEventType
{
    /**
     * Workflow Event Type String
     */
    const WORKFLOW_TYPE_STRING = "ezs3uploadrename";

    /**
     * Default constructor
     */
    function eZS3UploadRenameType()
    {
        /**
         * Define workflow event type. This assigns the name of the workflow event within the eZ Publish administration module views
         */
        $this->eZWorkflowEventType( self::WORKFLOW_TYPE_STRING, "eZS3UploadRename - Rename AWS S3 Files" );

        /**
         * Define trigger type. This workflow event requires the following to 'content, after, publish'
         */
        $this->setTriggerTypes( array( 'content' => array( 'publish' => array( 'after' ) ) ) );
    }

    /**
     * Workflow Event Type execute method
     */
    function execute( $process, $event )
    {
        /**
         * Fetch workflow process parameters
         */
        $parameters = $process->attribute( 'parameter_list' );
        $objectID = $parameters['object_id'];
        $version = $parameters['version'];

        /**
         * Fetch workflow event execution settings
         */
        $s3FileClassIdentifier = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'ClassIdentifier' );
        $s3FileAttributeIdentifier = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'AttributeIdentifier' );
        $s3FileRenameAttributeIdentifier = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'RenameAttributeIdentifier' );
        $awsS3Bucket = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'Bucket' );

        $verbose = false;
        $troubleshoot = false;
        $workflowVerboseLevel = 0;

        /**
         * Fetch content object
         */
        $object = eZContentObject::fetch( $objectID, $version );
        $objectCurrentVersion = $object->attribute( 'current_version' );
        $objectLastVersion = $objectCurrentVersion - 1;
        $objectDataMap = $object->dataMap();
        $objectPathName = $objectDataMap[ $s3FileAttributeIdentifier ]->content();

        /**
         * Test for the rare chance we would not have been given an object. Terminate workflow event execution after writing debug error report
         */
        if( !$object )
        {
            eZDebugSetting::writeError( 'extension-ezs3upload-rename-file-on-aws-s3-workflow-on-non-object',
                                        $objectID,
                                        'eZS3UploadRenameType::execute' );
            return eZWorkflowEventType::STATUS_WORKFLOW_CANCELLED;
        }

        /** Only iterate over versions of nodes greater than zero **/

        if( $objectLastVersion > 0 )
        {
            $objectPastDataMap = $object->fetchDataMap( $objectLastVersion );
            $objectPastPathName = $objectPastDataMap[ $s3FileAttributeIdentifier ]->content();

            /** Debug verbose output **/

            if( $troubleshoot && $workflowVerboseLevel >= 3 )
            {
                print_r( "Found! S3 File object pending rename: " . $nodeUrl . ", NodeID " . $nodeID . "\n" );

                if( $troubleshoot && $workflowVerboseLevel >= 5 )
                {
                    print_r( "Past Version Path: " . $objectPastPathName . "" );
                    print_r( "New Path: " . $objectPathName . "\n" );
                }
                else
                {
                    print_r( "Attempting S3 File object copy. From: " . $objectPastPathName . " To: " . $objectPathName ."\n" );
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
                        print_r( "Success: Copy of AWS S3 File object path. New path: '$objectPathName'\n");
                    }

                    $delete = $awsS3->delete_object( $awsS3Bucket, $objectPastPathName );

                    /** Optional debug output **/

                    if( $troubleshoot && $workflowVerboseLevel >= 3 )
                    {
                        print_r( "Delete Object Responce: \n". print_r( $delete ) . "\n");
                    }

                    if( $delete->isOK() )
                    {
                        /** Debug verbose output **/

                        if( $verbose )
                        {
                            print_r( "Success: Deletion of AWS S3 File object. Deleted Path: '$objectPastPathName'\n");
                        }

                        /** Only modify object if s3 file has been renamed Publish new object with renamed attribute checked **/

                        $updateParams = array();
                        $updateAttributeList = array( "$s3FileRenameAttributeIdentifier" => 1 );
                        $updateParams['attributes'] = $updateAttributeList;

                        $updateResult = eZContentFunctions::updateAndPublishObject( $object, $updateParams );

                        /** Debug verbose output **/

                        if( $verbose )
                        {
                            print_r( "Publishing new S3 File object version with $s3FileRenameAttributeIdentifier attribute checked\n");
                        }

                        /** Iterate workflow progress tracker **/
                        $result = true;
                    }
                }
                else
                {
                    /** Debug verbose output **/

                    if( $verbose )
                    {
                        $errorMsgAlert = "Failure! S3 File object failed to be renamed: " . $nodeUrl . ", NodeID " . $nodeID . "\n";
                        print_r( $errorMsgAlert );
                        eZDebug::writeError( $errorMsgAlert );

                        $errorMsg = "S3 File object copy from: " . $objectPastPathName . " to " . $objectPathName .  " failed\n";
                        print_r( $errorMsg );
                        eZDebug::writeError( $errorMsg );

                        /** Catch error, 404 file not found **/

                        if( $response->status == 404 )
                        {
                            $warningMsg = "Reason: S3 File object copy failed because file path " . $objectPastPathName . " no longer exists\n";
                            print_r( $warningMsg );
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

                    if( $troubleshoot && $workflowVerboseLevel >= 3 )
                    {
                        print_r( "Copy S3 File object response: \n" . print_r( $response ) );
                    }
                }
            }
        }

        /**
        * Optional debug output
        */
        if( $verbose && $troubleshoot )
        {
            die("\nTroubleshooting exection option enabled: Ending workflow event just before the end of it's execution to allow you to read related output.\n\n\n");
        }

        /**
         * Test result for failure to create image aliases. Non-fatal workflow event execution result. Write debug error report just in case this is a problem
         */
        if( $result == false )
        {
            eZDebugSetting::writeError( 'extension-bcimagealias-create-image-alias-variations-workflow-object-failure-to-create',
                                        $objectID,
                                        'eZS3UploadRenameType::execute' );
        }

        /**
         * Return default succesful workflow event status code, by default, regardless of results of execution, always.
         * Image alias image variation image files may not always need to be created. Also returning any other status
         * will result in problems with the succesfull and normal completion of the workflow event process
         */
        return eZWorkflowType::STATUS_ACCEPTED;
    }
}

/**
 * Register workflow event type class eZS3UploadRenameType
 */
eZWorkflowEventType::registerEventType( eZS3UploadRenameType::WORKFLOW_TYPE_STRING, "eZS3UploadRenameType" );

?>
