#!/usr/bin/env php
<?php

error_reporting( E_ALL );

// Mac OS stores timestamps as offsets from 2001-01-01 00:00:00.
// gmmktime( 0, 0, 0, 1, 1, 2001 ) = 978307200
define( 'MAC_TIMESTAMP_EPOCH', 978307200 );

/*
 * Parse the command line options, set reasonable defaults, and validate them.
 */
$cli_options = getopt( "l::o::js:e:ut:", array( 'library::', 'output-dir::', 'jpegrescan', 'start_date:', 'end_date:', 'update_site', 'timezone:' ) );

if ( isset( $cli_options['l'] ) ) {
	$cli_options['library'] = $cli_options['l'];
}

if ( isset( $cli_options['o'] ) ) {
	$cli_options['output-dir'] = $cli_options['o'];
}

if ( isset( $cli_options['j'] ) || isset( $cli_options['jpegrescan'] ) ) {
	$cli_options['jpegrescan'] = true;
}

if ( isset( $cli_options['s'] ) ) {
	$cli_options['start_date'] = $cli_options['s'];
}

if ( isset( $cli_options['t'] ) ) {
	$cli_options['timezone'] = $cli_options['t'];
}

if ( isset( $cli_options['start_date'] ) ) {
	$cli_options['start_date'] = date( 'Y-m-d', strtotime( $cli_options['start_date'] ) );
}

if ( isset( $cli_options['end_date'] ) ) {
	$cli_options['end_date'] = date( 'Y-m-d', strtotime( $cli_options['end_date'] ) );
}

if ( ! isset( $cli_options['end_date'] ) || ! $cli_options['end_date'] ) {
	$cli_options['end_date'] = false;
}

if ( ! isset( $cli_options['start_date'] ) || ! $cli_options['start_date'] ) {
	$cli_options['start_date'] = false;
}

if ( isset( $cli_options['u'] ) ) {
	$cli_options['update_site'] = true;
}

if ( isset( $cli_options['update_site'] ) ) {
	$cli_options['update_site'] = true;
}
else {
	$cli_options['update_site'] = false;
}

if ( ! isset( $cli_options['timezone'] ) ) {
	$cli_options['timezone'] = 'America/Los_Angeles';
}

try {
	new DateTimeZone( $cli_options['timezone'] );
} catch ( Exception $e ) {
	file_put_contents('php://stderr', "Invalid timezone identifier: " . $cli_options['timezone'] . "\n" );
	die;
}

if ( empty( $cli_options['library'] ) || empty( $cli_options['output-dir'] ) ) {
	file_put_contents('php://stderr', "Usage: ./photos-disc-export.php --library=/path/to/photo/library --output-dir=/path/for/exported/files [--jpegrescan --start_date=1950-01-01 --end_date=1955-01-01]\n" );
	die;
}

/*
 * Allow ~ to be used in the path options.
 */
$cli_options['output-dir'] = preg_replace( '/^~/', $_SERVER['HOME'], $cli_options['output-dir'] );

if ( ! is_array( $cli_options['library'] ) ) {
	$cli_options['library'] = array( $cli_options['library'] );
}

foreach ( $cli_options['library'] as $idx => $library ) {
	$cli_options['library'][ $idx ] = preg_replace( '/^~/', $_SERVER['HOME'], $library );
}

$cli_options['library'] = array_unique( $cli_options['library'] );

foreach ( $cli_options['library'] as $idx => $library ) {
	if ( ! file_exists( $library ) ) {
		file_put_contents('php://stderr', "Error: Library does not exist (" . $library . ")\n" );
		die;
	}

	// Ensure the paths end with a slash.
	$cli_options['library'][$idx] = rtrim( $library, '/' ) . '/';
}

// Ensure the output dir path ends with a slash.
$cli_options['output-dir'] = rtrim( $cli_options['output-dir'], '/' ) . '/';

