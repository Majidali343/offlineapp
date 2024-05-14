<?php
$servername = "localhost";
$username = "root";
$password = "";
$db1 = "offlineapp";
$db2 = "offlineapp2";

// Create connection for database1
$conn1 = new mysqli($servername, $username, $password, $db1);
if ($conn1->connect_error) {
    die("Connection failed for database1: " . $conn1->connect_error);
}

// Create connection for database2
$conn2 = new mysqli($servername, $username, $password, $db2);
if ($conn2->connect_error) {
    die("Connection failed for database2: " . $conn2->connect_error);
}

// Function to synchronize a single table between two databases
function syncTable($conn1, $conn2, $table) {
    // Fetch all data from the table in database1
    $result1 = $conn1->query("SELECT * FROM $table");
    if (!$result1) {
        die("Error fetching data from $table in database1: " . $conn1->error);
    }

    // Insert/Update data from database1 into database2
    while ($row1 = $result1->fetch_assoc()) {
        $columns = array_keys($row1);
        $id = $row1[$columns[0]]; // Assuming the first column is the primary key
        $created_at = $row1['created_at'];

        $setClause = [];
        foreach ($columns as $column) {
            $setClause[] = "`$column` = '" . $conn2->real_escape_string($row1[$column]) . "'";
        }
        $setClause = implode(", ", $setClause);

        $checkQuery = "SELECT * FROM $table WHERE `{$columns[0]}` = '$id'";
        $resultCheck = $conn2->query($checkQuery);

        if ($resultCheck->num_rows > 0) {
            $existingRow = $resultCheck->fetch_assoc();
            if ($existingRow['created_at'] != $created_at) {
                // If record exists and created_at is different, insert it as a new record and delete the old one
                $columnsWithoutId = array_slice($columns, 1);
                $valuesWithoutId = array_slice($row1, 1);
                $columnsWithoutIdEscaped = array_map(function($col) { return "`$col`"; }, $columnsWithoutId);
                $valuesWithoutIdEscaped = array_map([$conn2, 'real_escape_string'], $valuesWithoutId);
                $insertQuery = "INSERT INTO $table (" . implode(", ", $columnsWithoutIdEscaped) . ") VALUES ('" . implode("', '", $valuesWithoutIdEscaped) . "')";
                if (!$conn2->query($insertQuery)) {
                    die("Error inserting data into $table in database2: " . $conn2->error);
                }
                $deleteQuery = "DELETE FROM $table WHERE `{$columns[0]}` = '$id'";
                if (!$conn1->query($deleteQuery)) {
                    die("Error deleting data from $table in database2: " . $conn2->error);
                }
            }
        } else {
            // If record does not exist, insert it
            $columnsEscaped = array_map(function($col) { return "`$col`"; }, $columns);
            $valuesEscaped = array_map([$conn2, 'real_escape_string'], array_values($row1));
            $insertQuery = "INSERT INTO $table (" . implode(", ", $columnsEscaped) . ") VALUES ('" . implode("', '", $valuesEscaped) . "')";
            if (!$conn2->query($insertQuery)) {
                die("Error inserting data into $table in database2: " . $conn2->error);
            }
        }
    }

    // Fetch all data from the table in database2
    $result2 = $conn2->query("SELECT * FROM $table");
    if (!$result2) {
        die("Error fetching data from $table in database2: " . $conn2->error);
    }

    // Insert/Update data from database2 into database1
    while ($row2 = $result2->fetch_assoc()) {
        $columns = array_keys($row2);
        $id = $row2[$columns[0]]; // Assuming the first column is the primary key
        $created_at = $row2['created_at'];

        $setClause = [];
        foreach ($columns as $column) {
            $setClause[] = "`$column` = '" . $conn1->real_escape_string($row2[$column]) . "'";
        }
        $setClause = implode(", ", $setClause);

        $checkQuery = "SELECT * FROM $table WHERE `{$columns[0]}` = '$id'";
        $resultCheck = $conn1->query($checkQuery);

        if ($resultCheck->num_rows > 0) {
            $existingRow = $resultCheck->fetch_assoc();
            if ($existingRow['created_at'] != $created_at) {
                // If record exists and created_at is different, insert it as a new record and delete the old one
                $columnsWithoutId = array_slice($columns, 1);
                $valuesWithoutId = array_slice($row2, 1);
                $columnsWithoutIdEscaped = array_map(function($col) { return "`$col`"; }, $columnsWithoutId);
                $valuesWithoutIdEscaped = array_map([$conn1, 'real_escape_string'], $valuesWithoutId);
                $insertQuery = "INSERT INTO $table (" . implode(", ", $columnsWithoutIdEscaped) . ") VALUES ('" . implode("', '", $valuesWithoutIdEscaped) . "')";
                if (!$conn1->query($insertQuery)) {
                    die("Error inserting data into $table in database1: " . $conn1->error);
                }
                $deleteQuery = "DELETE FROM $table WHERE `{$columns[0]}` = '$id'";
                if (!$conn2->query($deleteQuery)) {
                    die("Error deleting data from $table in database1: " . $conn1->error);
                }
            }
        } else {
            // If record does not exist, insert it
            $columnsEscaped = array_map(function($col) { return "`$col`"; }, $columns);
            $valuesEscaped = array_map([$conn1, 'real_escape_string'], array_values($row2));
            $insertQuery = "INSERT INTO $table (" . implode(", ", $columnsEscaped) . ") VALUES ('" . implode("', '", $valuesEscaped) . "')";
            if (!$conn1->query($insertQuery)) {
                die("Error inserting data into $table in database1: " . $conn1->error);
            }
        }
    }
}

// Get list of tables from database1
$result = $conn1->query("SHOW TABLES");
if (!$result) {
    die("Error fetching table list from database1: " . $conn1->error);
}

$tables = [];
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

// Synchronize each table
foreach ($tables as $table) {
    echo "Synchronizing table: $table\n";
    syncTable($conn1, $conn2, $table);
}

echo "Data synchronization complete!";

// Close connections
$conn1->close();
$conn2->close();
?>
