<?php

require __DIR__ . '/vendor/autoload.php';

use Bref\Context\Context;
use Bref\Event\Handler;
use Cassandra\Cluster;
use Cassandra\Session;
use Cassandra\Exception;

return function ($event, Context $context) {
    // Cassandra connection details
    $contactPoints = ['xxx']; // Use the VPS IP as the contact point
    $keyspace = 'keyspace1'; // Replace with your ScyllaDB keyspace

    try {
        // Create a Cassandra cluster and session
        $cluster = Cassandra::cluster()
            ->withContactPoints(implode(',', $contactPoints))
            ->build();
        $session = $cluster->connect($keyspace);

        // Execute a simple SELECT query on the 'standard1' table
        $query = "SELECT * FROM standard1 LIMIT 1"; // Query the 'standard1' table
        $statement = new Cassandra\SimpleStatement($query);
        $result = $session->execute($statement);

        // Process the result
        $rows = [];
        foreach ($result as $row) {
            $rows[] = $row;
        }

        // Return the result
        return [
            'statusCode' => 200,
            'body' => json_encode([
                'message' => 'Query executed successfully!',
                'rows' => $rows,
            ]),
        ];
    } catch (Exception $e) {
        // Handle Cassandra errors
        return [
            'statusCode' => 500,
            'body' => json_encode([
                'message' => 'Cassandra error: ' . (string) $e,
            ]),
        ];
    }
};