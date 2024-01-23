# Super-PRED Target Prediction Automaton

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nekhbet/superpred-targetprediction.svg?style=flat-square)](https://packagist.org/packages/nekhbet/superpred-targetprediction)
[![Total Downloads](https://img.shields.io/packagist/dt/nekhbet/superpred-targetprediction.svg?style=flat-square)](https://packagist.org/packages/nekhbet/superpred-targetprediction)

Lets you take (and filter) the predicted targets based on a SMILES code from Super-PRED website (https://prediction.charite.de/subpages/target_prediction.php).

## Installation

You can install the package via composer:

```bash
composer require nekhbet/superpred-targetprediction
```

## Usage

```php
$api = new SuperPREDTargetPrediction();
$data = $api
    ->setSMILESCode('Cc1cc(O)c2C(=O)c3c(O)cc(O)c4c3c3c2c1c1c2c3c3c4c(O)cc(O)c3C(=O)c2c(O)cc1C')
    ->getTargets(min_probability: 80, min_model_accuracy: 96);
```

```txt
Output example: 
...
 [12] => Array
    (
        [target_name] => Thyroid hormone receptor alpha
        [id_chembl] => CHEMBL1860
        [id_uniprot] => P10827
        [id_pdb] => 3ILZ
        [id_tdd] => T79591
        [probability] => 81.71
        [model_accuracy] => 99.15
    )
...
```

## Credits

-   [Sorin Trimbitas](https://github.com/nekhbet)

