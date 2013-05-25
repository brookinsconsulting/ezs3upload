<?php
/**
 * Template autoload definition for eZ S3 Upload Client
 *
 * @copyright Copyright (C) 1999 - 2014 Brookins Consulting. All rights reserved.
 * @copyright Copyright (C) 2013 - 2014 Think Creative. All rights reserved.
 * @license http://ez.no/licenses/gnu_gpl GNU GPLv2 (or later)
 */

/**
 * Look in the operator files for documentation on use and parameters definition.
 *
 * @var array $eZTemplateOperatorArray
 */

$eZTemplateOperatorArray = array();
$eZTemplateOperatorArray[] = array( 'script' => 'extension/ezs3upload/autoloads/ezs3uploadtemplatefunctions.php',
                                    'class' => 'ezs3uploadTemplateFunctions',
                                    'operator_names' => array( 'aws_s3_sigpolicydoc',
                                                               'aws_s3_policydoc64',
                                                               'is_mac_user' ) );

?>