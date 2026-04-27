# Bref PHP Lambda → Cassandra / ScyllaDB Query Function

An **AWS Lambda PHP function**, built with [**Bref**](https://bref.sh/) (PHP 8.1 custom runtime on `provided.al2`), that connects to a **Cassandra / ScyllaDB** cluster and executes a query — wired up via AWS SAM. The contact-point is a self-hosted ScyllaDB node on a public IP, and the handler returns the first row from the `standard1` table in `keyspace1`. Used at production as a proof-of-concept that PHP-on-Lambda could talk to ScyllaDB before migrating production traffic.

## Highlights

- **PHP on Lambda via Bref** — `Runtime: provided.al2` + `Layers: [.../php-81:103]` gives you a real PHP 8.1 runtime; the handler is just `index.php`.
- **Cassandra DataStax PHP driver** — `Cassandra::cluster()->withContactPoints(...)->build()` + `$cluster->connect($keyspace)` talks native CQL over the wire.
- **ScyllaDB-compatible** — ScyllaDB is wire-compatible with Cassandra, so the same `Cassandra` PHP driver works against it unchanged.
- **SAM template for reproducibility** — `cf.yaml` is the AWS-generated SAM export; 1024 MB / 6 s timeout / logs policy scoped to the `/aws/lambda/bref-php-function-dev*` log group.
- **(eu-west-1)** — matches the rest of the FanSocial stack; the layer ARN (`arn:aws:lambda:xxx:xxx:layer:php-81:103`) is also regional.

## Architecture

```
 API / caller
       │ invoke
       ▼
 Lambda: bref-php-function-dev         (Runtime: provided.al2 + php-81 layer)
       │ Cassandra::cluster()
       │   withContactPoints("x.x.x.x")
       │   build()→connect("keyspace1")
       ▼
 ScyllaDB / Cassandra cluster          (self-hosted on EC2 / VPS)
       │ SELECT * FROM standard1 LIMIT 1
       ▼
 { statusCode: 200, body: { rows: [...] } }
```

## Tech stack

- **Runtime:** PHP 8.1 via Bref (`provided.al2`, `x86_64`, 1024 MB, 6 s timeout)
- **Dependencies:** `bref/bref`, DataStax `Cassandra` PHP extension
- **IaC:** AWS SAM (`AWS::Serverless-2016-10-31`)
- **AWS services:** Lambda, Lambda Layers (php-81), CloudWatch Logs
- **Database:** ScyllaDB (Cassandra-compatible), self-hosted

## Repository layout

```
CS-DEPLOYMENT/
├── README.md
├── .gitignore
├── cf.yaml                         # SAM template for the Bref PHP Lambda
├── exisitng-function/              # current deployed handler
│   ├── index.php                   # Bref handler → Cassandra query
│   ├── compose.json                # composer manifest
│   └── composer.lock
└── php-bref/                       # alternate / earlier handler layout
    └── php-bref/
```

> The subfolder name `exisitng-function` is a pre-existing typo from the original deployment — preserved to match SAM output. Fix before re-packaging.

## How it works

1. SAM / Lambda cold-start loads `index.php`; `vendor/autoload.php` pulls in `Bref` + Cassandra bindings.
2. The handler is a closure returned from `index.php` — Bref's `Handler` contract: `function ($event, Context $context)`.
3. `Cassandra::cluster()->withContactPoints('xx.xx.xx.xx')->build()` creates a cluster, `->connect('keyspace1')` opens a session.
4. `new Cassandra\SimpleStatement('SELECT * FROM standard1 LIMIT 1')` is executed; rows are collected into an array.
5. On success: `statusCode: 200` + JSON body with rows. On `Cassandra\Exception`: `statusCode: 500` with the error string.

## Prerequisites

- AWS SAM CLI (or Terraform + `aws_lambda_function` referencing the same layer)
- Composer installed locally to `composer install` into `vendor/`
- Network path from the Lambda to the ScyllaDB contact point (either a public IP with SG allowance on port 9042, or a VPC-attached Lambda + VPC peering)
- The DataStax PHP Cassandra extension available (typically via the Bref community "cassandra" layer)

## Deployment

```bash
cd exisitng-function
composer install --no-dev --optimize-autoloader
cd ..
sam build -t cf.yaml
sam deploy --guided
```

## Notes

- The contact point `xx.xx.xx.xx` is a public IP of the ScyllaDB host, move that behind VPC peering or PrivateLink and lock the SG to the Lambda's ENI.
- The `keyspace1` / `standard1` combination is the default schema produced by `cassandra-stress` — this handler was a smoke-test during capacity planning, not a real business query.
- The stock `php-81` Bref layer does not include the Cassandra extension — in practice this deployment used a custom layer or a community extension layer alongside it. Verify before redeploying.
- Demonstrates: PHP on Lambda (Bref), CQL from PHP, Cassandra ↔ ScyllaDB wire compatibility, SAM-exported Lambda configs.
