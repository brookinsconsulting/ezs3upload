<?php
//
// Definition of eZS3UploadType class
//
// Created on: <17-May-2013 23:15:12 bc>
//
// ## BEGIN COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
// SOFTWARE NAME: eZ S3 Upload Client
// SOFTWARE RELEASE: 0.4.2
// COPYRIGHT NOTICE: Copyright (C) 1999 - 2014 Brookins Consulting and ThinkCreative
// SOFTWARE LICENSE: GNU General Public License v2.0 (or later)
// NOTICE: >
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of version 2.0  of the GNU General
//   Public License as published by the Free Software Foundation.
//
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//
//   You should have received a copy of version 2.0 of the GNU General
//   Public License along with this program; if not, write to the Free
//   Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
//   MA 02110-1301, USA.
//
//
// ## END COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
//

/**
 * Datatype for ezs3uploadType
 */

class ezs3uploadType extends eZDataType
{
    const DATA_TYPE_STRING = 'ezs3upload';
    const DEFAULT_STRING_FIELD = "data_text1";
    const DEFAULT_STRING_VARIABLE = "_ezstring_default_value_";

    /*!
     Construction of the class, note that the second parameter in eZDataType
     is the actual name showed in the datatype dropdown list.
    */
    function __construct()
    {
        parent::__construct( self::DATA_TYPE_STRING, ezpI18n::tr( 'kernel/classes/datatypes', 'Amazon S3 Upload Client', 'Datatype name' ),
                             array( 'serialize_supported' => true,
                                    'object_serialize_map' => array( 'data_text' => 'text' ) ) );
    }

    /*!
     Sets the default value.
    */
    function initializeObjectAttribute( $contentObjectAttribute, $currentVersion, $originalContentObjectAttribute )
    {
        if ( $currentVersion != false )
        {
//             $contentObjectAttributeID = $contentObjectAttribute->attribute( "id" );
//             $currentObjectAttribute = eZContentObjectAttribute::fetch( $contentObjectAttributeID,
//                                                                         $currentVersion );
            $dataText = $originalContentObjectAttribute->attribute( "data_text" );
            $contentObjectAttribute->setAttribute( "data_text", $dataText );
        }
        else
        {
            $contentClassAttribute = $contentObjectAttribute->contentClassAttribute();
            $default = $contentClassAttribute->attribute( 'data_text1' );
            if ( $default !== '' && $default !== NULL )
            {
                $contentObjectAttribute->setAttribute( 'data_text', $default );
            }
        }
    }

    /*!
     Fetches the http post variables for collected information
    */
    function fetchCollectionAttributeHTTPInput( $collection, $collectionAttribute, $http, $base, $contentObjectAttribute )
    {
        if ( $http->hasPostVariable( $base . "_ezstring_data_text_" . $contentObjectAttribute->attribute( "id" ) ) )
        {
            $dataText = $http->postVariable( $base . "_ezstring_data_text_" . $contentObjectAttribute->attribute( "id" ) );
            $collectionAttribute->setAttribute( 'data_text', $dataText );
            return true;
        }
        return false;
    }

    /*!
     Simple string insertion is supported.
    */
    function isSimpleStringInsertionSupported()
    {
        return true;
    }

    /*!
     Inserts the string \a $string in the \c 'data_text' database field.
    */
    function insertSimpleString( $object, $objectVersion, $objectLanguage,
                                 $objectAttribute, $string,
                                 &$result )
    {
        $result = array( 'errors' => array(),
                         'require_storage' => true );
        $objectAttribute->setContent( $string );
        $objectAttribute->setAttribute( 'data_text', $string );
        return true;
    }

    /*!
      Validates the input and returns true if the input was
      valid for this datatype.
    */
    function validateObjectAttributeHTTPInput( $http, $base, $objectAttribute )
    {
        return eZInputValidator::STATE_ACCEPTED;
    }

