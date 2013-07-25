<?php
//
// Definition of ezs3uploadTemplateFunctions
//
// Created on: <17-May-2013 23:15:12 bc>
//
// ## BEGIN COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
// SOFTWARE NAME: eZ S3 Upload Client
// SOFTWARE RELEASE: 0.2.7
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
 * Template operators for the datatype template policy and policy signature rendering
 */

class ezs3uploadTemplateFunctions
{
    function ezs3uploadTemplateFunctions()
    {
    }

    function operatorList()
    {
        return array( 'aws_s3_sigpolicydoc', 'aws_s3_policydoc64', 'is_mac_user' );
    }

    function namedParameterPerOperator()
    {
        return true;
    }

    function namedParameterList()
    {
        return array( 'aws_s3_sigpolicydoc' => array( 'policydoc' => array( 'type' => 'string',
                                                                            'required' => true,
                                                                            'default' => '' ) ),
                      'aws_s3_policydoc64' => array(),
                      'is_mac_user' => array() );

    }

    function modify( $tpl, $operatorName, $operatorParameters, $rootNamespace, $currentNamespace, &$operatorValue, $namedParameters )
    {
        switch ( $operatorName )
        {
	        case 'get_download_url':
            {
				$awsAccessKey = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'Key' );
				$awsSecretKey = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'SecretKey' );
				$awsBucket = eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'Bucket' );
				
				$s3 = new S3( $awsAccessKey, $awsSecretKey );
				
				$operatorValue = $s3->getAuthenticatedURL($awsBucket, $operatorValue, 6000);
				
            } break;
            case 'aws_s3_policydoc64':
            {
                $operatorValue = self::aws_s3_policydoc64();
            } break;
            case 'aws_s3_sigpolicydoc':
            {
                $operatorValue = self::aws_s3_sigpolicydoc( $namedParameters['policydoc'] );
            } break;
            case 'is_mac_user':
            {
              $operatorValue = (preg_match("/macintosh/",strtolower($_SERVER['HTTP_USER_AGENT'])) ? true : false);
            } break;
        }
    }

    /**
     * Check access to a specific module/function with limitation values.
     * @param string $module
     * @param string $function
     * @param array|null $limitations A hash of limitation keys and values
     * @return bool
     */
    public static function aws_s3_policydoc64()
    {
        // $maxFileSize = 50 * 1048576; // size in bytes, default 50MB
        $maxFileSize = 1024 * eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'FileSizeLimit' ); // size in bytes, default 1972.890625MB
        $expTime = time() + (1 * 60 * 60);
        $expTimeStr = gmdate('Y-m-d\TH:i:s\Z', $expTime);
        $policyDoc = '{"expiration": "' . $expTimeStr . '",
                       "conditions": [{"bucket": "' . eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'Bucket' ) . '"},
                                      ["starts-with", "$key", ""],
                                      {"acl": "public-read"},
                                      ["content-length-range", 0, '. $maxFileSize .'],
                                      {"success_action_status": "201"},
                                      ["starts-with", "$Filename", ""],
                                      ["starts-with", "$Content-Type", "image/"]
                                     ]}';

        $policyDoc = implode(explode('\r', $policyDoc));
        $policyDoc = implode(explode('\n', $policyDoc));
        $policyDoc64 = base64_encode($policyDoc);

        return $policyDoc64;
    }

    /**
     * Check access to a specific module/function with limitation values.
     * @param string $module
     * @param string $function
     * @param array|null $limitations A hash of limitation keys and values
     * @return bool
     */
    public static function aws_s3_sigpolicydoc( $policyDoc = false )
    {
        $sigPolicyDoc = base64_encode( hash_hmac("sha1", $policyDoc, eZINI::instance( 's3.ini' )->variable( 'S3Settings', 'SecretKey' ), TRUE) );

        return $sigPolicyDoc;
    }
}

?>