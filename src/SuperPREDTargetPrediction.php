<?php

namespace Nekhbet\SuperPREDTargetPrediction;

use DOMDocument;
use DOMXPath;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use JetBrains\PhpStorm\NoReturn;
use Nekhbet\SuperPREDTargetPrediction\Exceptions\SuperPREDTargetPredictionException;
use Psr\Http\Message\ResponseInterface;

class SuperPREDTargetPrediction
{
    private string $endpointStage1 = 'https://prediction.charite.de/subpages/target_prediction.php';
    private string $endpointStage2 = 'https://prediction.charite.de/subpages/target_result.php';

    private array $userAgentsPool = [
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 14.2; rv:109.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ];

    private string $userAgent = '';
    private string $SMILES_code = '';

    private int $requestTimeout;
    private array $raw_data = [];
    private array $nicefied_data = [];

    public function __construct(int $requestTimeout = 20)
    {
        $this->requestTimeout = $requestTimeout;
        $this->userAgent      = $this->userAgentsPool[rand(0, count($this->userAgentsPool) - 1)];
    }

    public function setUserAgent(string $ua): SuperPREDTargetPrediction
    {
        $this->userAgent = $ua;

        return $this;
    }

    public function setSMILESCode(string $SMILES): SuperPREDTargetPrediction
    {
        $this->SMILES_code = $SMILES;

        return $this;
    }

    /**
     * @throws GuzzleException
     * @throws SuperPREDTargetPredictionException
     */
    public function run(): void
    {
        $this->scrapeData();
    }

    /**
     * @throws GuzzleException
     * @throws SuperPREDTargetPredictionException
     */
    private function scrapeData(): void
    {
        if ( ! $this->SMILES_code) {
            throw new SuperPREDTargetPredictionException("SMILES Code not set!");
        }
        // Stage 1
        $replyStage1 = $this->doPostCall($this->endpointStage1, [
            [
                'name'     => 'pubchem',
                'contents' => '',
            ],
            [
                'name'     => 'smiles',
                'contents' => $this->SMILES_code,
            ],
            [
                'name'     => 'start',
                'contents' => '',
            ],
        ]);

        if ($replyStage1->getStatusCode() !== 200) {
            throw new SuperPREDTargetPredictionException("Stage1: Invalid Status Code: ".$replyStage1->getStatusCode());
        }
        $contentStage1 = $replyStage1->getBody()->getContents();
        if (stripos($contentStage1, 'var pub_mol=') === false) {
            throw new SuperPREDTargetPredictionException("Stage1: Invalid Content");
        }

        // Stage 2
        $extractedSIM = $this->extractSIM($contentStage1);
        $replyStage2  = $this->doPostCall($this->endpointStage2, [
            [
                'name'     => 'type',
                'contents' => 'input_mol',
            ],
            [
                'name'     => 'sim',
                'contents' => $extractedSIM,
            ],
            [
                'name'     => 'searchtype',
                'contents' => 'simsearch',
            ],
        ]);

        if ($replyStage2->getStatusCode() !== 200) {
            throw new SuperPREDTargetPredictionException("Stage2: Invalid Status Code: ".$replyStage2->getStatusCode());
        }
        $contentStage2 = $replyStage2->getBody()->getContents();
        if (stripos($contentStage2, 'Model accuracy</th>') === false) {
            throw new SuperPREDTargetPredictionException("Stage2: Invalid Content");
        }

        // Extract all info
        $this->raw_data      = $this->extractRawData($contentStage2);
        $this->nicefied_data = $this->decorateRawData($this->raw_data);
    }


    /**
     * @throws GuzzleException
     */
    private function doPostCall(string $url, array $post_data): ResponseInterface
    {
        $client = new Client([
            'timeout' => $this->requestTimeout,
        ]);

        return $client->request('POST', $url, [
                'headers'   => [
                    'User-Agent' => $this->userAgent,
                ],
                'multipart' => $post_data,
            ]
        );
    }

    #[NoReturn] private function dd(): void
    {
        $args = func_get_args();
        call_user_func_array('print_r', $args);
        die();
    }

    private function extractSIM(string $contentStage1): string
    {
        preg_match('~var pub_mol=\'(.*?)\';\s*$~m', $contentStage1, $matches);
        $reply = $matches[1];

        return str_replace(['\n'], ["\n"], $reply);
    }

