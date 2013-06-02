<?php
/**
 * File containing the ezs3upload.php class
 *
 * @copyright Copyright (C) 1999 - 2014 Brookins Consulting. All rights reserved.
 * @copyright Copyright (C) 2013 - 2014 Think Creative. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2 (or later)
 * @version 0.4.2
 * @package ezs3upload
 */

require_once 'extension/ezs3upload/classes/S3.php';

class eZS3Upload
{
    private static $__accessKey; // AWS Access key
    private static $__secretKey; // AWS Secret key

    /**
    * Constructor - if you're not using the class statically
    *
    * @param string $accessKey Access key
    * @param string $secretKey Secret key
    * @param boolean $useSSL Enable SSL
    * @return void
    */
    public function __construct($accessKey = null, $secretKey = null, $useSSL = true) {
        if ($accessKey !== null && $secretKey !== null)
            self::setAuth($accessKey, $secretKey);
    }

    /**
    * Set AWS access key and secret key
    *
    * @param string $accessKey Access key
    * @param string $secretKey Secret key
    * @return void
    */
    public static function setAuth($accessKey, $secretKey) {
        self::$__accessKey = $accessKey;
        self::$__secretKey = $secretKey;
    }

    /**
    * Upload file to AWS S3
    *
    * @param string $file File path to upload
    * @param string $uri Uri to upload to
    * @param string $nodeID NodeID to store within file content object datatype attribute
    * @param string $awsBucket Amazon S3 Upload Bucket
    * @param string $awsFilePermissions Amazon S3 Uploaded file permissions
    * @param boolean $removetempfile Remove temporary files after upload
    * @param object &$cli CLI Script object
    * @param boolean $verbose Verbose mode switch
    * @return boolean true or false depending on success
    */
    public static function cliUpload( $file, $uri, $nodeID = false, $awsBucket, $awsFilePermissions, $removetempfile = false, $createNode = false, &$cli, &$script, $verbose = false )
    {
        $s3 = new S3( self::$__accessKey, self::$__secretKey );

        if ( $s3->putObject( S3::inputFile($file), $awsBucket, $uri, $awsFilePermissions ) ) {
            if( $verbose )
                $cli->output( "File $uri uploaded successfully to Amazon S3\n" );

                if( $removetempfile )
                    unlink( $file );
        } else {
            if( $verbose )
                $cli->output( "Failed to upload file! $uri\n" );

            if( $removetempfile )
                unlink( $file );

            return false;
        }

        if( is_numeric( $nodeID ) )
            self::cliStoreFileNameInAttribute( $uri, $nodeID, $cli, $script );

        if( !is_numeric( $nodeID ) && $createNode )
            self::cliStoreFileNameInNewFileObject( $uri, $cli, $script );

        return true;
    }

    /**
    * Upload file to AWS S3
    *
    * @param string $file File path to upload
    * @param string $uri Uri to upload to
    * @param string $nodeID NodeID to store within file content object datatype attribute
    * @param string $awsBucket Amazon S3 Upload Bucket
    * @param string $awsFilePermissions Amazon S3 Uploaded file permissions
    * @param boolean $removetempfile Remove temporary files after upload
    * @param boolean $verbose Verbose mode switch
    * @return boolean true or false depending on success
    */
    public static function upload( $file, $uri, $nodeID = false, $awsBucket, $awsFilePermissions, $removetempfile = false, $createNode = false, $verbose = false )
    {
        $s3 = new S3( self::$__accessKey, self::$__secretKey );

        if ( $s3->putObject( S3::inputFile($file), $awsBucket, $uri, $awsFilePermissions ) ) {
            if( $verbose )
                echo( "File $uri uploaded successfully to Amazon S3\n" );

                if( $removetempfile )
                    unlink( $file );
        } else {
            if( $verbose )
                echo( "Failed to upload file! $uri\n" );

                if( $removetempfile )
                    unlink( $file );

            return false;
        }

        if( is_numeric( $nodeID ) )
            self::storeFileNameInAttribute( $uri, $nodeID );

        if( !is_numeric( $nodeID ) && $createNode )
            self::storeFileNameInNewFileObject( $uri );

        return true;
    }

