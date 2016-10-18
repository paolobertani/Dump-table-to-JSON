<?php
    //
    //  dump_table_to_json
    //  version 1.0
    //
    //  dump a mysql table to a text file in json format
    //
    //  Copyright (c) 2016 Paolo Bertani - Kalei S.r.l.
    //  Licensed under the FreeBSD 2-clause license
    //


    // Show usage if argument count does not match

    if( $argc != 7 )
    {
        echo "\nUsage: \n\n";
        echo "\$ php dump_table_to_json.php HOST USER PASSWORD DATABASE TABLE PATH\n\n";
        echo "HOST:     mysql database host (ex. localhost)\n";
        echo "USER:     database user\n";
        echo "PASSWORD: password for user\n";
        echo "DATABASE: database name\n";
        echo "TABLE:    table name\n";
        echo "PATH:     path to output file\n\n";
        exit(1);
    }


    // Get arguments

    $host     = $argv[1];
    $user     = $argv[2];
    $password = $argv[3];
    $database = $argv[4];
    $table    = $argv[5];
    $filePath = $argv[6];


    // Connect to DB

    @$mysqli = new mysqli( $host, $user, $password, $database );
    if( ! $mysqli )
    {
        echo "Unable to connect to database.\n";
        exit(1);
    }
    if( $mysqli->connect_errno )
    {
        echo "Unable to connect to database: " . $mysqli->connect_error . "\n";
        exit(1);
    }


    // Get tables list

    $result = $mysqli->query( 'SHOW TABLES' );
    if( ! $result )
    {
        echo "Unable to get tables' names\n";
        $mysqli->close();
        exit(1);
    }


    // Check requested table is present

    $tables = array_column( $result->fetch_all(), 0 );
    if( ! in_array( $table, $tables ) )
    {
        echo "Specified table does not exists\n";
        $mysqli->close();
        exit(1);
    }


    // Open file for writing - file is overwritten without warnings if pre-existent

    $file = fopen( $filePath, 'w' );
    if( ! $file )
    {
        $mysqli->close();
        echo "Unable to open file for writing\n";
        exit(1);
    }


    // Loop setup

    $openbracket = false;
    $first = true;
    $offset = 0;


    // How many records to fetch at each iteration;
    // higher values result in more memory used by the PHP script, faster execution, less load on mysql

    $count = 10000;


    // Fetch all the records and dump them to file in JSON format;
    // records are fetchet in blocks with size matching the `$count` variable;
    // if more records that `$count` are present then multiple queries will be executed.

    while( true )
    {
        // Note that table name cannot be parametrized. However table named is whitlisted
        // by checking its name with the list of available tables.
        // Table name is then enclosed between backticks when inserted into the query.
        $stmt = $mysqli->prepare( 'SELECT * FROM `' . $table . '` LIMIT ?,?' );
        if( ! $stmt )
        {
            echo "Error occured while preparing query: " . $mysqli->error . "\n";
            $mysqli->close();
            fclose( $file );
            exit(1);
        }
        $stmt->bind_param( 'ii', $offset, $count );
        $success = $stmt->execute();
        if( ! $success )
        {
            echo "Error occured while executing query: " . $mysqli->error . "\n";
            $mysqli->close();
            fclose( $file );
            exit(1);
        }
        $result = $stmt->get_result();
        if( $result->num_rows == 0 )
        {
            break;
        }
        while( true )
        {
            $row = $result->fetch_assoc();
            if( $row === null )
            {
                break;
            }
            $json = json_encode( $row );
            if( ! $openbracket )
            {
                fwrite( $file, "[\n" );
                $openbracket = true;
            }
            if( ! $first )
            {
                fwrite( $file, ",\n" );
            }
            else
            {
                $first = false;
            }
            fwrite( $file, $json);
        }
        $offset += $count;
    }


    // Close file and connection

    if( $openbracket )
    {
        fwrite( $file, "\n]\n" );
    }
    fclose( $file );
    $mysqli->close();

    exit(0);