// Don't allow an export to a directory that exists.
if ( ! file_exists( $cli_options['output-dir'] ) ) {
	if ( ! mkdir( $cli_options['output-dir'], 0777, true ) ) {
		file_put_contents('php://stderr', "Error: Could not create directory: " . $cli_options['output-dir'] . "\n" );
		die;
	}
}
else {
	file_put_contents( 'php://stderr', "Error: output directory already exists: " . $cli_options['output-dir'] . "\n" );
	file_put_contents( 'php://stderr', "       Out of an abundance of caution, this program will not output to an existing directory.\n" );
	die;
}

/*
 * Begin exporting.
 */

echo "Copying website structure...\n";

// Copy over the HTML/JS/CSS for the website.
shell_exec( "cp -r " . rtrim( __DIR__, '/' ) . '/' . "site/* " . escapeshellarg( $cli_options['output-dir'] ) );

if ( $cli_options['update_site'] ) {
	// The program was run just to copy over the site structure, probably by me while developing.
	// This option is so secret, it's not even documented in the README!
	exit;
}

$original_export_path = $cli_options['output-dir'];
$cli_options['output-dir'] .= 'photos/';

if ( ! file_exists( $cli_options['output-dir'] ) ) {
	mkdir( $cli_options['output-dir'] );
}

if ( ! file_exists( $cli_options['output-dir'] . 'thumbnails/' ) ) {
	mkdir( $cli_options['output-dir'] . "thumbnails/" );
}

$json_events = array();
$json_photos = array();
$json_faces = array();

$folders = array();

$timezone = new \DateTimeZone( $cli_options['timezone'] );
$time_right_now = new \DateTime( 'now', $timezone );
$timezone_offset = $timezone->getOffset( $time_right_now );

