{* $attribute.data_text|wash( xhtml ) *}
{def $path_array=$attribute.content|explode( '/' )
     $array_length=$path_array|count
     $file_path=$path_array|remove( $array_length|sub( 1 ) )|implode( '/' )
     $file=$path_array|extract( $array_length|sub( 1 ), 1 )[0]}
{if $attribute.content}<a href={concat( 'http://', ezini( 'S3Settings', 'Bucket', 's3.ini' ),'.s3.amazonaws.com/', $file_path, '/', $file|urlencode )|ezurl}>{$attribute.content|wash( xhtml )}</a>{/if}