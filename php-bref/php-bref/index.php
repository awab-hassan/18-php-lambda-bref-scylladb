<?php

require __DIR__ . '/vendor/autoload.php';

use Bref\Context\Context;
use Cassandra;

return function ($event, Context $context) {
    $contactPoints = ['xxx'];
    $keyspace = 'keyspace1';
    $username = 'xxx';
    $password = 'xxx';

    try {
        // Create a Cassandra cluster with credentials
        $cluster = Cassandra::cluster()
            ->withContactPoints(implode(',', $contactPoints))
            ->withCredentials($username, $password)
            ->build();

        $session = $cluster->connect($keyspace);

        // Query execution
        $query = "SELECT * FROM standard1 LIMIT 1";
        $statement = new Cassandra\SimpleStatement($query);
        $result = $session->execute($statement);

        \$rows = [];
        foreach (\$result as \$row) {
            \$rows[] = \$row;
        }

        return [
            'statusCode' => 200,
            'body' => json_encode([
                'message' => 'Query executed successfully!',
                'rows' => \$rows,
            ]),
        ];
    } catch (Cassandra\Exception \$e) {
        return [
            'statusCode' => 500,
            'body' => json_encode([
                'message' => 'Cassandra error: ' . \$e->getMessage(),
            ]),
        ];
    }
};