foreach ( $cli_options['library'] as $library ) {
	echo "Processing " . $library . "...\n";

	$db_file_path = $library . "database/Photos.sqlite";

	if ( ! file_exists( $db_file_path ) ) {
		file_put_contents( 'php://stderr', "Error: Database file does not exist in " . $library_path . "\n" );
		die;
	}

	$db = new SQLite3( $db_file_path, SQLITE3_OPEN_READONLY );

	$photos = $db->query( "SELECT * FROM ZGENERICASSET" );

	while ( $row = $photos->fetchArray( SQLITE3_ASSOC ) ) {
		// @todo What does ZADJUSTMENTTIMESTAMP do?

		$file_type = 'photo';

		if ( $row['ZKIND'] == '1' ) {
			$file_type = 'video';
		}

		$subdirectory = $row['ZDIRECTORY'];
		$filename = $row['ZFILENAME'];

		$photo_path = $library . "originals/" . $subdirectory . "/" . $filename;

		$photo_idx = count( $json_photos ) + 1;

		if ( file_exists( $photo_path ) ) {
			$photo_date_created = MAC_TIMESTAMP_EPOCH + $row['ZDATECREATED'];

			// If there's a date in the EXIF data, use that.
			$exif = @exif_read_data( $photo_path, "EXIF" );
			
			if ( $exif && isset( $exif['DateTimeOriginal'] ) ) {
				$localPhotoTimestamp = strtotime( $exif['DateTimeOriginal'] );
				
				if ( false !== $localPhotoTimestamp && $localPhotoTimestamp <= time() ) {
					$photo_date_created = $localPhotoTimestamp;
				}
			}

			$datetime = new DateTime( '@' . ( intval( $photo_date_created ) ) );
			$timestamp = $datetime->getTimestamp();

			if ( $cli_options['start_date'] && date( "Y-m-d", $timestamp ) < $cli_options['start_date'] ) {
				continue;
			}

			if ( $cli_options['end_date'] && date( "Y-m-d", $timestamp ) > $cli_options['end_date'] ) {
				continue;
			}

			echo "Found photo #" . $photo_idx . ": " . $photo_path . " (" . date( "Y-m-d", $timestamp ) . ")\n";
		}
		else {
			file_put_contents( 'php://stderr', "Couldn't find photo " . $photo_path . " in library\n" );
			continue;
		}

		$event_key = date( "Y-m-d", $timestamp );

		if ( ! isset( $json_events[ $event_key ] ) ) {
			$json_events[ $event_key ] = array(
				'id' => $event_key,
				'title' => $event_key,
				'date' => $event_key,
				'dateFriendly' => date( "F j, Y", $timestamp ),
				'photos' => array()
			);
		}

		$photo_date = date( "Y-m-d H-i-s", $timestamp );

		$idx = count( $json_events[ $event_key ]['photos'] );

		// 3 here should be strlen( number of photos in this event )
		$photo_filename = $photo_date . ' - ' . str_pad( $idx, 3, '0', STR_PAD_LEFT );

		$title = '';

		$attributes_statement = $db->prepare( "SELECT * FROM ZADDITIONALASSETATTRIBUTES WHERE Z_PK=:asset_id" );
		$attributes_statement->bindValue( ':asset_id', $row['Z_PK'] );
		$attributes_result = $attributes_statement->execute();

		if ( $attributes_row = $attributes_result->fetchArray( SQLITE3_ASSOC ) ) {
			if ( $attributes_row['ZTITLE'] ) {
				$title = $attributes_row['ZTITLE'];
			}
		} 

		if ( $title ) {
			$photo_filename .= " - " . str_replace( "/", "-", $title );
		}

		$description = '';

		$description_statement = $db->prepare( "SELECT * FROM ZASSETDESCRIPTION WHERE ZASSETATTRIBUTES=:asset_id" );
		$description_statement->bindValue( ':asset_id', $row['Z_PK'] );
		$description_result = $description_statement->execute();

		if ( $description_row = $description_result->fetchArray( SQLITE3_ASSOC ) ) {
			if ( $description_row['ZLONGDESCRIPTION'] ) {
				$description = $description_row['ZLONGDESCRIPTION'];
			}
		} 

		// Find faces.
		$face_names = array();

		$faces_in_this_photo_statement = $db->prepare( "SELECT * FROM ZDETECTEDFACE WHERE ZASSET=:photo_id" );
		$faces_in_this_photo_statement->bindValue( ':photo_id', $row['Z_PK'] );
		$faces_in_this_photo = $faces_in_this_photo_statement->execute();

		while ( $face_row = $faces_in_this_photo->fetchArray( SQLITE3_ASSOC ) ) {
			$name_statement = $db->prepare( "SELECT * FROM ZPERSON WHERE Z_PK=:person_id" );
			$name_statement->bindValue( ':person_id', $face_row['ZPERSON'] );
			$name_result = $name_statement->execute();

			$name = '';

			while ( $name_row = $name_result->fetchArray( SQLITE3_ASSOC ) ) {
				$name = $name_row['ZFULLNAME'];
			}

			$name_statement->close();

			if ( $name ) {
				$face_names[] = $name;

				if ( ! isset( $json_faces[ $name ] ) ) {
					$json_faces[ $name ] = array( 'photos' => array() );
				}

				/*
				 * There's only a single ZSIZE entry, so I'm probably not calculating the face box size correctly, but it works well enough.
				 */
				$face_width = $face_row['ZSIZE'];
				$face_height = $face_row['ZSIZE'];
				$face_lower_left_x = $face_row['ZCENTERX'] - ( $face_width / 2 );
				$face_lower_left_y = $face_row['ZCENTERY'] - ( $face_height / 2 );

				$json_faces[ $name ]['photos'][ $photo_idx ] = array( $face_lower_left_x, $face_lower_left_y, $face_width, $face_height );
			}
		}

		$faces_in_this_photo_statement->close();

		$face_names = array_values( array_unique( $face_names ) ); // array_values prevents it from being JSON encoded as an object

		$tmp = explode( ".", $photo_path );
		$photo_extension = array_pop( $tmp );

		$photo_filename = preg_replace( '/[^a-zA-Z0-9 \(\)\.,\-]/', '', $photo_filename );

		$photo_filename .= "." . $photo_extension;

		$utcPhotoTimestamp = $timestamp - $timezone_offset; // Mac OS expects this to be in UTC

		$event_folder = $cli_options['output-dir'] . $event_key . "/";
		$folders[] = $event_folder;

		$thumb_folder = $cli_options['output-dir'] . str_replace( $cli_options['output-dir'], "thumbnails/", $event_folder );

		if ( ! is_dir( $event_folder ) ) {
			mkdir( $event_folder );
		}

		if ( ! is_dir( $thumb_folder ) ) {
			mkdir( $thumb_folder );
		}

		if ( ! file_exists( $event_folder . $photo_filename ) ) {
			copy( $photo_path, $event_folder . $photo_filename );

			if ( 'photo' === $file_type && isset( $cli_options['jpegrescan'] ) ) {
				shell_exec( "jpegrescan " . escapeshellarg( $event_folder . $photo_filename ) . " " . escapeshellarg( $event_folder . $photo_filename ) . " > /dev/null 2>&1" );
			}

			shell_exec( "touch -mt " . escapeshellarg( date( "YmdHi.s", $utcPhotoTimestamp ) ) . " " . escapeshellarg( $event_folder . $photo_filename ) . " > /dev/null 2>&1" );
		}

		if ( 'photo' === $file_type && ! file_exists( $thumb_folder . "thumb_" . $photo_filename ) ) {
			shell_exec( "sips -Z 300 " . escapeshellarg( $event_folder . $photo_filename ) . " --out " . escapeshellarg( $thumb_folder . "thumb_" . $photo_filename ) . " 2> /dev/null" );

			if ( isset( $cli_options['jpegrescan'] ) ) {
				shell_exec( "jpegrescan " . escapeshellarg( $thumb_folder . "thumb_" . $photo_filename ) . " " . escapeshellarg( $thumb_folder . "thumb_" . $photo_filename ) . " > /dev/null 2>&1"  );
			}

			shell_exec( "touch -mt " . escapeshellarg( date( "YmdHi.s", $utcPhotoTimestamp ) ) . " " . escapeshellarg( $thumb_folder . "thumb_" . $photo_filename ) . " > /dev/null 2>&1" );
		}

		$json_photos[ $photo_idx ] = array(
			'id' => $photo_idx,
			'path' => str_replace( $cli_options['output-dir'], '', $event_folder . $photo_filename ),
			'thumb_path' => str_replace( $cli_options['output-dir'], '', $thumb_folder . "thumb_" . $photo_filename ),
			'event_id' => $event_key,
			'title' => $title,
			'description' => trim( $title . "\n\n" . $description ),
			'faces' => $face_names,
			'date' => date( "Y-m-d", $timestamp ),
			'dateFriendly' => date( "F j, Y g:i A", $timestamp ),
			'type' => $file_type,
		);

		$json_events[ $event_key ]['photos'][] = $photo_idx;
	}

	$db->close();
}