    private function extractRawData(string $contentStage2): array
    {
        $ret = [
            'binders'     => [],
            'targets'     => [],
            'indications' => [],
        ];
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($contentStage2);
        $xpath = new DOMXPath($doc);

        // Get binders
        $rows = $xpath->query('//div[@class="container"]//table[@id="known"]/tbody/tr');
        if ($rows->length > 0) {
            foreach ($rows as $row) {
                $cells   = $row->getElementsByTagName('td');
                $rowData = [];
                foreach ($cells as $key => $cell) {
                    $rowData[] = $cell->nodeValue;
                }
                $ret['binders'][] = $rowData;
            }
        }

        // Get indications
        $rows = $xpath->query('//div[@class="container"]//table[@id="indications"]/tbody/tr');
        if ($rows->length > 0) {
            foreach ($rows as $row) {
                $cells   = $row->getElementsByTagName('td');
                $rowData = [];
                foreach ($cells as $key => $cell) {
                    $rowData[] = $cell->nodeValue;
                }
                $ret['indications'][] = $rowData;
            }
        }

        // Get targets
        $rows = $xpath->query('//div[@class="container"]//table[@id="targets"]/tbody/tr');
        foreach ($rows as $row) {
            $cells   = $row->getElementsByTagName('td');
            $rowData = [];
            foreach ($cells as $key => $cell) {
                $rowData[] = $cell->nodeValue;
            }
            $ret['targets'][] = $rowData;
        }

        return $ret;
    }

    private function decorateRawData(array $raw_data): array
    {
        $cleaned = [
            'binders'     => [],
            'indications' => [],
            'targets'     => [],
        ];
        if ($raw_data['binders']) {
            foreach ($raw_data['binders'] as $raw_row) {
                $cleaned['binders'][] = [
                    'target_name'  => trim($raw_row[0]),
                    'id_chembl'    => trim($raw_row[1]),
                    'id_uniprot'   => trim($raw_row[2]),
                    'id_pdb'       => (trim($raw_row[3]) === 'Not Available') ? '' : trim($raw_row[3]),
                    'id_tdd'       => (trim($raw_row[4]) === 'Not Available') ? '' : trim($raw_row[4]),
                    'min_activity' => trim($raw_row[5]),
                    'assay_type'   => trim($raw_row[6]),
                ];
            }
        }

        if ($raw_data['indications']) {
            foreach ($raw_data['indications'] as $raw_row) {
                $cleaned['indications'][] = [
                    'target_name'    => trim($raw_row[0]),
                    'id_chembl'      => trim($raw_row[1]),
                    'indication'     => trim($raw_row[2]),
                    'probability'    => floatval(trim(trim($raw_row[3]), '%')),
                    'model_accuracy' => floatval(trim(trim($raw_row[4]), '%')),
                ];
            }
        }

        if ($raw_data['targets']) {
            foreach ($raw_data['targets'] as $raw_row) {
                $cleaned['targets'][] = [
                    'target_name'    => trim($raw_row[0]),
                    'id_chembl'      => trim($raw_row[1]),
                    'id_uniprot'     => trim($raw_row[2]),
                    'id_pdb'         => (trim($raw_row[3]) === 'Not Available') ? '' : trim($raw_row[3]),
                    'id_tdd'         => (trim($raw_row[4]) === 'Not Available') ? '' : trim($raw_row[4]),
                    'probability'    => floatval(trim(trim($raw_row[5]), '%')),
                    'model_accuracy' => floatval(trim(trim($raw_row[6]), '%')),
                ];
            }
        }

        return $cleaned;
    }

    public function getBinders(): array
    {
        return $this->nicefied_data['binders'];
    }

    public function getIndications(float $min_probability = 0, float $min_model_accuracy = 0): array
    {
        $data = $this->nicefied_data['indications'];
        if ($data) {
            if ($min_probability > 0 || $min_model_accuracy > 0) {
                $data = array_filter($data, function ($item) use ($min_probability, $min_model_accuracy) {
                    return ($item['probability'] >= $min_probability) && ($item['model_accuracy'] >= $min_model_accuracy);
                });
            }
        }

        return $data;
    }

    public function getTargets(float $min_probability = 0, float $min_model_accuracy = 0): array
    {
        $data = $this->nicefied_data['targets'];
        if ($data) {
            if ($min_probability > 0 || $min_model_accuracy > 0) {
                $data = array_filter($data, function ($item) use ($min_probability, $min_model_accuracy) {
                    return ($item['probability'] >= $min_probability) && ($item['model_accuracy'] >= $min_model_accuracy);
                });
            }
        }

        return $data;
    }


}