    /**
    * Store file path in content object attribute
    *
    * @param string $uri Uri to store in content object attribute
    * @param string $nodeID NodeID to store within file content object datatype attribute
    * @param object &$cli CLI Script object
    * @return boolean true or false depending on success
    */
    public static function cliStoreFileNameInAttribute( $uri, $nodeID, &$cli, &$script )
    {
        $node = eZContentObjectTreeNode::fetch( $nodeID );

        if ( !$node )
        {
            $cli->error( "No node with ID: $nodeID" );
            $script->shutdown( 3 );
        }

        $object = $node->object();
        $nodeDataMap = $node->dataMap();
        $nodeS3Attribute = $nodeDataMap['aws_s3_upload_client'];

        $params = array();
        $attributeList = array();
        $attributeList['aws_s3_upload_client'] = "$uri";
        $params['attributes'] = $attributeList;

        $operationResult = eZContentFunctions::updateAndPublishObject( $object, $params );

        $object->expireAllViewCache();
        eZContentCacheManager::clearObjectViewCache( $object->attribute('id') );

        if ( !$operationResult )
        {
            $cli->error( "Storage failed using NodeID $nodeID!" );
            $script->shutdown( 3 );
        }

        return true;
    }

    /**
    * Store file path in content object attribute
    *
    * @param string $uri Uri to store in content object attribute
    * @param string $nodeID NodeID to store within file content object datatype attribute
    * @param object &$cli CLI Script object
    * @return boolean true or false depending on success
    */
    public static function cliStoreFileNameInNewFileObject( $uri, &$cli, &$script )
    {
        $creatorID = 14;
        $classIdentifier = 's3_file';
        $parentNodeID = 2;

        /** Calculate file name, path, upload subdirectory **/
        $fileUriArray = array_reverse( explode( '/', $uri ) );
        $fileName = $fileUriArray[0];

        $params = array();
        $attributeList = array();
        $attributeList['name'] = "$fileName";
        $attributeList['aws_s3_upload_client'] = "$uri";
        $params['creator_id'] = $creatorID;
        $params['class_identifier'] = $classIdentifier;
        $params['parent_node_id'] = $parentNodeID;
        $params['attributes'] = $attributeList;

        $operationResult = eZContentFunctions::createAndPublishObject( $params );

        if ( !$operationResult )
        {
            $cli->error( "Storage failed!" );
            $script->shutdown( 3 );
        }

        return true;
    }

    /**
    * Store file path in content object attribute
    *
    * @param string $uri Uri to store in content object attribute
    * @param string $nodeID NodeID to store within file content object datatype attribute
    * @param object &$cli CLI Script object
    * @return boolean true or false depending on success
    */
    public static function storeFileNameInNewFileObject( $uri )
    {
        $creatorID = 14;
        $classIdentifier = 's3_file';
        $parentNodeID = 2;

        /** Calculate file name, path, upload subdirectory **/
        $fileUriArray = array_reverse( explode( '/', $uri ) );
        $fileName = $fileUriArray[0];

        $params = array();
        $attributeList = array();
        $attributeList['name'] = "$fileName";
        $attributeList['aws_s3_upload_client'] = "$uri";
        $params['creator_id'] = $creatorID;
        $params['class_identifier'] = $classIdentifier;
        $params['parent_node_id'] = $parentNodeID;
        $params['attributes'] = $attributeList;

        $operationResult = eZContentFunctions::createAndPublishObject( $params );

        if ( !$operationResult )
        {
            echo( "Storage failed!" );
            exit();
        }

        return true;
    }

    /**
    * Store file path in content object attribute
    *
    * @param string $uri Uri to store in content object attribute
    * @param string $nodeID NodeID to store within file content object datatype attribute
    * @return boolean true or false depending on success
    */
    public static function storeFileNameInAttribute( $uri, $nodeID )
    {
        $node = eZContentObjectTreeNode::fetch( $nodeID );

        if ( !$node )
        {
            echo("No node with ID: $nodeID" );
            exit();
        }

        $object = $node->object();
        $nodeDataMap = $node->dataMap();
        $nodeS3Attribute = $nodeDataMap['aws_s3_upload_client'];

        $params = array();
        $attributeList = array();
        $attributeList['aws_s3_upload_client'] = "$uri";
        $params['attributes'] = $attributeList;

        $operationResult = eZContentFunctions::updateAndPublishObject( $object, $params );

        $object->expireAllViewCache();
        eZContentCacheManager::clearObjectViewCache( $object->attribute('id') );

        if ( !$operationResult )
        {
            echo( "Storage failed using NodeID $nodeID!" );
            exit();
        }

        return true;
    }

    /**
    * Download file via curl to storage directory
    *
    * @param string $uri Uri to download
    * @param string $storagedir Path to store downloaded file
    * @return boolean true or false depending on success
    */
    public static function downloadFile( $url, $storagedir )
    {
        $fileUriArray = array_reverse( explode( '/', $url ) );
        $fileUri = $fileUriArray[0];
        $path = $storagedir . $fileUri;

        $fp = fopen($path, 'w');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FILE, $fp);

        $response = curl_exec ($ch);

        curl_close($ch);
        fclose($fp);

        if ( $response )
        {
            return true;
        }
        else
        {
            return false;
        }
    }
}
?>