echo "Writing JS for website...\n";

ksort( $json_faces );
ksort( $json_events );

file_put_contents( $original_export_path . "/inc/data.js", "var events = {", FILE_APPEND );
write_json_object_without_using_so_much_memory( $original_export_path . "/inc/data.js", $json_events );
file_put_contents( $original_export_path . "/inc/data.js", "};\n\n", FILE_APPEND );
file_put_contents( $original_export_path . "/inc/data.js", "var photos = {", FILE_APPEND );
write_json_object_without_using_so_much_memory( $original_export_path . "/inc/data.js", $json_photos );
file_put_contents( $original_export_path . "/inc/data.js", "};\n\n", FILE_APPEND );
file_put_contents( $original_export_path . "/inc/data.js", "var faces = {", FILE_APPEND );
write_json_object_without_using_so_much_memory( $original_export_path . "/inc/data.js", $json_faces );
file_put_contents( $original_export_path . "/inc/data.js", "};\n\n", FILE_APPEND );

function write_json_object_without_using_so_much_memory( $path, $obj ) {
	foreach ( $obj as $idx => $member ) {
		$comma = ",";

		if ( next( $obj ) === false ) {
			$comma = "";
		}

		file_put_contents( $path, json_encode( (string) $idx ) . ': ' . json_encode( $member, JSON_PRETTY_PRINT ) . $comma . "\n", FILE_APPEND );
	}
}

function sort_photos( $a, $b ) {
	if ( $a['timestamp'] < $b['timestamp'] ) {
		return -1;

	}
	else if ( $b['timestamp'] < $a['timestamp'] ) {
		return 1;
	}

	return 0;
}

// @todo
// ZGENERICALBUM has albums
// ZKEYWORD has keywords