    /*!
    */
    function fetchObjectAttributeHTTPInput( $http, $base, $contentObjectAttribute )
    {
        if ( $http->hasPostVariable( $base . '_ezstring_data_text_' . $contentObjectAttribute->attribute( 'id' ) ) )
        {
            $data = $http->postVariable( $base . '_ezstring_data_text_' . $contentObjectAttribute->attribute( 'id' ) );
            $contentObjectAttribute->setAttribute( 'data_text', $data );
            $this->storeFilesize($contentObjectAttribute, $data);

            /* $contentObjectAttribute->setContent( $data ); */
            return true;
        }
        return false;
    }

    /*!
     Store the content. Since the content has been stored in function
     fetchObjectAttributeHTTPInput(), this function is with empty code.
    */
    function storeObjectAttribute( $objectattribute )
    {
    }

    /*!
     Returns the meta data used for storing search indices.
    */
    function metaData( $contentObjectAttribute )
    {
        return $contentObjectAttribute->attribute( 'data_text' );
    }
    /*!
     \return string representation of an contentobjectattribute data for simplified export

    */
    function toString( $contentObjectAttribute )
    {
        return $contentObjectAttribute->attribute( 'data_text' );
    }

    function fromString( $contentObjectAttribute, $string )
    {
        return $contentObjectAttribute->setAttribute( 'data_text', $string );
    }

    /*!
     Returns the text.
    */
    function title( $objectAttribute, $name = null)
    {
        return $contentObjectAttribute->attribute( 'data_text' );
    }

    function isIndexable()
    {
        return true;
    }

    function isInformationCollector()
    {
        // datatype missing basic ezstring info colector bits in datatype
        return false;
    }

    function sortKey( $contentObjectAttribute )
    {
        $trans = eZCharTransform::instance();
        return $trans->transformByGroup( $contentObjectAttribute->attribute( 'data_text' ), 'lowercase' );
    }

    function sortKeyType()
    {
        return 'string';
    }

    function hasObjectAttributeContent( $contentObjectAttribute )
    {
        return trim( $contentObjectAttribute->attribute( 'data_text' ) ) != '';
    }

    function diff( $old, $new, $options = false )
    {
        $diff = new eZDiff();
        $diff->setDiffEngineType( $diff->engineType( 'text' ) );
        $diff->initDiffEngine();
        $diffObject = $diff->diff( $old->content(), $new->content() );
        return $diffObject;
    }

    function supportsBatchInitializeObjectAttribute()
    {
        return true;
    }

    function batchInitializeObjectAttributeData( $classAttribute )
    {
        $default = $classAttribute->attribute( 'data_text1' );
        if ( $default !== '' && $default !== NULL )
        {
            $db = eZDB::instance();
            $default = "'" . $db->escapeString( $default ) . "'";
            $trans = eZCharTransform::instance();
            $lowerCasedDefault = $trans->transformByGroup( $default, 'lowercase' );
            return array( 'data_text' => $default, 'sort_key_string' => $lowerCasedDefault );
        }

        return array();
    }

    /*!
     Returns the content.
    */
    function objectAttributeContent( $contentObjectAttribute )
    {
        $data = $contentObjectAttribute->attribute( 'data_text' );

        // check if data_int(filesize) is set
        if (!$contentObjectAttribute->attribute( 'data_int' ))
            $this->storeFilesize(
                $contentObjectAttribute,
                $data
            );

        return $data;
    }

    function objectDisplayInformation( $objectAttribute, $mergeInfo = false )
    {
        $info = array( 'edit' => array( 'grouped_input' => true ),
                       'collection' => array( 'grouped_input' => true ) );
        return eZDataType::objectDisplayInformation( $objectAttribute, $info );
    }

    /*!
     Store filesize
    */
    function storeFilesize( $contentObjectAttribute, $s3_uri ) {
        require_once 'extension/ezs3upload/classes/S3.php';
        $awsAccessKey = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'Key' );
        $awsSecretKey = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'SecretKey' );
        $awsBucket = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'Bucket' );
        $s3 = new S3( $awsAccessKey, $awsSecretKey );
        $filedata = $s3->getObjectInfo($awsBucket, $s3_uri);
        if (!$filedata || !array_key_exists("size", $filedata)) return false;
        return $contentObjectAttribute->setAttribute( 'data_int', intval($filedata['size']));
    }

}

eZDataType::register( ezs3uploadType::DATA_TYPE_STRING, 'ezs3uploadType' );

?>