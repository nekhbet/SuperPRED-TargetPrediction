<?php

use GuzzleHttp\Exception\GuzzleException;
use Nekhbet\SuperPREDTargetPrediction\Exceptions\SuperPREDTargetPredictionException;
use Nekhbet\SuperPREDTargetPrediction\SuperPREDTargetPrediction;

include(__DIR__.'/../vendor/autoload.php');

$client = new SuperPREDTargetPrediction(requestTimeout: 60);
try {
    $client
        ->setSMILESCode('Cc1cc(O)c2C(=O)c3c(O)cc(O)c4c3c3c2c1c1c2c3c3c4c(O)cc(O)c3C(=O)c2c(O)cc1C')
//        ->setSMILESCode('c1ncccc1[C@@H]2CCCN2C')
        ->run();
    $binders     = $client->getBinders();
    $targets     = $client->getTargets(min_probability: 80, min_model_accuracy: 95);
    $indications = $client->getIndications(min_probability: 80, min_model_accuracy: 95);
} catch (GuzzleException $e) {
    die("Connection Exception: ".$e->getMessage());
} catch (SuperPREDTargetPredictionException $e) {
    die("LIB Exception: ".$e->getMessage());
}

print_r([$binders, $targets, $indications]);